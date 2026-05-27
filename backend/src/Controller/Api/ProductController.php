<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Batch;
use App\Entity\Category;
use App\Entity\Product;
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
            'categoryId' => null !== $request->query->get('category') ? (int) $request->query->get('category') : null,
            'storageLocationId' => null !== $request->query->get('storage') ? (int) $request->query->get('storage') : null,
            'expiringWithinDays' => null !== $request->query->get('expiring_within_days') ? (int) $request->query->get('expiring_within_days') : null,
            'belowMinStock' => $request->query->getBoolean('below_min_stock'),
        ];

        $items = $this->products->findForUser($user, $filters);

        return new JsonResponse(array_map(fn (Product $p) => $this->serializeProduct($p), $items));
    }

    #[Route('/api/products', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = $this->decode($request);

        $name = trim((string) ($payload['name'] ?? ''));
        $categoryId = isset($payload['categoryId']) ? (int) $payload['categoryId'] : 0;

        if ('' === $name || 0 === $categoryId) {
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

        return new JsonResponse($this->serializeProduct($product), Response::HTTP_CREATED);
    }

    #[Route('/api/products/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $product = $this->ownedOr404($id, $user);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        return new JsonResponse($this->serializeProduct($product));
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

        return new JsonResponse($this->serializeProduct($product));
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
            $product->setBrand(null === $payload['brand'] ? null : trim((string) $payload['brand']));
        }
        if (array_key_exists('categoryId', $payload)) {
            $cat = $this->categories->find((int) $payload['categoryId']);
            if ($cat instanceof Category) {
                $product->setCategory($cat);
            }
        }
        if (array_key_exists('storageLocationId', $payload)) {
            $loc = null === $payload['storageLocationId']
                ? null
                : $this->locations->find((int) $payload['storageLocationId']);
            $product->setStorageLocation($loc instanceof StorageLocation ? $loc : null);
        }
        if (array_key_exists('preferredStoreId', $payload)) {
            $store = null === $payload['preferredStoreId']
                ? null
                : $this->stores->find((int) $payload['preferredStoreId']);
            $product->setPreferredStore($store instanceof Store ? $store : null);
        }
        if (array_key_exists('unitType', $payload) && is_string($payload['unitType'])) {
            $unit = UnitType::tryFrom($payload['unitType']);
            if (null !== $unit) {
                $product->setUnitType($unit);
            }
        }
        if (array_key_exists('minStock', $payload) && is_numeric($payload['minStock'])) {
            $product->setMinStock((string) $payload['minStock']);
        }
        if (array_key_exists('notes', $payload)) {
            $product->setNotes(null === $payload['notes'] ? null : (string) $payload['notes']);
        }
    }

    /** @return array<string, mixed> */
    public function serializeProduct(Product $p): array
    {
        $batches = $p->getBatches()->toArray();
        usort($batches, function (Batch $a, Batch $b): int {
            $ad = $a->getExpirationDate();
            $bd = $b->getExpirationDate();
            if (null === $ad && null === $bd) {
                return 0;
            }
            if (null === $ad) {
                return 1;
            }
            if (null === $bd) {
                return -1;
            }

            return $ad <=> $bd;
        });

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
            'storageLocation' => null === $p->getStorageLocation() ? null : [
                'id' => $p->getStorageLocation()->getId(),
                'name' => $p->getStorageLocation()->getName(),
            ],
            'preferredStore' => null === $p->getPreferredStore() ? null : [
                'id' => $p->getPreferredStore()->getId(),
                'name' => $p->getPreferredStore()->getName(),
            ],
            'unitType' => $p->getUnitType()->value,
            'quantity' => $p->getTotalQuantity(),
            'minStock' => $p->getMinStock(),
            'nextExpiration' => $p->getNextExpiration()?->format('Y-m-d'),
            'notes' => $p->getNotes(),
            'belowMinStock' => $p->isBelowMinStock(),
            'batches' => array_map(fn (Batch $b) => [
                'id' => $b->getId(),
                'quantity' => $b->getQuantity(),
                'expirationDate' => $b->getExpirationDate()?->format('Y-m-d'),
                'createdAt' => $b->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $b->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ], $batches),
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
}
