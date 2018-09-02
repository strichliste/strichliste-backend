<?php

namespace App\Controller\Api;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/settings")
 */
class SettingsController extends AbstractController {

    private $settingsService;

    function __construct(SettingsService $settingsService) {
        $this->settingsService = $settingsService;
    }

    /**
     * @Route(methods="GET")
     */
    public function list() {
        return $this->json([
            'settings' => $this->settingsService->getAll()
        ]);
    }
}
