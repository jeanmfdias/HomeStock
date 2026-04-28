<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ReportController
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    #[Route('/api/reports/expiring', methods: ['GET'])]
    public function expiring(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query->get('days', 7)));
        $items = $this->products->findExpiringForUser($user, $days);

        return new JsonResponse([
            'days' => $days,
            'items' => array_map(fn (Product $p) => $this->row($p), $items),
        ]);
    }

    #[Route('/api/reports/shopping-list', methods: ['GET'])]
    public function shoppingList(#[CurrentUser] User $user): JsonResponse
    {
        $items = $this->products->findShoppingListForUser($user);

        return new JsonResponse([
            'items' => array_map(fn (Product $p) => $this->row($p), $items),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Product $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'brand' => $p->getBrand(),
            'quantity' => $p->getQuantity(),
            'minStock' => $p->getMinStock(),
            'unitType' => $p->getUnitType()->value,
            'expirationDate' => $p->getExpirationDate()?->format('Y-m-d'),
            'category' => $p->getCategory()->getName(),
            'preferredStore' => $p->getPreferredStore()?->getName(),
        ];
    }
}
