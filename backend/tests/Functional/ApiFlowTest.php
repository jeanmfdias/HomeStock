<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->em->persist(new Category('Market', 'market', true));
        $this->em->persist(new Category('Cleaning', 'cleaning', false));
        $this->em->flush();
    }

    public function testAuthAndProductLifecycle(): void
    {
        // Register
        $this->json('POST', '/api/auth/register', [
            'email' => 'jean@example.com',
            'name' => 'Jean',
            'password' => 'supersecret',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Re-register same email -> 409
        $this->json('POST', '/api/auth/register', [
            'email' => 'jean@example.com',
            'name' => 'Jean',
            'password' => 'supersecret',
        ]);
        self::assertResponseStatusCodeSame(409);

        // Login
        $this->json('POST', '/api/auth/login', [
            'email' => 'jean@example.com',
            'password' => 'supersecret',
        ]);
        self::assertResponseIsSuccessful();

        // /me should now return the user
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        $me = $this->decode();
        self::assertSame('jean@example.com', $me['email']);

        // Create product (no quantity / expiration on creation)
        $marketId = $this->categoryId('market');
        $this->json('POST', '/api/products', [
            'name' => 'Milk',
            'categoryId' => $marketId,
            'unitType' => 'l',
            'minStock' => '1',
        ]);
        self::assertResponseStatusCodeSame(201);
        $product = $this->decode();
        self::assertSame('Milk', $product['name']);
        self::assertSame(0, bccomp($product['quantity'], '0', 3));
        self::assertSame([], $product['batches']);
        self::assertNull($product['nextExpiration']);
        $productId = $product['id'];

        // Add a batch (purchase) — 2 L expiring in 5 days
        $expDate = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $this->json('POST', '/api/products/'.$productId.'/batches', [
            'quantity' => '2',
            'expirationDate' => $expDate,
        ]);
        self::assertResponseStatusCodeSame(201);
        $afterAdd = $this->decode();
        self::assertCount(1, $afterAdd['batches']);
        self::assertSame(0, bccomp($afterAdd['quantity'], '2', 3));
        $batchId = $afterAdd['batches'][0]['id'];

        // Consume 1.5 L from this batch
        $this->json('POST', '/api/products/'.$productId.'/batches/'.$batchId.'/movements', [
            'delta' => '-1.5',
            'reason' => 'consume',
        ]);
        self::assertResponseIsSuccessful();
        $afterConsume = $this->decode();
        self::assertSame('0.500', $afterConsume['quantity']);
        self::assertTrue($afterConsume['belowMinStock']);

        // Shopping list contains the product
        $this->client->request('GET', '/api/reports/shopping-list');
        self::assertResponseIsSuccessful();
        $list = $this->decode();
        self::assertCount(1, $list['items']);
        self::assertSame('Milk', $list['items'][0]['name']);
        self::assertArrayNotHasKey('expirationDate', $list['items'][0]);

        // Expiring report contains a per-batch row
        $this->client->request('GET', '/api/reports/expiring?days=7');
        self::assertResponseIsSuccessful();
        $exp = $this->decode();
        self::assertCount(1, $exp['items']);
        self::assertSame($batchId, $exp['items'][0]['batchId']);
        self::assertSame($productId, $exp['items'][0]['productId']);

        // Cannot consume more than batch holds
        $this->json('POST', '/api/products/'.$productId.'/batches/'.$batchId.'/movements', [
            'delta' => '-10',
            'reason' => 'consume',
        ]);
        self::assertResponseStatusCodeSame(422);

        // Cleaning category + batch with no expirationDate -> 201
        $cleaningId = $this->categoryId('cleaning');
        $this->json('POST', '/api/products', [
            'name' => 'Soap',
            'categoryId' => $cleaningId,
            'unitType' => 'unit',
            'minStock' => '1',
        ]);
        self::assertResponseStatusCodeSame(201);
        $soap = $this->decode();
        $this->json('POST', '/api/products/'.$soap['id'].'/batches', [
            'quantity' => '3',
            'expirationDate' => null,
        ]);
        self::assertResponseStatusCodeSame(201);

        // Market category + batch without expirationDate -> 422 expiration_required_for_category
        $this->json('POST', '/api/products', [
            'name' => 'Bread',
            'categoryId' => $marketId,
            'unitType' => 'unit',
            'minStock' => '1',
        ]);
        self::assertResponseStatusCodeSame(201);
        $bread = $this->decode();
        $this->json('POST', '/api/products/'.$bread['id'].'/batches', [
            'quantity' => '1',
            'expirationDate' => null,
        ]);
        self::assertResponseStatusCodeSame(422);
        $err = $this->decode();
        self::assertSame('expiration_required_for_category', $err['error']);

        // Same expirationDate on second POST -> still one batch, quantity merged
        $this->json('POST', '/api/products/'.$productId.'/batches', [
            'quantity' => '1.5',
            'expirationDate' => $expDate,
        ]);
        self::assertResponseStatusCodeSame(201);
        $afterMerge = $this->decode();
        self::assertCount(1, $afterMerge['batches']);
        self::assertSame(0, bccomp($afterMerge['batches'][0]['quantity'], '2', 3));

        // DELETE batch with movements -> 409
        $this->client->request('DELETE', '/api/products/'.$productId.'/batches/'.$batchId);
        self::assertResponseStatusCodeSame(409);
        $delErr = $this->decode();
        self::assertSame('batch_has_movements', $delErr['error']);
    }

    public function testUnauthenticatedAccessIsRejected(): void
    {
        $this->client->request('GET', '/api/products');
        self::assertResponseStatusCodeSame(401);
    }

    /** @param array<string, mixed> $body */
    private function json(string $method, string $url, array $body): void
    {
        $this->client->request(
            $method,
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    /** @return array<string, mixed> */
    private function decode(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 16, JSON_THROW_ON_ERROR);
    }

    private function categoryId(string $slug): int
    {
        $cat = $this->em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        self::assertNotNull($cat);

        return (int) $cat->getId();
    }
}
