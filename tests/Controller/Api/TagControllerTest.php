<?php

namespace App\Tests\Controller\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

class TagControllerTest extends AbstractApplicationTestCase
{
    #[TestWith([null]), TestWith(['']), TestWith(['  '])]
    public function testAddTagRejectsBlankParameter(?string $tag): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);

        $this->client->request('POST', "/api/article/{$articleId}/tag", ['tag' => $tag]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[TestWith(['GET']), TestWith(['DELETE'])]
    #[TestDox('$method tag returns 404 for wrong article')]
    public function testTagOfOtherArticleIsNotAccessible(string $method): void
    {
        $articleAId = $this->createArticleDb('Club Mate', 150);
        $articleBId = $this->createArticleDb('Flora Mate', 160);

        $afterAdd = $this->requestJson('POST', "/api/article/{$articleAId}/tag", [
            'tag' => 'mate',
        ], 'article');
        $tagId = $afterAdd['tags'][0]['id'];

        $this->client->request($method, "/api/article/{$articleBId}/tag/{$tagId}");
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTagIsReusedAndGarbageCollected(): void
    {
        $articleAId = $this->createArticleDb('Club Mate', 150);
        $articleBId = $this->createArticleDb('Flora Mate', 160);

        $this->requestJson('POST', "/api/article/{$articleBId}/tag", ['tag' => 'cola']);
        $this->requestJson('POST', "/api/article/{$articleBId}/tag", ['tag' => 'mate']);
        $this->requestJson('POST', "/api/article/{$articleAId}/tag", ['tag' => 'mate']);

        $tags = $this->requestJson('GET', '/api/tag');
        $this->assertSame(2, $tags['count']);

        // Ordered by usageCount DESC, so mate (2) comes before cola (1)
        $this->assertSame(['mate', 'cola'], array_column($tags['tags'], 'tag'));
        $this->assertSame(2, $tags['tags'][0]['usageCount']);
        $this->assertSame(1, $tags['tags'][1]['usageCount']);

        $mateTagId = $tags['tags'][0]['id'];

        $this->requestJson('DELETE', "/api/article/{$articleAId}/tag/{$mateTagId}");

        $mateAfter = $this->requestJson('GET', "/api/article/{$articleBId}/tag/{$mateTagId}", unpackKey: 'tag');
        $this->assertSame(1, $mateAfter['usageCount']);

        $this->requestJson('DELETE', "/api/article/{$articleBId}/tag/{$mateTagId}");

        $tagsAfterGc = $this->requestJson('GET', '/api/tag');
        $this->assertSame(1, $tagsAfterGc['count']);
        $this->assertSame('cola', $tagsAfterGc['tags'][0]['tag']);
    }
}
