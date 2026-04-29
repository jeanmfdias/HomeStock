<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\MovementReason;
use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\StorageLocation;
use App\Entity\Store;
use App\Entity\UnitType;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\StorageLocationRepository;
use App\Repository\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly StorageLocationRepository $locations,
        private readonly StoreRepository $stores,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/products', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $filters = [
            'categoryId' => $request->query->get('category') !== null ? (int) $request->query->get('category') : null,
            'storageLocationId' => $request->query->get('storage') !== null ? (int) $request->query->get('storage') : null,
            'expiringWithinDays' => $request->query->get('expiring_within_days') !== null ? (int) $request->query->get('expiring_within_days') : null,
            'belowMinStock' => $request->query->getBoolean('below_min_stock'),
        ];

        $items = $this->products->findForUser($user, $filters);

        return new JsonResponse(array_map(fn (Product $p) => $this->serialize($p), $items));
    }

    #[Route('/api/products', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = $this->decode($request);

        $name = trim((string) ($payload['name'] ?? ''));
        $categoryId = isset($payload['categoryId']) ? (int) $payload['categoryId'] : 0;

        if ($name === '' || $categoryId === 0) {
            return new JsonResponse(['error' => 'name_and_categoryId_required'], 422);
        }

        $category = $this->categories->find($categoryId);
        if (!$category instanceof Category) {
            return new JsonResponse(['error' => 'category_not_found'], 422);
        }

        $product = new Product($user, $name, $category);
        $this->applyPayload($product, $payload);

        $errors = $this->validator->validate($product);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->persist($product);
        $this->em->flush();

        return new JsonResponse($this->serialize($product), Response::HTTP_CREATED);
    }

    #[Route('/api/products/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        return new JsonResponse($this->serialize($product));
    }

    #[Route('/api/products/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $payload = $this->decode($request);
        $this->applyPayload($product, $payload);
        $product->touch();

        $errors = $this->validator->validate($product);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($product));
    }

    #[Route('/api/products/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $this->em->remove($product);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/products/{id}/movements', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMovement(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $payload = $this->decode($request);
        $deltaRaw = $payload['delta'] ?? null;
        $reasonRaw = (string) ($payload['reason'] ?? '');

        if ($deltaRaw === null || !is_numeric($deltaRaw)) {
            return new JsonResponse(['error' => 'delta_required_numeric'], 422);
        }
        $reason = MovementReason::tryFrom($reasonRaw);
        if ($reason === null) {
            return new JsonResponse(['error' => 'invalid_reason'], 422);
        }

        $delta = (string) $deltaRaw;
        if (bccomp($delta, '0', 3) === 0) {
            return new JsonResponse(['error' => 'delta_must_be_nonzero'], 422);
        }

        try {
            $this->em->wrapInTransaction(function () use ($product, $delta, $reason): void {
                $newQuantity = bcadd($product->getQuantity(), $delta, 3);
                if (bccomp($newQuantity, '0', 3) < 0) {
                    throw new \DomainException('quantity_cannot_go_negative');
                }
                $product->setQuantity($newQuantity);
                $product->touch();

                $movement = new StockMovement($product, $delta, $reason);
                $this->em->persist($movement);
            });
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($this->serialize($product));
    }

    /** @return Product|JsonResponse */
    private function ownedOr404(int $id, User $user): Product|JsonResponse
    {
        $product = $this->products->find($id);
        if (!$product instanceof Product || $product->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return $product;
    }

    /** @param array<string, mixed> $payload */
    private function applyPayload(Product $product, array $payload): void
    {
        if (array_key_exists('name', $payload) && is_string($payload['name'])) {
            $product->setName(trim($payload['name']));
        }
        if (array_key_exists('brand', $payload)) {
            $product->setBrand($payload['brand'] === null ? null : trim((string) $payload['brand']));
        }
        if (array_key_exists('categoryId', $payload)) {
            $cat = $this->categories->find((int) $payload['categoryId']);
            if ($cat instanceof Category) {
                $product->setCategory($cat);
            }
        }
        if (array_key_exists('storageLocationId', $payload)) {
            $loc = $payload['storageLocationId'] === null
                ? null
                : $this->locations->find((int) $payload['storageLocationId']);
            $product->setStorageLocation($loc instanceof StorageLocation ? $loc : null);
        }
        if (array_key_exists('preferredStoreId', $payload)) {
            $store = $payload['preferredStoreId'] === null
                ? null
                : $this->stores->find((int) $payload['preferredStoreId']);
            $product->setPreferredStore($store instanceof Store ? $store : null);
        }
        if (array_key_exists('unitType', $payload) && is_string($payload['unitType'])) {
            $unit = UnitType::tryFrom($payload['unitType']);
            if ($unit !== null) {
                $product->setUnitType($unit);
            }
        }
        if (array_key_exists('quantity', $payload) && is_numeric($payload['quantity'])) {
            $product->setQuantity((string) $payload['quantity']);
        }
        if (array_key_exists('minStock', $payload) && is_numeric($payload['minStock'])) {
            $product->setMinStock((string) $payload['minStock']);
        }
        if (array_key_exists('expirationDate', $payload)) {
            $value = $payload['expirationDate'];
            if ($value === null || $value === '') {
                $product->setExpirationDate(null);
            } elseif (is_string($value)) {
                try {
                    $product->setExpirationDate(new \DateTimeImmutable($value));
                } catch (\Exception) {
                    // ignore — validator will catch missing required exp later
                }
            }
        }
        if (array_key_exists('notes', $payload)) {
            $product->setNotes($payload['notes'] === null ? null : (string) $payload['notes']);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(Product $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'brand' => $p->getBrand(),
            'category' => [
                'id' => $p->getCategory()->getId(),
                'name' => $p->getCategory()->getName(),
                'slug' => $p->getCategory()->getSlug(),
                'requiresExpiration' => $p->getCategory()->requiresExpiration(),
            ],
            'storageLocation' => $p->getStorageLocation() === null ? null : [
                'id' => $p->getStorageLocation()->getId(),
                'name' => $p->getStorageLocation()->getName(),
            ],
            'preferredStore' => $p->getPreferredStore() === null ? null : [
                'id' => $p->getPreferredStore()->getId(),
                'name' => $p->getPreferredStore()->getName(),
            ],
            'unitType' => $p->getUnitType()->value,
            'quantity' => $p->getQuantity(),
            'minStock' => $p->getMinStock(),
            'expirationDate' => $p->getExpirationDate()?->format('Y-m-d'),
            'notes' => $p->getNotes(),
            'belowMinStock' => $p->isBelowMinStock(),
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function validationError(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $fields = [];
        foreach ($errors as $error) {
            $fields[$error->getPropertyPath()] = $error->getMessage();
        }

        return new JsonResponse(['error' => 'validation_failed', 'fields' => $fields], 422);
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
