<?php

namespace App\Controller\Ui;

use App\Entity\Article;
use App\Entity\ArticleTag;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArticleTagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/articles/{id}/tags', name: 'articles_tags_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(Article $article, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_tag'.$article->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $name = trim((string) $request->request->get('tag', ''));
        if ('' === $name || mb_strlen($name) > 64) {
            $this->addFlash('error', $this->translator->trans('articles.tags.errors.invalid'));

            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $tag = $this->tagRepository->findByTag($name);
        if (!$tag) {
            $tag = new Tag($name);
            $this->em->persist($tag);
        }

        if ($article->hasTag($tag)) {
            $this->addFlash('error', $this->translator->trans('articles.tags.errors.already_attached'));

            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $article->addTag($tag);
        $this->em->persist($article);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('articles.tags.added'));

        return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/articles/{id}/tags/{tid}/delete', name: 'articles_tags_delete', methods: ['POST'], requirements: ['id' => '\d+', 'tid' => '\d+'])]
    public function delete(Article $article, int $tid, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_tag'.$tid, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $tag = $this->tagRepository->find($tid);
        if ($tag) {
            // one tx + fresh COUNT so two concurrent removers can't both delete the tag
            $tagId = $tag->getId();
            $this->em->wrapInTransaction(function () use ($article, $tag, $tagId) {
                foreach ($article->getArticleTags() as $articleTag) {
                    if ($articleTag->getTag()->getId() === $tagId) {
                        $this->em->remove($articleTag);
                        break;
                    }
                }
                $this->em->flush();

                $remaining = (int) $this->em->createQueryBuilder()
                    ->select('COUNT(at.id)')
                    ->from(ArticleTag::class, 'at')
                    ->where('at.tag = :tag')
                    ->setParameter('tag', $tag)
                    ->getQuery()->getSingleScalarResult();
                if (0 === $remaining) {
                    $this->em->remove($tag);
                    $this->em->flush();
                }
            });
            $this->addFlash('success', $this->translator->trans('articles.tags.removed'));
        }

        return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
    }
}
