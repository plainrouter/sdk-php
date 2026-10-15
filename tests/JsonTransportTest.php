<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Plainrouter\Client;
use Plainrouter\JsonResponseClient;
use Plainrouter\OpenAPI\Configuration;
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

        $this->assertIntegerInputGuardsCoverTheGeneratedContract();
    }

    /** Exercise every spec-derived integer selector inside this existing transport test case. */
    private function assertIntegerInputGuardsCoverTheGeneratedContract(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__.'/../src/integer-input-fields.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $responses = new MockHandler([
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);
        $history = [];
        $stack = HandlerStack::create($responses);
        $stack->push(Middleware::history($history));
        $httpClient = new HttpClient(['handler' => $stack]);
        $transport = new JsonResponseClient($httpClient, null, Client::DEFAULT_BASE_URL);
        $configuration = (new Configuration)->setHost(Client::DEFAULT_BASE_URL);
        $wrappers = $this->integerInputWrappers();
        $instances = [];

        foreach ($manifest as $operation => $fields) {
            if ($fields['body'] === [] && $fields['query'] === [] && $fields['path'] === []) {
                continue;
            }
            $this->assertArrayHasKey($operation, $wrappers, "Missing generated PHP guard wrapper for {$operation}");
            [$guardClass, $requestMethod, $bodyArgument, $bodyFieldArguments] = $wrappers[$operation];
            $instance = $instances[$guardClass] ??= new $guardClass($transport, $configuration);
            $apiClass = get_parent_class($guardClass);
            $this->assertIsString($apiClass);

            foreach ([...$fields['path'], ...$fields['query']] as $field) {
                $this->assertCuratedMethodRejectsBoolean(
                    $instance,
                    $apiClass,
                    $requestMethod,
                    $field,
                    $field,
                    true,
                    $history,
                );
            }

            foreach ($fields['body'] as $path) {
                $fieldParts = array_values(array_filter($path, static fn (string $part): bool => $part !== '*'));
                $field = end($fieldParts);
                if ($bodyArgument !== null) {
                    $this->assertCuratedMethodRejectsBoolean(
                        $instance,
                        $apiClass,
                        $requestMethod,
                        $bodyArgument,
                        (string) $field,
                        $this->integerFieldPayload($path, true),
                        $history,
                    );
                    continue;
                }

                $bodyField = null;
                foreach ($bodyFieldArguments as [$candidateField, $argument]) {
                    if ($candidateField === $field) {
                        $bodyField = [$candidateField, $argument];
                        break;
                    }
                }
                $this->assertNotNull($bodyField, "Missing generated PHP multipart argument for {$operation} field {$field}");
                $this->assertCuratedMethodRejectsBoolean(
                    $instance,
                    $apiClass,
                    $requestMethod,
                    $bodyField[1],
                    (string) $field,
                    true,
                    $history,
                );
            }
        }

        $accepted = false;
        foreach ($manifest as $operation => $fields) {
            if ($fields['body'] === []) {
                continue;
            }
            $path = $fields['body'][0];
            [$method, $route] = explode(' ', $operation, 2);
            $route = preg_replace('/\{[^}]+\}/', '1', $route);
            foreach ([7, '7'] as $value) {
                $payload = $this->integerFieldPayload($path, $value);
                $request = new \GuzzleHttp\Psr7\Request(
                    $method,
                    Client::DEFAULT_BASE_URL.$route,
                    ['Content-Type' => 'application/json'],
                    json_encode($payload, JSON_THROW_ON_ERROR),
                );
                $transport->send($request);
            }
            $accepted = true;
            break;
        }
        $this->assertTrue($accepted, 'The generated PHP manifest must include an integer body field');
        $this->assertCount(2, $history, 'Only the accepted integer and numeric-string requests may reach HTTP');
    }

    /** @return array<string, array{string, string, ?string, list<array{string, string}>}> */
    private function integerInputWrappers(): array
    {
        $wrappers = [];
        foreach (glob(__DIR__.'/../src/IntegerInputApis/Guarded*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (! preg_match('/class (Guarded\w+) extends \\\\Plainrouter\\\\OpenAPI\\\\Api\\\\(\w+)/', $source, $classMatch)) {
                continue;
            }
            preg_match_all(
                '~public function (\w+Request)\([^\n]*\)\s*\{\s*IntegerInputGuard::assertArguments\(\'([^\']+)\', self::class, __FUNCTION__, func_get_args\(\), (null|\'[^\']*\'), (\[[^\n]*\])\);~',
                $source,
                $methodMatches,
                PREG_SET_ORDER,
            );
            foreach ($methodMatches as $match) {
                $bodyArgument = $match[3] === 'null' ? null : substr($match[3], 1, -1);
                preg_match_all("/'([^']+)'\\s*,\\s*'([^']+)'/", $match[4], $pairMatches, PREG_SET_ORDER);
                $bodyFieldArguments = array_map(
                    static fn (array $pair): array => [$pair[1], $pair[2]],
                    $pairMatches,
                );
                $wrappers[$match[2]] = [
                    'Plainrouter\\IntegerInputApis\\'.$classMatch[1],
                    $match[1],
                    $bodyArgument,
                    $bodyFieldArguments,
                ];
            }
        }
        return $wrappers;
    }

    /** @param list<array{request: \Psr\Http\Message\RequestInterface}> $history */
    private function assertCuratedMethodRejectsBoolean(
        object $api,
        string $apiClass,
        string $requestMethod,
        string $argumentName,
        string $fieldName,
        mixed $value,
        array $history,
    ): void {
        $parameters = (new \ReflectionMethod($apiClass, $requestMethod))->getParameters();
        $argumentIndex = null;
        foreach ($parameters as $index => $parameter) {
            if ($parameter->getName() === $argumentName) {
                $argumentIndex = $index;
                break;
            }
        }
        $this->assertNotNull($argumentIndex, "Missing PHP operation parameter {$argumentName}");
        $arguments = [];
        foreach ($parameters as $index => $parameter) {
            $arguments[] = $index === $argumentIndex
                ? $value
                : ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : self::requiredArgumentValue($parameter));
        }

        try {
            $api->{substr($requestMethod, 0, -7)}(...$arguments);
            $this->fail("Boolean integer field {$fieldName} was not rejected by {$requestMethod}");
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString($fieldName, $error->getMessage());
        }
        $this->assertCount(0, $history, "Rejected boolean field {$fieldName} reached the HTTP handler");
    }

    private static function requiredArgumentValue(\ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if (! $type instanceof \ReflectionNamedType) {
            return '1';
        }
        if ($type->allowsNull()) {
            return null;
        }
        return match ($type->getName()) {
            'string' => '1',
            'int' => 1,
            'float' => 1.0,
            'bool' => false,
            'array' => [],
            default => null,
        };
    }

    /** @param list<string> $path */
    private function integerFieldPayload(array $path, mixed $value): array
    {
        $payload = [];
        $cursor = &$payload;
        foreach ($path as $index => $segment) {
            if ($segment === '*') {
                if ($index === array_key_last($path)) {
                    $cursor[0] = $value;
                } else {
                    $cursor[0] = [];
                    $cursor = &$cursor[0];
                }
            } elseif ($index === array_key_last($path)) {
                $cursor[$segment] = $value;
            } else {
                $cursor[$segment] = [];
                $cursor = &$cursor[$segment];
            }
        }
        return $payload;
    }

}
