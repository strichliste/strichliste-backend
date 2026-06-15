<?php

namespace App\Tests\Controller\Api;

class ApiDocTest extends AbstractApplicationTestCase
{
    public function testSpecCoversTheWholeApi(): void
    {
        $this->client->request('GET', '/api/doc.json');
        $this->assertResponseIsSuccessful();

        $spec = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('strichliste API', $spec['info']['title']);

        $operations = 0;
        foreach ($spec['paths'] as $operationsByMethod) {
            $operations += count(array_intersect_key(
                $operationsByMethod,
                array_flip(['get', 'post', 'put', 'delete', 'patch'])
            ));
        }
        // 29 = every /api route; update the #[OA\*] attributes on the matching
        // src/Controller/Api/* action when endpoints change.
        $this->assertSame(29, $operations);

        foreach (['User', 'Article', 'Transaction', 'Error'] as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas']);
        }
    }

    public function testSwaggerUiRenders(): void
    {
        $this->client->request('GET', '/api/doc');
        $this->assertResponseIsSuccessful();
    }
}
