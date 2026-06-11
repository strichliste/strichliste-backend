<?php

namespace App\Controller\Ui;

use App\Entity\Article;
use App\Entity\User;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController {

    private const PAGE_SIZE = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
    ) {
    }

    #[Route('/search-results', name: 'search_results', methods: ['GET'])]
    public function index(Request $request): Response {
        $q = trim((string) $request->query->get('q', ''));
        $userPage = max(1, (int) $request->query->get('user_page', 1));
        $articlePage = max(1, (int) $request->query->get('article_page', 1));

        $tooShort = mb_strlen($q) < 2;

        $users = $articles = [];
        $userTotal = $articleTotal = 0;
        if (!$tooShort) {
            [$users, $userTotal] = $this->searchUsers($q, $userPage);
            [$articles, $articleTotal] = $this->searchArticles($q, $articlePage);
        }

        return $this->render('search/results.html.twig', [
            'q' => $q,
            'tooShort' => $tooShort,
            'users' => $users,
            'userTotal' => $userTotal,
            'userPage' => $userPage,
            'userPages' => max(1, (int) ceil(($userTotal ?: 0) / self::PAGE_SIZE)),
            'articles' => $articles,
            'articleTotal' => $articleTotal,
            'articlePage' => $articlePage,
            'articlePages' => max(1, (int) ceil(($articleTotal ?: 0) / self::PAGE_SIZE)),
            'currencySymbol' => $this->settings->getOrDefault('i18n.currency.symbol', '€'),
        ]);
    }

    private function searchUsers(string $q, int $page): array {
        $offset = ($page - 1) * self::PAGE_SIZE;
        $repo = $this->em->getRepository(User::class);
        $like = '%' . $this->escapeLike($q) . '%';

        $count = (int) $repo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where("LOWER(u.name) LIKE LOWER(:q) ESCAPE '!'")
            ->andWhere('u.disabled = false')
            ->setParameter('q', $like)
            ->getQuery()->getSingleScalarResult();

        $results = $repo->createQueryBuilder('u')
            ->where("LOWER(u.name) LIKE LOWER(:q) ESCAPE '!'")
            ->andWhere('u.disabled = false')
            ->setParameter('q', $like)
            ->orderBy('u.name')
            ->setFirstResult($offset)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()->getResult();

        return [$results, $count];
    }

    private function searchArticles(string $q, int $page): array {
        $offset = ($page - 1) * self::PAGE_SIZE;
        $repo = $this->em->getRepository(Article::class);
        $like = '%' . $this->escapeLike($q) . '%';

        $count = (int) $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where("LOWER(a.name) LIKE LOWER(:q) ESCAPE '!'")
            ->andWhere('a.active = true')
            ->setParameter('q', $like)
            ->getQuery()->getSingleScalarResult();

        $results = $repo->createQueryBuilder('a')
            ->where("LOWER(a.name) LIKE LOWER(:q) ESCAPE '!'")
            ->andWhere('a.active = true')
            ->setParameter('q', $like)
            ->orderBy('a.name')
            ->setFirstResult($offset)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()->getResult();

        return [$results, $count];
    }

    private function escapeLike(string $q): string {
        // SQLite has no default LIKE escape char, hence the explicit ESCAPE '!' in the DQL;
        // LOWER() on both sides keeps the match case-insensitive across engines
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
    }
}
