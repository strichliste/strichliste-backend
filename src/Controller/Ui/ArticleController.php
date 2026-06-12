<?php

namespace App\Controller\Ui;

use App\Entity\Article;
use App\Entity\Tag;
use App\Form\CreateArticleType;
use App\Form\EditArticleType;
use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use App\Repository\TransactionRepository;
use App\Service\MoneyParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArticleController extends AbstractController
{
    private const int PAGE_SIZE = 25;

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly TagRepository $tagRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly \App\Service\ArticleService $articleService,
    ) {
    }

    #[Route('/articles', name: 'articles_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('articles_active');
    }

    #[Route('/articles/active', name: 'articles_active', methods: ['GET'])]
    public function active(Request $request): Response
    {
        return $this->renderList(true, $request);
    }

    #[Route('/articles/inactive', name: 'articles_inactive', methods: ['GET'])]
    public function inactive(Request $request): Response
    {
        return $this->renderList(false, $request);
    }

    private function renderList(bool $active, Request $request): Response
    {
        // natural sort ("Beer 2" < "Beer 10") has no portable SQL equivalent, so sort and slice in PHP
        $tag = trim((string) $request->query->get('tag', ''));
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->where('a.active = :active')
            ->setParameter('active', $active);
        if ('' !== $tag) {
            $qb->innerJoin('a.articleTags', 'at')
                ->innerJoin('at.tag', 't')
                ->andWhere('LOWER(t.tag) = LOWER(:tag)')
                ->setParameter('tag', $tag);
        }
        $articles = $qb->getQuery()->getResult();
        usort($articles, fn (Article $a, Article $b) => strnatcasecmp($a->getName() ?? '', $b->getName() ?? ''));

        $allTags = $this->tagRepository->findAll();
        usort($allTags, fn (Tag $a, Tag $b) => $b->getUsageCount() <=> $a->getUsageCount());

        $total = count($articles);
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min((int) $request->query->get('page', 1), $totalPages));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $slice = array_slice($articles, $offset, self::PAGE_SIZE);

        return $this->render('articles/list.html.twig', [
            'articles' => $slice,
            'tags' => array_slice($allTags, 0, 20),
            'activeTag' => '' !== $tag ? $tag : null,
            'active' => $active,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/articles/add', name: 'articles_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $form = $this->createForm(CreateArticleType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $article = new Article();
            $article->setName(trim((string) $data['name']));
            $article->setAmount(MoneyParser::majorToCents((float) $data['amount']));
            $this->em->persist($article);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('articles.create.success'));

            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        // render() answers 422 itself when handed a submitted invalid form — Turbo needs that
        return $this->render('articles/create.html.twig', ['form' => $form]);
    }

    #[Route('/articles/{id}/edit', name: 'articles_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Article $article, Request $request): Response
    {
        $form = $this->createForm(EditArticleType::class, [
            'name' => $article->getName(),
            'amount' => $article->getAmount() / 100,
            'active' => $article->isActive(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $resultArticle = $this->articleService->update(
                $article,
                trim((string) $data['name']),
                MoneyParser::majorToCents((float) $data['amount']),
                (bool) $data['active'],
            );

            $created = $resultArticle->getId() !== $article->getId();
            $this->addFlash('success', $this->translator->trans(
                $created ? 'articles.edit.precursor_success' : 'articles.edit.success'
            ));

            return $this->redirectToRoute('articles_edit', ['id' => $resultArticle->getId()], Response::HTTP_SEE_OTHER);
        }

        $referenceCount = $this->transactionRepository->getArticleReferenceCount($article);

        return $this->render('articles/edit.html.twig', [
            'article' => $article,
            'form' => $form,
            'priceFrozen' => $referenceCount > 0,
        ]);
    }

    #[Route('/articles/{id}/delete', name: 'articles_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function delete(Article $article, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete_article'.$article->getId(), $request->request->getString('_token'))) {
                $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));

                return $this->redirectToRoute('articles_delete', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
            }
            $article->setActive(false);
            $this->em->persist($article);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('articles.delete.success'));

            return $this->redirectToRoute('articles_inactive', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('articles/delete_confirm.html.twig', ['article' => $article]);
    }
}
