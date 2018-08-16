<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/", methods="GET")
     */
    public function list(EntityManagerInterface $entityManager)
    {
        return $this->json([
            'users' => $entityManager->getRepository(User::class)->findAllActive(),
        ]);
    }

    /**
     * @Route("/", methods="POST")
     * @
     */
    public function createUser(Request $request, EntityManagerInterface $entityManager)
    {
        $user = new User();
        $user->setBalance(0);
        $user->setActive(true);

        $name = $request->request->get('name');

        if (!$name) {
            throw new BadRequestHttpException('Missing parameter name');
        }

        if ($entityManager->getRepository(User::class)->findByName($name)) {
            return new Response(sprintf("User '%s' already exists", $name), 409);
        }

        $user->setName($name);

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new BadRequestHttpException('E-Mail address is invalid');
            }

            $user->setEmail($email);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'user' => $user
        ]);
    }

    /**
     * @Route("/{userId}")
     */
    public function user($userId, EntityManagerInterface $entityManager)
    {
        $user = $entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'user' => $user
        ]);
    }
}
