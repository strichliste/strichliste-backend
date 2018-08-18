<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/settings")
 */
class SettingsController extends AbstractController {

    /**
     * @Route(methods="GET")
     */
    public function list() {
        return $this->json([
            'settings' => $this->getParameter('strichliste')
        ]);
    }
}
