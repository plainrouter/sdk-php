<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Plainrouter\Client;
use Plainrouter\OpenAPI\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonTransportTest extends TestCase
{
    public static function redirects(): iterable
    {
        foreach ([300, 301, 302, 303, 304, 307, 308, 399] as $status) {
            foreach ([false, true] as $httpErrors) {
                yield [$status, $httpErrors];
            }
        }
    }

    #[DataProvider('redirects')]
    public function test_redirects_fail_before_sync_and_async_parsing(int $status, bool $httpErrors): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, ['Location' => '/html'], '<html>Redirect</html>'),
            new Response($status, ['Location' => '/html'], '<html>Redirect</html>'),
        ]));
        $stack->push(Middleware::history($history));
        $client = new Client(httpClient: new HttpClient(['handler' => $stack, 'http_errors' => $httpErrors]));
        foreach ([fn () => $client->operations->listEvents(), fn () => $client->operations->listEventsAsync()->wait()] as $call) {
            try {
                $call();
                $this->fail('A redirect must never return a model');
            } catch (ApiException $error) {
                $this->assertSame($status, $error->getCode());
                $this->assertSame(['/html'], $error->getResponseHeaders()['Location']);
            }
        }
        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertSame('application/json', $entry['request']->getHeaderLine('Accept'));
            $this->assertFalse($entry['options']['allow_redirects']);
        }
    }

    public function test_caller_can_override_accept_on_sync_and_async_calls(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}'), new Response(200, [], '{}')]));
        $stack->push(Middleware::history($history));
        $client = new Client(httpClient: new HttpClient(['handler' => $stack]), accept: 'text/html');
        $client->operations->listEvents();
        $client->operations->listEventsAsync()->wait();
        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertSame('text/html', $entry['request']->getHeaderLine('Accept'));
        }
    }

    public function test_caller_http_client_accept_is_retained_and_sdk_override_wins(): void
    {
        foreach (['Accept', 'ACCEPT', 'accept'] as $name) {
            foreach (['application/vnd.x+json', '*/*'] as $accept) {
                foreach ([null, 'text/html'] as $override) {
                    $history = [];
                    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}'), new Response(200, [], '{}')]));
                    $stack->push(Middleware::history($history));
                    $client = new Client(httpClient: new HttpClient(['handler' => $stack, 'headers' => ['Accept' => 'application/json', $name => $accept]]), accept: $override);
                    $client->operations->listEvents();
                    $client->operations->listEventsAsync()->wait();
                    foreach ($history as $entry) {
                        $this->assertSame($override ?? ($accept === '*/*' ? 'application/json' : $accept), $entry['request']->getHeaderLine('Accept'));
                        $this->assertCount(1, $entry['request']->getHeader('Accept'));
                    }
                }
            }
        }
    }


    public function test_stream_middleware_passes_a_buffered_copy_for_later_classification(): void
    {
        $body = '{"error":{"code":"workspace_lock_timeout","message":"Retry."}}';
        $responses = [];
        foreach ([1, 2] as $_) {
            $responses[] = new Response(503, ['Retry-After' => '1'], new \GuzzleHttp\Psr7\NoSeekStream(\GuzzleHttp\Psr7\Utils::streamFor($body)));
        }
        $history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        $stack->push(Middleware::mapResponse(static function ($response) {
            $bytes = $response->getBody()->getContents();
            return $response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($bytes));
        }));
        $client = new Client(httpClient: new HttpClient(['handler' => $stack, 'http_errors' => false, 'stream' => true]));
        $input = ['actions' => [], 'evidence' => [], 'rationale' => 'Retry test', 'idempotency_key' => 'test', 'target_source' => 'human_supplied'];
        $client->actions->actionsApiPropose(1, $input);
        $client->actions->actionsApiProposeAsync(1, $input)->wait();
        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $response = $entry['response'];
            $this->assertTrue(\Plainrouter\ProposalErrors::isRetryable($response));
            $this->assertSame($body, (string) $response->getBody());
            $this->assertSame('1', $response->getHeaderLine('Retry-After'));
        }
    }

}
