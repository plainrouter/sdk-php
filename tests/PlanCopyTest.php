<?php

declare(strict_types=1);

namespace Plainrouter\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Plainrouter\Client;
use Plainrouter\OpenAPI\Model\PlanCopyRead;

final class PlanCopyTest extends TestCase
{
    public function test_plan_copy_posts_with_auth_and_returns_a_typed_draft(): void
    {
        $draft = ['plan' => [
            'id' => 'draft-copy',
            'platform_ad_account_id' => 7,
            'status' => 'draft',
            'budget_amount_minor' => 12345,
            'validation_result' => null,
            'validated_at' => null,
            'approval_id' => null,
        ]];
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], json_encode($draft, JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));
        $client = new Client(token: 'plan-writer-test-token', httpClient: new HttpClient(['handler' => $stack]));

        [$response, $status] = $client->plans->launchPlansCopyWithHttpInfo(1, 'failed-plan');

        $this->assertSame(201, $status);
        $this->assertInstanceOf(PlanCopyRead::class, $response);
        $this->assertSame($draft, json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame([], $response->getPlan()->listInvalidProperties());
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/workspaces/1/admin/plans/failed-plan/copy', $request->getUri()->getPath());
        $this->assertSame('Bearer plan-writer-test-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('', (string) $request->getBody());
    }
}
