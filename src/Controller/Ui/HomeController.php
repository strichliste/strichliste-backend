<?php

namespace App\Controller\Ui;

use App\Repository\UserRepository;
use App\Service\SettingsService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController {

    private const PAGE_SIZE = 25;

    public function __construct(
        private UserRepository $userRepository,
        private UserService $userService,
        private SettingsService $settingsService,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): RedirectResponse {
        return $this->redirectToRoute('users_active');
    }

    #[Route('/user/active', name: 'users_active', methods: ['GET'])]
    public function activeUsers(Request $request): Response {
        $page = max(1, (int) $request->query->get('page', 1));
        $since = $this->userService->getStaleDateTime();
        $result = $this->userRepository->findAllActivePaginated($since, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        return $this->renderList($result['users'], $result['total'], $page, true);
    }

    #[Route('/user/inactive', name: 'users_inactive', methods: ['GET'])]
    public function inactiveUsers(Request $request): Response {
        $page = max(1, (int) $request->query->get('page', 1));
        $since = $this->userService->getStaleDateTime();
        $result = $this->userRepository->findAllInactivePaginated($since, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        return $this->renderList($result['users'], $result['total'], $page, false);
    }

    private function renderList(array $users, int $total, int $page, bool $active): Response {
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $totalPages);

        return $this->render('users/list.html.twig', [
            'users' => $users,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'active' => $active,
            'currencySymbol' => $this->settingsService->getOrDefault('i18n.currency.symbol', '€'),
        ]);
    }
}
