<?php

declare(strict_types=1);

namespace Plainrouter\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Configuration;
use Plainrouter\Plans;
use Throwable;

final class CopyParityTest extends TestCase
{
    public function test_copy_facade_matches_generated_for_every_other_response_and_failure(): void
    {
        $draft = json_encode(['plan' => ['id' => 'draft', 'platform_ad_account_id' => 1, 'status' => 'draft', 'budget_amount_minor' => 100, 'validation_result' => null, 'validated_at' => null, 'approval_id' => null]], JSON_THROW_ON_ERROR);
        $cases = [
            '200-valid' => [200, $draft], '201-valid' => [201, $draft],
            '200-malformed' => [200, '{not json'], '201-malformed' => [201, '{not json'], '204-empty' => [204, ''],
            '400-generic' => [400, '{"message":"Bad request"}'], '401-generic' => [401, '{"message":"Unauthenticated"}'],
            '403-generic' => [403, '{"message":"Forbidden"}'], '404-generic' => [404, '{"message":"Not found"}'],
            '413-generic' => [413, '{"message":"Too large"}'], '422-generic' => [422, '{"message":"Invalid"}'],
            '429-generic' => [429, '{"message":"Rate limited"}'], '500-generic' => [500, '{"message":"Server error"}'],
            '502-html' => [502, '<html>Unavailable</html>'], '503-generic' => [503, '{"message":"Unavailable"}'],
            '503-html' => [503, '<html>Unavailable</html>'], '503-malformed' => [503, '{not json'],
            '503-other-envelope' => [503, '{"error":{"code":"maintenance","message":"Down"}}'],
            '503-no-message' => [503, '{"error":{"code":"proposal_not_recorded"}}'],
            '503-error-null' => [503, '{"error":null}'], '503-error-array' => [503, '{"error":[]}'],
            'connect-error' => [0, ''],
        ];
        foreach ([200, 201, 204, 400, 401, 403, 404, 413, 422, 429, 500, 502, 503] as $status) {
            foreach (['generic' => '{"message":"Unavailable"}', 'html' => '<html>Unavailable</html>', 'malformed' => '{not json', 'other-envelope' => '{"error":{"code":"maintenance","message":"Down"}}'] as $kind => $body) {
                if (! in_array([$status, $body], $cases, true)) {
                    $cases["cross-$status-$kind"] = [$status, $body];
                }
            }
        }
        foreach ($cases as $name => [$status, $body]) {
            foreach ([false, true] as $httpErrors) {
                foreach ([false, true] as $async) {
                    $expected = $this->capture(false, $async, $httpErrors, $status, $body);
                    $actual = $this->capture(true, $async, $httpErrors, $status, $body);
                    if ($async && $httpErrors && in_array($status, [401, 403, 404, 413, 422, 429, 500, 503], true)) {
                        // Direct generated async calls now decode documented models; the curated copy path keeps its narrower envelope recovery.
                        $this->assertSame(ApiException::class, $actual['exception']);
                        $this->assertSame("[$status] Error connecting to the API (https://example.test/api/v1/workspaces/1/admin/plans/failed-plan/copy)", $actual['message']);
                        $this->assertSame($status, $actual['code']);
                        $this->assertSame($body, $actual['body']);
                        $this->assertSame(['Retry-After' => ['30']], $actual['headers']);
                        $this->assertSame('null', $actual['response_type']);
                        $this->assertSame('null', $actual['response_object']);
                        $this->assertSame($expected['requests'], $actual['requests']);
                        $this->assertCount(1, $actual['requests']);
                        continue;
                    }
                    $this->assertSame($expected, $actual, "$name; http_errors=".json_encode($httpErrors).'; async='.json_encode($async));
                    $this->assertCount(1, $actual['requests']);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function capture(bool $facade, bool $async, bool $httpErrors, int $status, string $body): array
    {
        $item = $status === 0 ? new ConnectException('Connection refused', new Request('POST', 'https://example.test')) : new Response($status, ['Retry-After' => '30'], $body);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([$item]));
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack, 'http_errors' => $httpErrors]);
        $configuration = (new Configuration)->setHost('https://example.test/api/v1')->setAccessToken('parity-token');
        $api = $facade ? new Plans($http, $configuration) : new DeploymentPlanApi($http, $configuration);
        try {
            $result = $async ? $api->launchPlansCopyAsyncWithHttpInfo(1, 'failed-plan')->wait() : $api->launchPlansCopyWithHttpInfo(1, 'failed-plan');
            $outcome = ['returned_type' => get_debug_type($result[0]), 'returned' => json_encode($result, JSON_THROW_ON_ERROR)];
        } catch (Throwable $error) {
            $outcome = ['exception' => get_class($error), 'message' => $error->getMessage()];
            if ($error instanceof ApiException) {
                $outcome += ['code' => $error->getCode(), 'body' => $error->getResponseBody(), 'headers' => $error->getResponseHeaders(),
                    'response_type' => get_debug_type($error->getResponseObject()), 'response_object' => json_encode($error->getResponseObject(), JSON_THROW_ON_ERROR)];
            }
        }
        $outcome['requests'] = array_map(fn (array $row): array => [$row['request']->getMethod(), (string) $row['request']->getUri(), $row['request']->getHeaderLine('Authorization'), (string) $row['request']->getBody()], $history);
        return $outcome;
    }
}
