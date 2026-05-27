<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\BatchRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ReportController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly BatchRepository $batches,
    ) {
    }

    #[Route('/api/reports/expiring', methods: ['GET'])]
    public function expiring(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query->get('days', 7)));
        $items = $this->batches->findExpiringBatchesForUser($user, $days);

        return new JsonResponse([
            'days' => $days,
            'items' => array_map(fn (Batch $b) => $this->expiringRow($b), $items),
        ]);
    }

    #[Route('/api/reports/shopping-list', methods: ['GET'])]
    public function shoppingList(#[CurrentUser] User $user): JsonResponse
    {
        $items = $this->products->findShoppingListForUser($user);

        return new JsonResponse([
            'items' => array_map(fn (Product $p) => $this->shoppingRow($p), $items),
        ]);
    }

    /** @return array<string, mixed> */
    private function expiringRow(Batch $b): array
    {
        $p = $b->getProduct();

        return [
            'productId' => $p->getId(),
            'batchId' => $b->getId(),
            'name' => $p->getName(),
            'brand' => $p->getBrand(),
            'quantity' => $b->getQuantity(),
            'minStock' => $p->getMinStock(),
            'unitType' => $p->getUnitType()->value,
            'expirationDate' => $b->getExpirationDate()?->format('Y-m-d'),
            'category' => $p->getCategory()->getName(),
            'preferredStore' => $p->getPreferredStore()?->getName(),
        ];
    }

    /** @return array<string, mixed> */
    private function shoppingRow(Product $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'brand' => $p->getBrand(),
            'quantity' => $p->getTotalQuantity(),
            'minStock' => $p->getMinStock(),
            'unitType' => $p->getUnitType()->value,
            'category' => $p->getCategory()->getName(),
            'preferredStore' => $p->getPreferredStore()?->getName(),
        ];
    }
}
