<?php

namespace App\Tests\Controller\Ui;

use App\Entity\Article;
use App\Entity\Barcode;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\SettingsService;
use App\Tests\Controller\Api\AbstractApplicationTestCase;
use App\Tests\Support\TestSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractUiTestCase extends AbstractApplicationTestCase
{
    // mirror translations/messages.en.yaml — tests assert the rendered copy,
    // not the keys; only the strings shared across test classes live here
    protected const string ERROR_BOUNDARY = 'This transaction would cross a configured boundary.';
    protected const string ERROR_ACCOUNT_DISABLED = 'This account is disabled and cannot transact.';
    protected const string ERROR_GENERIC = 'Something went wrong.';

    /**
     * Merges $overrides into the strichliste.yaml settings for the rest of
     * the test (the test env wires the mutable TestSettingsService). Forces
     * disableReboot() so the override survives across requests — the test and
     * all requests then share one container and EntityManager: clear() the EM
     * before re-reading entities, or assert through HTTP (assertUserBalance).
     *
     * @param array<string, mixed> $overrides
     */
    protected function overrideSettings(array $overrides): void
    {
        $this->client->disableReboot();

        $settings = static::getContainer()->get(SettingsService::class);
        \assert($settings instanceof TestSettingsService);

        $settings->setOverrides($overrides);
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * A service-level failure inside wrapInTransaction closes the
     * EntityManager. The worker runtime resets doctrine between requests;
     * under disableReboot() the test has to do that itself before the next
     * request (or em() call) touches the closed manager.
     */
    protected function resetEntityManager(): void
    {
        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $registry->resetManager();
    }

    protected function createBarcodeDb(int $articleId, string $code): void
    {
        $em = $this->em();
        $article = $em->find(Article::class, $articleId);
        \assert(null !== $article);

        $barcode = new Barcode($code);
        $barcode->setArticle($article);
        $em->persist($barcode);
        $em->flush();
    }

    protected function setUserDisabled(int $userId, bool $disabled = true): void
    {
        $em = $this->em();
        $user = $em->find(User::class, $userId);
        \assert(null !== $user);

        $user->setDisabled($disabled);
        $em->flush();
    }

    /**
     * Sets the balance directly, bypassing transactions and boundaries — for
     * staging accounts near a boundary without the fixtures hitting it.
     */
    protected function setUserBalance(int $userId, int $cents): void
    {
        $em = $this->em();
        $user = $em->find(User::class, $userId);
        \assert(null !== $user);

        $user->setBalance($cents);
        $em->flush();
    }

    protected function setArticleInactive(int $articleId): void
    {
        $em = $this->em();
        $article = $em->find(Article::class, $articleId);
        \assert(null !== $article);

        $article->setActive(false);
        $em->flush();
    }

    protected function lastTransactionId(int $userId): int
    {
        $tx = $this->em()->getRepository(Transaction::class)
            ->findOneBy(['user' => $userId], ['id' => 'DESC']);
        \assert(null !== $tx && null !== $tx->getId());

        return $tx->getId();
    }

    /**
     * The undo grace period compares against Transaction::created — move it
     * into the past to simulate an expired window without sleeping.
     */
    protected function backdateTransaction(int $txId, string $modify = '-10 minutes'): void
    {
        $em = $this->em();
        $tx = $em->find(Transaction::class, $txId);
        \assert(null !== $tx);

        $tx->setCreated(new \DateTime($modify));
        $em->flush();
    }

    /**
     * Asserts a flash of the given level on the page the client currently
     * shows (call after followRedirect(), or directly on 422 re-renders).
     */
    protected function assertFlash(string $level, string $needle): void
    {
        $nodes = $this->client->getCrawler()->filter('.flash--'.$level);
        $this->assertGreaterThan(0, $nodes->count(), sprintf('expected a "%s" flash', $level));

        $texts = $nodes->each(fn (Crawler $node) => $node->text());
        $this->assertStringContainsString($needle, implode(' | ', $texts));
    }

    /**
     * Value of a hidden input (usually a CSRF token) in the current page,
     * for raw POSTs that can't go through Crawler::form().
     */
    protected function hiddenValue(Crawler $crawler, string $fieldName): string
    {
        $nodes = $crawler->filter(sprintf('input[name="%s"]', $fieldName));
        $this->assertGreaterThan(0, $nodes->count(), sprintf('no hidden input "%s" in the response', $fieldName));

        $value = $nodes->first()->attr('value');
        \assert(null !== $value);

        return $value;
    }
}
