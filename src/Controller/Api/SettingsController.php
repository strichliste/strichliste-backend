<?php

namespace App\Controller\Api;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
class SettingsController extends AbstractController {
    public function __construct(private readonly SettingsService $settingsService) {}

    #[Route(methods: ['GET'])]
    public function list(): JsonResponse {
        return $this->json([
            'settings' => $this->settingsService->getAll(),
        ]);
    }
}
