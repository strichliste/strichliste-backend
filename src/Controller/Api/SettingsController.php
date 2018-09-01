<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
