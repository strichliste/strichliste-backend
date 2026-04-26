<?php

namespace App\Tests\Controller\Api;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function requestJson(string $method, string $uri, array $params = [], ?string $unpackKey = null): array
    {
        $this->client->request($method, $uri, $params);
        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        return $unpackKey === null ? $response : $response[$unpackKey];
    }

    protected function assertUserBalance(int $userId, int $expected): void
    {
        $data = $this->requestJson('GET', "/api/user/{$userId}");
        $this->assertSame($expected, $data['user']['balance']);
    }

    protected function createUserDb(string $name): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setName($name);
        $em->persist($user);
        $em->flush();

        return $user->getId();
    }

    protected function createArticleDb(string $name, int $amount): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $article = new Article();
        $article->setName($name);
        $article->setAmount($amount);
        $em->persist($article);
        $em->flush();

        return $article->getId();
    }

    protected function generateBarcode(): string
    {
        return implode('', array_map(
            fn () => random_int(0, 9),
            range(1, 13),
        ));
    }
}
