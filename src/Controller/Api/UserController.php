<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\ParameterInvalidException;
use App\Exception\ParameterMissingException;
use App\Exception\UserAlreadyExistsException;
use App\Exception\UserNotFoundException;
use App\Repository\UserRepository;
use App\Serializer\UserSerializer;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    public function __construct(private readonly UserSerializer $userSerializer)
    {
    }

    #[Route(methods: ['GET'])]
    public function list(Request $request, UserService $userService, UserRepository $userRepository): JsonResponse
    {
        $active = $request->query->getString('active');

        $staleDateTime = $userService->getStaleDateTime();

        if ('true' === $active) {
            $users = $userRepository->findAllActive($staleDateTime);
        } elseif ('false' === $active) {
            $users = $userRepository->findAllInactive($staleDateTime);
        } else {
            $users = $userRepository->findAll();
        }

        usort($users, fn (User $a, User $b) => strnatcasecmp((string) $a->getName(), (string) $b->getName()));

        return $this->json([
            'users' => array_map(fn (User $user) => $this->userSerializer->serialize($user), $users),
        ]);
    }

    #[Route(methods: ['POST'])]
    public function createUser(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        $name = User::sanitizeName($name);

        if (!$name || mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($userRepository->findByName($name)) {
            throw new UserAlreadyExistsException($name);
        }

        $user = new User();
        $user->setName($name);

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                throw new ParameterInvalidException('email');
            }

            $user->setEmail(trim($email));
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = $request->query->getString('query');
        $limit = (int) $request->query->get('limit', 25);

        $results = $userRepository->createQueryBuilder('u')
            ->where('u.name LIKE :query')
            ->andWhere('u.disabled = false')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('u.name')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($results),
            'users' => array_map(fn (User $user) => $this->userSerializer->serialize($user), $results),
        ]);
    }

    #[Route('/{userId}', methods: ['GET'])]
    public function user(string $userId, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    #[Route('/{userId}', methods: ['POST'])]
    public function updateUser(string $userId, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $name = $request->request->get('name');
        if (mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($name) {
            $name = User::sanitizeName($name);

            if ($name !== $user->getName() && $userRepository->findByName($name)) {
                throw new UserAlreadyExistsException($name);
            }

            $user->setName($name);
        }

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                throw new ParameterInvalidException('email');
            }

            $user->setEmail($email);
        }

        $isDisabled = $request->request->get('isDisabled');
        if (null !== $isDisabled) {
            // explicit: the string "false" must not coerce to true
            $user->setDisabled(filter_var($isDisabled, FILTER_VALIDATE_BOOLEAN));
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }
}
