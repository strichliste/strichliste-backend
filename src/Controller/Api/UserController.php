<?php

namespace App\Controller\Api;

use App\ApiDoc\Error as ErrorSchema;
use App\Entity\User;
use App\Exception\ParameterInvalidException;
use App\Exception\ParameterMissingException;
use App\Exception\UserAlreadyExistsException;
use App\Exception\UserNotFoundException;
use App\Repository\UserRepository;
use App\Serializer\UserSerializer;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Get(
        summary: 'List users',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'active', in: 'query', required: false, description: '"true" = users with recent activity, "false" = stale users, omitted = all.', schema: new OA\Schema(type: 'string', enum: ['true', 'false'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users sorted naturally by name.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'users', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
            ])),
        ],
    )]
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
            'users' => array_map($this->userSerializer->serialize(...), $users),
        ]);
    }

    #[Route(methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a user',
        tags: ['user'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 64),
                new OA\Property(property: 'email', type: 'string', maxLength: 255),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The created user.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 400, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 409, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function createUser(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $name = $request->request->getString('name');
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

        $email = $request->request->getString('email');
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
    #[OA\Get(
        summary: 'Search enabled users by name',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'query', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matching users.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'users', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
            ])),
        ],
    )]
    public function search(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = $request->query->getString('query');
        $limit = $request->query->getInt('limit', 25);

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
            'users' => array_map($this->userSerializer->serialize(...), $results),
        ]);
    }

    #[Route('/{userId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a user by id or name',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The user.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
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
    #[OA\Post(
        summary: 'Update a user',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 64),
            new OA\Property(property: 'email', type: 'string', maxLength: 255),
            new OA\Property(property: 'isDisabled', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The updated user.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 400, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 409, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function updateUser(string $userId, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $name = $request->request->getString('name');
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

        $email = $request->request->getString('email');
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
