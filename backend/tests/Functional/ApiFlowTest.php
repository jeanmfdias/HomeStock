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

        // Create product
        $marketId = $this->categoryId('market');
        $this->json('POST', '/api/products', [
            'name' => 'Milk',
            'categoryId' => $marketId,
            'unitType' => 'l',
            'quantity' => '2',
            'minStock' => '1',
            'expirationDate' => (new \DateTimeImmutable('+5 days'))->format('Y-m-d'),
        ]);
        self::assertResponseStatusCodeSame(201);
        $product = $this->decode();
        self::assertSame('Milk', $product['name']);
        self::assertSame(0, bccomp($product['quantity'], '2', 3));

        // Consume 1.5 L
        $this->json('POST', '/api/products/' . $product['id'] . '/movements', [
            'delta' => '-1.5',
            'reason' => 'consume',
        ]);
        self::assertResponseIsSuccessful();
        $afterConsume = $this->decode();
        self::assertSame('0.500', $afterConsume['quantity']);
        self::assertTrue($afterConsume['belowMinStock']);

        // Shopping list contains it
        $this->client->request('GET', '/api/reports/shopping-list');
        self::assertResponseIsSuccessful();
        $list = $this->decode();
        self::assertCount(1, $list['items']);
        self::assertSame('Milk', $list['items'][0]['name']);

        // Expiring report contains it
        $this->client->request('GET', '/api/reports/expiring?days=7');
        self::assertResponseIsSuccessful();
        $exp = $this->decode();
        self::assertCount(1, $exp['items']);

        // Cannot consume more than available
        $this->json('POST', '/api/products/' . $product['id'] . '/movements', [
            'delta' => '-10',
            'reason' => 'consume',
        ]);
        self::assertResponseStatusCodeSame(422);
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
