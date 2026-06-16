<?php

namespace App\Controller\Api;

use App\Service\SettingsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
class SettingsController extends AbstractController
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    /**
     * @return array{settings: array<string, mixed>}
     */
    #[Route(methods: ['GET'])]
    #[OA\Get(
        summary: 'Get the app settings',
        tags: ['settings'],
        responses: [
            new OA\Response(response: 200, description: 'The full settings tree from config/strichliste.yaml (i18n, account/payment boundaries, deposit/dispense steps, paypal, …).', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'settings', type: 'object', additionalProperties: true),
            ])),
        ],
    )]
    #[Serialize]
    public function list(): array
    {
        // dynamic config tree (no fixed schema), so a typed Response DTO does not fit;
        // #[Serialize] still removes the manual JsonResponse plumbing.
        return [
            'settings' => $this->settingsService->getAll(),
        ];
    }
}
