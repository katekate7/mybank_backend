<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class AuthController
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/register', name: 'register', methods: ['POST', 'OPTIONS'])]
    public function register(Request $request): JsonResponse
    {
        // CORS preflight response
        if ($request->isMethod('OPTIONS')) {
            return $this->corsResponse();
        }

        $data = json_decode($request->getContent(), true);
        $uuid = $data['uuid'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($uuid) || empty($password)) {
            return new JsonResponse(['status' => 'error', 'message' => 'UUID and password are required'], 400);
        }

        // Check if user with this UUID already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['uuid' => $uuid]);
        if ($existingUser) {
            return new JsonResponse(['status' => 'error', 'message' => 'UUID already registered'], 400);
        }

        // Create new User
        $user = new User();
        $user->setUuid($uuid);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $response = new JsonResponse(['status' => 'success', 'message' => 'User registered']);
        return $this->corsResponse($response);
    }

    #[Route('/login', name: 'login', methods: ['POST', 'OPTIONS'])]
    public function login(Request $request): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsResponse();
        }

        $data = json_decode($request->getContent(), true);
        $uuid = $data['uuid'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($uuid) || empty($password)) {
            return new JsonResponse(['status' => 'error', 'message' => 'UUID and password are required'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['uuid' => $uuid]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
            return $this->corsResponse($response);
        }

        $response = new JsonResponse(['status' => 'success', 'message' => 'Login successful']);
        return $this->corsResponse($response);
    }

    private function corsResponse(?JsonResponse $response = null): JsonResponse
    {
        if (!$response) {
            $response = new JsonResponse();
        }
        $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:5174');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        return $response;
    }
}
