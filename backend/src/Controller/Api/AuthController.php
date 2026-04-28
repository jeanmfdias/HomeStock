<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/auth/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $email = trim((string) ($payload['email'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        $violations = $this->validator->validate(
            ['email' => $email, 'name' => $name, 'password' => $password],
            new Assert\Collection([
                'email' => [new Assert\NotBlank(), new Assert\Email()],
                'name' => [new Assert\NotBlank(), new Assert\Length(min: 1, max: 120)],
                'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 200)],
            ])
        );
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[trim($v->getPropertyPath(), '[]')] = $v->getMessage();
            }
            return new JsonResponse(['error' => 'validation_failed', 'fields' => $errors], 422);
        }

        if ($this->users->findByEmail($email) !== null) {
            return new JsonResponse(['error' => 'email_taken'], 409);
        }

        $user = new User($email, $name);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/auth/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // The json_login firewall handles this. If we get here, security failed silently.
        return new JsonResponse(['error' => 'login_route_not_intercepted'], 500);
    }

    #[Route('/api/auth/me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['error' => 'unauthenticated'], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ]);
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
