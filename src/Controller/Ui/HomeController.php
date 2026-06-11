<?php

namespace App\Controller\Ui;

use App\Repository\UserRepository;
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
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): RedirectResponse {
        return $this->redirectToRoute('users_active');
    }

    #[Route('/user/active', name: 'users_active', methods: ['GET'])]
    public function activeUsers(Request $request): Response {
        $since = $this->userService->getStaleDateTime();
        return $this->renderList(
            fn(int $offset) => $this->userRepository->findAllActivePaginated($since, self::PAGE_SIZE, $offset),
            max(1, (int) $request->query->get('page', 1)),
            true,
        );
    }

    #[Route('/user/inactive', name: 'users_inactive', methods: ['GET'])]
    public function inactiveUsers(Request $request): Response {
        $since = $this->userService->getStaleDateTime();
        return $this->renderList(
            fn(int $offset) => $this->userRepository->findAllInactivePaginated($since, self::PAGE_SIZE, $offset),
            max(1, (int) $request->query->get('page', 1)),
            false,
        );
    }

    /**
     * @param callable(int $offset): array{users: array, total: int} $fetch
     */
    private function renderList(callable $fetch, int $page, bool $active): Response {
        $result = $fetch(($page - 1) * self::PAGE_SIZE);
        $totalPages = max(1, (int) ceil($result['total'] / self::PAGE_SIZE));

        // out-of-range page: re-fetch the real last page instead of rendering an empty list
        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $fetch(($page - 1) * self::PAGE_SIZE);
        }

        return $this->render('users/list.html.twig', [
            'users' => $result['users'],
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $result['total'],
            'active' => $active,
        ]);
    }
}
