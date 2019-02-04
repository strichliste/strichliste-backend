<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\ParameterInvalidException;
use App\Exception\ParameterMissingException;
use App\Exception\UserAlreadyExistsException;
use App\Exception\UserNotFoundException;
use App\Serializer\UserSerializer;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/user")
 */
class UserController extends AbstractController {

    /**
     * @var UserSerializer
     */
    private $userSerializer;

    function __construct(UserSerializer $userSerializer) {
        $this->userSerializer = $userSerializer;
    }

    /**
     * @Route(methods="GET")
     */
    function list(Request $request, UserService $userService, EntityManagerInterface $entityManager) {
        $active = $request->query->get('active');

        $staleDateTime = $userService->getStaleDateTime();
        $userRepository = $entityManager->getRepository(User::class);

        if ($active === 'true') {
            $users = $userRepository->findAllActive($staleDateTime);
        } elseif ($active === 'false') {
            $users = $userRepository->findAllInactive($staleDateTime);
        } else {
            $users = $userRepository->findAll();
        }

        usort($users, function (User $a, User $b) {
           return strnatcasecmp($a->getName(), $b->getName());
        });

        return $this->json([
            'users' => array_map(function (User $user) {
                return $this->userSerializer->serialize($user);
            }, $users)
        ]);
    }

    /**
     * @Route(methods="POST")
     */
    function createUser(Request $request, EntityManagerInterface $entityManager) {

        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        // TODO: Use sanitize-helper
        $name = trim($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        if (!$name || mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($entityManager->getRepository(User::class)->findByName($name)) {
            throw new UserAlreadyExistsException($name);
        }

        $user = new User();
        $user->setName($name);

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

    /**
     * @Route("/search", methods="GET")
     */
    function search(Request $request, EntityManagerInterface $entityManager) {
        $query = $request->query->get('query');
        $limit = $request->query->get('limit', 25);

        $results = $entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.name LIKE :query')
            ->andWhere('u.disabled = false')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.name')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($results),
            'articles' => array_map(function (User $user) {
                return $this->userSerializer->serialize($user);
            }, $results),
        ]);
    }

    /**
     * @Route("/{userId}", methods="GET")
     */
    function user($userId, EntityManagerInterface $entityManager) {
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    /**
     * @Route("/{userId}", methods="POST")
     */
    function updateUser($userId, Request $request, EntityManagerInterface $entityManager) {
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $name = $request->request->get('name');
        if (mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($name) {
            // TODO: Use sanitize-helper
            $name = trim($name);
            $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

            if ($name !== $user->getName() && $entityManager->getRepository(User::class)->findByName($name)) {
                throw new UserAlreadyExistsException($name);
            }

            $user->setName($name);
        }

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ParameterInvalidException('email');
            }

            $user->setEmail($email);
        }

        // TODO: Find a better way for this tri-state
        $isDisabled = $request->request->get('isDisabled');
        if ($isDisabled !== null) {
            $user->setDisabled($isDisabled);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }
}
