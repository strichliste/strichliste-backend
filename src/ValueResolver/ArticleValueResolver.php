<?php

namespace App\ValueResolver;

use App\Entity\Article;
use App\Exception\ArticleNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves an {articleId} route parameter to its Article for /api controllers,
 * throwing the domain 404 the frozen error contract expects — Symfony's built-in
 * EntityValueResolver would raise a bare NotFoundHttpException instead.
 */
final readonly class ArticleValueResolver implements ValueResolverInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Article::class !== $argument->getType() || !$request->attributes->has('articleId')) {
            return [];
        }

        $articleId = $request->attributes->get('articleId');
        $article = $this->entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        return [$article];
    }
}
