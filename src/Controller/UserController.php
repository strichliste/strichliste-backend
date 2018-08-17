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
class UserController extends AbstractController {

    /**
     * @Route("/", methods="GET")
     */
    public function list(EntityManagerInterface $entityManager) {
        return $this->json([
            'users' => $entityManager->getRepository(User::class)->findAllActive(),
        ]);
    }

    /**
     * @Route("/", methods="POST")
     * @
     */
    public function createUser(Request $request, EntityManagerInterface $entityManager) {

        $name = $request->request->get('name');

        if (!$name) {
            throw new BadRequestHttpException('Missing parameter name');
        }

        $name = trim($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        if ($entityManager->getRepository(User::class)->findByName($name)) {
            return new Response(sprintf("User '%s' already exists", $name), 409);
        }

        $user = new User();
        $user->setName($name);

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new BadRequestHttpException('E-Mail address is invalid');
            }

            $user->setEmail(trim($email));
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
    public function user($userId, EntityManagerInterface $entityManager) {

        if (is_numeric($userId)) {
            $user = $entityManager->getRepository(User::class)->find($userId);
        } else {
            $user = $entityManager->getRepository(User::class)->findByName($userId);
        }

        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'user' => $user
        ]);
    }
}
