<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\StorageLocation;
use App\Entity\Store;
use App\Repository\CategoryRepository;
use App\Repository\StorageLocationRepository;
use App\Repository\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReferenceController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categories,
        private readonly StorageLocationRepository $locations,
        private readonly StoreRepository $stores,
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('/api/categories', methods: ['GET'])]
    public function listCategories(): JsonResponse
    {
        $items = $this->categories->findBy([], ['name' => 'ASC']);
        return new JsonResponse(array_map(fn (Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
            'requiresExpiration' => $c->requiresExpiration(),
        ], $items));
    }

    #[Route('/api/categories', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name_required'], 422);
        }
        $slug = strtolower($this->slugger->slug($name)->toString());
        $cat = new Category($name, $slug, (bool) ($payload['requiresExpiration'] ?? false));
        $errors = $this->validator->validate($cat);
        if (\count($errors) > 0) {
            return new JsonResponse(['error' => 'validation_failed'], 422);
        }
        $this->em->persist($cat);
        $this->em->flush();

        return new JsonResponse([
            'id' => $cat->getId(),
            'name' => $cat->getName(),
            'slug' => $cat->getSlug(),
            'requiresExpiration' => $cat->requiresExpiration(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/storage-locations', methods: ['GET'])]
    public function listLocations(): JsonResponse
    {
        $items = $this->locations->findBy([], ['name' => 'ASC']);
        return new JsonResponse(array_map(fn (StorageLocation $l) => [
            'id' => $l->getId(),
            'name' => $l->getName(),
        ], $items));
    }

    #[Route('/api/storage-locations', methods: ['POST'])]
    public function createLocation(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name_required'], 422);
        }
        $loc = new StorageLocation($name);
        $this->em->persist($loc);
        $this->em->flush();

        return new JsonResponse(['id' => $loc->getId(), 'name' => $loc->getName()], Response::HTTP_CREATED);
    }

    #[Route('/api/stores', methods: ['GET'])]
    public function listStores(): JsonResponse
    {
        $items = $this->stores->findBy([], ['name' => 'ASC']);
        return new JsonResponse(array_map(fn (Store $s) => [
            'id' => $s->getId(),
            'name' => $s->getName(),
        ], $items));
    }

    #[Route('/api/stores', methods: ['POST'])]
    public function createStore(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name_required'], 422);
        }
        $store = new Store($name);
        $this->em->persist($store);
        $this->em->flush();

        return new JsonResponse(['id' => $store->getId(), 'name' => $store->getName()], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
