<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Batch;
use App\Entity\MovementReason;
use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\BatchRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BatchController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly BatchRepository $batches,
        private readonly ProductController $productController,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/products/{id}/batches', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $payload = $this->decode($request);
        $quantityRaw = $payload['quantity'] ?? null;
        if (null === $quantityRaw || !is_numeric($quantityRaw)) {
            return new JsonResponse(['error' => 'quantity_required_numeric'], 422);
        }
        $quantity = (string) $quantityRaw;
        if (bccomp($quantity, '0', 3) <= 0) {
            return new JsonResponse(['error' => 'quantity_must_be_positive'], 422);
        }

        $expirationDate = null;
        if (array_key_exists('expirationDate', $payload) && null !== $payload['expirationDate'] && '' !== $payload['expirationDate']) {
            try {
                $expirationDate = new \DateTimeImmutable((string) $payload['expirationDate']);
            } catch (\Exception) {
                return new JsonResponse(['error' => 'invalid_expiration_date'], 422);
            }
        }

        try {
            $resultBatch = $this->em->wrapInTransaction(function () use ($product, $quantity, $expirationDate): Batch {
                $existing = $this->batches->findOneByProductAndExpiration($product, $expirationDate);

                if ($existing instanceof Batch) {
                    $existing->setQuantity(bcadd($existing->getQuantity(), $quantity, 3));
                    $existing->touch();
                    $this->em->persist(new StockMovement($existing, $quantity, MovementReason::PURCHASE));
                    $product->touch();

                    return $existing;
                }

                $batch = new Batch($product, $quantity, $expirationDate);
                $errors = $this->validator->validate($batch);
                if (\count($errors) > 0) {
                    throw new \DomainException($this->violationsKey($errors));
                }

                $this->em->persist($batch);
                $this->em->persist(new StockMovement($batch, $quantity, MovementReason::PURCHASE));
                $product->touch();

                return $batch;
            });
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $this->em->refresh($product);

        return new JsonResponse($this->productController->serializeProduct($product), Response::HTTP_CREATED);
    }

    #[Route('/api/products/{id}/batches/{batchId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'batchId' => '\d+'])]
    public function delete(int $id, int $batchId, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $batch = $this->batches->find($batchId);
        if (!$batch instanceof Batch || $batch->getProduct()->getId() !== $product->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        if (\count($batch->getMovements()) > 0) {
            return new JsonResponse(['error' => 'batch_has_movements'], 409);
        }

        $this->em->remove($batch);
        $product->touch();
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/products/{id}/batches/{batchId}/movements', methods: ['POST'], requirements: ['id' => '\d+', 'batchId' => '\d+'])]
    public function addMovement(int $id, int $batchId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $batch = $this->batches->find($batchId);
        if (!$batch instanceof Batch || $batch->getProduct()->getId() !== $product->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        $payload = $this->decode($request);
        $deltaRaw = $payload['delta'] ?? null;
        $reasonRaw = (string) ($payload['reason'] ?? '');

        if (null === $deltaRaw || !is_numeric($deltaRaw)) {
            return new JsonResponse(['error' => 'delta_required_numeric'], 422);
        }
        $reason = MovementReason::tryFrom($reasonRaw);
        if (null === $reason) {
            return new JsonResponse(['error' => 'invalid_reason'], 422);
        }

        $delta = (string) $deltaRaw;
        if (0 === bccomp($delta, '0', 3)) {
            return new JsonResponse(['error' => 'delta_must_be_nonzero'], 422);
        }

        try {
            $this->em->wrapInTransaction(function () use ($batch, $product, $delta, $reason): void {
                $newQuantity = bcadd($batch->getQuantity(), $delta, 3);
                if (bccomp($newQuantity, '0', 3) < 0) {
                    throw new \DomainException('quantity_cannot_go_negative');
                }
                $batch->setQuantity($newQuantity);
                $batch->touch();
                $product->touch();

                $this->em->persist(new StockMovement($batch, $delta, $reason));
            });
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($this->productController->serializeProduct($product));
    }

    private function ownedOr404(int $id, User $user): Product|JsonResponse
    {
        $product = $this->products->find($id);
        if (!$product instanceof Product || $product->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function violationsKey(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): string
    {
        foreach ($errors as $error) {
            return (string) $error->getMessage();
        }

        return 'validation_failed';
    }
}
