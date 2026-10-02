<?php

declare(strict_types=1);

namespace Plainrouter\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plainrouter\Client;
use Plainrouter\Events;
use Plainrouter\OpenAPI\Api\EventApi;
use Plainrouter\OpenAPI\Api\OperationsApi;
use Plainrouter\OpenAPI\Api\SandboxApi;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ActionPolicyRead;
use Plainrouter\OpenAPI\Model\CreateEvent202Response;
use Plainrouter\OpenAPI\Model\GetSandbox200Response;
use Plainrouter\OpenAPI\ObjectSerializer;
use Plainrouter\Version;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;

final class ClientTest extends TestCase
{
    private const VALID_CAPTURED_AT = '2026-08-19T12:34:56.123456+02:00';

    private const ACCEPTED = '{"event_id":"event-123","duplicate":false,"warnings":[]}';

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public function test_exposes_three_service_groups_and_the_configuration(): void
    {
        $properties = array_map(
            static fn ($property): string => $property->getName(),
            (new ReflectionClass(Client::class))->getProperties(),
        );
        sort($properties);

        $this->assertSame(['configuration', 'events', 'operations', 'sandbox'], $properties);

        $client = new Client();
        $this->assertInstanceOf(EventApi::class, $client->events);
        $this->assertInstanceOf(OperationsApi::class, $client->operations);
        $this->assertInstanceOf(SandboxApi::class, $client->sandbox);
    }

    public function test_exposes_the_signed_contract_operations(): void
    {
        $this->assertSame(
            ['createEvent', 'getEvent', 'verifySignalIngestion'],
            $this->operationNames(EventApi::class),
        );
        $this->assertSame(
            [
                'deleteUserData',
                'getEmqReport',
                'getReconciliationReport',
                'listEvents',
                'listEventsByCursor',
                'replayDeliveries',
                'sendTestPurchase',
                'setDestinationTestMode',
            ],
            $this->operationNames(OperationsApi::class),
        );
        $this->assertSame(
            ['createSandboxKey', 'getSandbox', 'getSandboxKey', 'validateSandboxEvent', 'validateSandboxEventWithKey'],
            $this->operationNames(SandboxApi::class),
        );
    }

    public function test_targets_the_vendored_contract_version(): void
    {
        $contract = json_decode(
            (string) file_get_contents(__DIR__.'/../../../spec/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame($contract['info']['version'], Version::CONTRACT);
    }

    public function test_configures_base_url_token_and_user_agent(): void
    {
        $client = new Client(
            token: 'tracker-secret',
            baseUrl: 'http://localhost:4567/custom/v1/',
            userAgent: 'test-agent/1',
        );

        $this->assertSame('http://localhost:4567/custom/v1', $client->configuration->getHost());
        $this->assertSame('tracker-secret', $client->configuration->getAccessToken());
        $this->assertSame('test-agent/1', $client->configuration->getUserAgent());
        $this->assertSame($client->configuration, $client->events->getConfig());
        $this->assertSame($client->configuration, $client->operations->getConfig());
        $this->assertSame($client->configuration, $client->sandbox->getConfig());
    }

    public function test_uses_safe_defaults(): void
    {
        $client = new Client();

        $this->assertSame(Client::DEFAULT_BASE_URL, $client->configuration->getHost());
        $this->assertSame('plainrouter-php/'.Version::SDK, $client->configuration->getUserAgent());
        $this->assertSame('', $client->configuration->getAccessToken());
    }

    #[DataProvider('ambiguousBaseUrls')]
    public function test_rejects_ambiguous_base_urls(string $baseUrl): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client(baseUrl: $baseUrl);
    }

    /**
     * @return list<array{string}>
     */
    public static function ambiguousBaseUrls(): array
    {
        return [
            ['plainrouter.com/api/v1'],
            ['https://example.com/api?token=secret'],
            ['https://example.com/api#fragment'],
            ['ftp://example.com/api'],
        ];
    }

    public function test_sends_the_tracker_secret_as_a_bearer_token(): void
    {
        $client = $this->stubbedClient([new Response(401, ['Content-Type' => 'application/json'], '{"message":"Unauthenticated."}')], 'tracker-secret');

        try {
            $client->operations->listEvents();
            $this->fail('Expected an API exception.');
        } catch (ApiException $exception) {
            $this->assertSame(401, $exception->getCode());
        }

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer tracker-secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('/api/v1/dashboard/events', $request->getUri()->getPath());
        $this->assertSame('plainrouter-php/'.Version::SDK, $request->getHeaderLine('User-Agent'));
    }

    public function test_zero_auth_sandbox_does_not_send_an_authorization_header(): void
    {
        $client = $this->stubbedClient([new Response(503, ['Content-Type' => 'application/json'], '{"message":"Unavailable."}')]);

        try {
            $client->sandbox->getSandbox();
            $this->fail('Expected an API exception.');
        } catch (ApiException $exception) {
            $this->assertSame(503, $exception->getCode());
        }

        $this->assertFalse($this->history[0]['request']->hasHeader('Authorization'));
    }

    public function test_rejects_visitor_id_without_captured_at_before_making_a_call(): void
    {
        $client = $this->stubbedClient([new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED)], 'tracker-secret');

        try {
            $client->events->createEvent([
                'event_name' => 'Purchase',
                'consent_basis' => 'consent',
                'visitor_id' => 'visitor-123',
            ]);
            $this->fail('Expected the body to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertMatchesRegularExpression('/consent\.captured_at/', $exception->getMessage());
            $this->assertMatchesRegularExpression('/required|missing/', $exception->getMessage());
        }

        $this->assertSame([], $this->history);
    }

    #[DataProvider('invalidCaptureTimes')]
    public function test_rejects_an_invalid_captured_at_before_making_a_call(string $capturedAt): void
    {
        $client = $this->stubbedClient([new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED)], 'tracker-secret');

        try {
            $client->events->createEvent([
                'event_name' => 'Purchase',
                'consent_basis' => 'consent',
                'user_data' => ['em' => 'hashed-email'],
                'consent' => ['captured_at' => $capturedAt],
            ]);
            $this->fail('Expected the body to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertMatchesRegularExpression('/consent\.captured_at/', $exception->getMessage());
            $this->assertMatchesRegularExpression('/invalid|format|ISO/', $exception->getMessage());
        }

        $this->assertSame([], $this->history);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCaptureTimes(): array
    {
        return [
            'space separator' => ['2026-08-19 12:34:56+02:00'],
            'missing offset' => ['2026-08-19T12:34:56'],
            'impossible date' => ['2026-02-30T12:34:56Z'],
            'impossible hour' => ['2026-08-19T24:00:00Z'],
            'seven fraction digits' => ['2026-08-19T12:34:56.1234567Z'],
            'trailing newline' => ["2026-08-19T12:34:56Z\n"],
        ];
    }

    public function test_sends_a_valid_captured_at_byte_identical_to_input(): void
    {
        $client = $this->stubbedClient([new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED)], 'tracker-secret');

        $client->events->createEvent([
            'event_name' => 'Purchase',
            'consent_basis' => 'consent',
            'visitor_id' => 'visitor-123',
            'consent' => ['captured_at' => self::VALID_CAPTURED_AT],
        ]);

        $this->assertStringContainsString(
            '"captured_at":"'.self::VALID_CAPTURED_AT.'"',
            (string) $this->history[0]['request']->getBody(),
        );
    }

    public function test_sends_events_without_identity_when_captured_at_is_absent(): void
    {
        $client = $this->stubbedClient([
            new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED),
            new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED),
        ], 'tracker-secret');

        $client->events->createEvent(['event_name' => 'Purchase', 'consent_basis' => 'consent']);
        $client->events->createEvent([
            'event_name' => 'Purchase',
            'consent_basis' => 'consent',
            'visitor_id' => null,
            'user_data' => null,
        ]);

        $this->assertCount(2, $this->history);
    }

    public function test_validates_the_async_create_event_path_too(): void
    {
        $client = $this->stubbedClient([new Response(202, ['Content-Type' => 'application/json'], self::ACCEPTED)], 'tracker-secret');

        $this->expectException(InvalidArgumentException::class);

        $client->events->createEventAsync([
            'event_name' => 'Purchase',
            'consent_basis' => 'consent',
            'visitor_id' => 'visitor-123',
        ]);
    }

    public function test_returns_a_typed_202_response(): void
    {
        $body = '{"event_id":"event-123","duplicate":false,"warnings":[{"code":"consent_captured_at_invalid","message":"Consent capture time was invalid."}]}';
        $client = $this->stubbedClient([new Response(202, ['Content-Type' => 'application/json'], $body)], 'tracker-secret');

        $result = $client->events->createEvent(['event_name' => 'Purchase', 'consent_basis' => 'consent']);

        $this->assertInstanceOf(CreateEvent202Response::class, $result);
        $this->assertSame('event-123', $result->getEventId());
        $this->assertSame([], $result->listInvalidProperties());
    }

    public function test_policy_model_parses_the_current_response_and_flags_an_invalid_mode(): void
    {
        $data = [
            'id' => null,
            'workspace_id' => 1,
            'execution_mode' => 'full',
            'outcome_check_after_hours' => 24,
            'anomaly_threshold_percent' => '20.00',
        ];

        $policy = ObjectSerializer::deserialize(json_encode(['data' => $data]), ActionPolicyRead::class);
        $this->assertInstanceOf(ActionPolicyRead::class, $policy);
        $this->assertSame([], $policy->getData()->listInvalidProperties());
        $this->assertSame('full', $policy->getData()->getExecutionMode());

        $this->expectException(InvalidArgumentException::class);
        $policy->getData()->setExecutionMode('unknown_mode');
    }

    public function test_sandbox_model_flags_a_response_missing_required_fields(): void
    {
        $sandbox = ObjectSerializer::deserialize('{"environment":"sandbox"}', GetSandbox200Response::class);

        $this->assertInstanceOf(GetSandbox200Response::class, $sandbox);
        $this->assertNotSame([], $sandbox->listInvalidProperties());
    }

    public function test_events_keeps_the_server_capture_time_pattern(): void
    {
        $this->assertSame(
            '/\A(?<datetime>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d{1,6}))?(?<offset>Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/',
            Events::CAPTURED_AT_PATTERN,
        );
    }

    /**
     * @param  list<Response>  $responses
     */
    private function stubbedClient(array $responses, ?string $token = null): Client
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Client(token: $token, httpClient: new HttpClient(['handler' => $stack]));
    }

    /**
     * @param  class-string  $api
     * @return list<string>
     */
    private function operationNames(string $api): array
    {
        $names = [];

        foreach ((new ReflectionClass($api))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_ends_with($method->getName(), 'WithHttpInfo') && ! str_ends_with($method->getName(), 'AsyncWithHttpInfo')) {
                $names[] = substr($method->getName(), 0, -strlen('WithHttpInfo'));
            }
        }

        sort($names);

        return $names;
    }
}
