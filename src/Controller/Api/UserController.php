<?php

namespace App\Controller\Api;

use App\Dto\Api\CreateUserDto;
use App\Dto\Api\UpdateUserDto;
use App\Dto\Api\UserListResponse;
use App\Dto\Api\UserResponse;
use App\Dto\Api\UserSearchResponse;
use App\Entity\User;
use App\Exception\UserAlreadyExistsException;
use App\Exception\UserNotFoundException;
use App\Repository\UserRepository;
use App\Serializer\UserSerializer;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\Serialize;
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
            new OA\Response(response: 200, description: 'Users sorted naturally by name.', content: new OA\JsonContent(ref: new Model(type: UserListResponse::class))),
        ],
    )]
    #[Serialize]
    public function list(Request $request, UserService $userService, UserRepository $userRepository): UserListResponse
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

        return new UserListResponse(
            users: array_map($this->userSerializer->serialize(...), $users),
        );
    }

    #[Route(methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a user',
        tags: ['user'],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: CreateUserDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: CreateUserDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The created user.', content: new OA\JsonContent(ref: new Model(type: UserResponse::class))),
            new OA\Response(response: 422, ref: '#/components/responses/Error'),
            new OA\Response(response: 409, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function createUser(#[MapRequestPayload] CreateUserDto $dto, UserRepository $userRepository, EntityManagerInterface $entityManager): UserResponse
    {
        if ($userRepository->findByName($dto->name)) {
            throw new UserAlreadyExistsException($dto->name);
        }

        $user = new User();
        $user->setName($dto->name);

        if ($dto->email) {
            $user->setEmail($dto->email);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return new UserResponse($this->userSerializer->serialize($user));
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
            new OA\Response(response: 200, description: 'Matching users.', content: new OA\JsonContent(ref: new Model(type: UserSearchResponse::class))),
        ],
    )]
    #[Serialize]
    public function search(Request $request, UserRepository $userRepository): UserSearchResponse
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

        return new UserSearchResponse(
            count: count($results),
            users: array_map($this->userSerializer->serialize(...), $results),
        );
    }

    #[Route('/{userId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a user by id or name',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The user.', content: new OA\JsonContent(ref: new Model(type: UserResponse::class))),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function user(string $userId, UserRepository $userRepository): UserResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        return new UserResponse($this->userSerializer->serialize($user));
    }

    #[Route('/{userId}', methods: ['POST'])]
    #[OA\Post(
        summary: 'Update a user',
        tags: ['user'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: UpdateUserDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: UpdateUserDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The updated user.', content: new OA\JsonContent(ref: new Model(type: UserResponse::class))),
            new OA\Response(response: 422, ref: '#/components/responses/Error'),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
            new OA\Response(response: 409, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function updateUser(string $userId, #[MapRequestPayload] UpdateUserDto $dto, UserRepository $userRepository, EntityManagerInterface $entityManager): UserResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        if ($dto->name) {
            if ($dto->name !== $user->getName() && $userRepository->findByName($dto->name)) {
                throw new UserAlreadyExistsException($dto->name);
            }

            $user->setName($dto->name);
        }

        if ($dto->email) {
            $user->setEmail($dto->email);
        }

        if (null !== $dto->isDisabled) {
            $user->setDisabled($dto->isDisabled);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return new UserResponse($this->userSerializer->serialize($user));
    }
}
