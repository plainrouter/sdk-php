<?php

declare(strict_types=1);

namespace Plainrouter\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Plainrouter\Client;
use Plainrouter\OpenAPI\Api\ActionProposalApiApi;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ProposalUnavailable;
use Plainrouter\OpenAPI\Model\ProposalUnavailableError;
use Plainrouter\OpenAPI\Model\WorkspaceLockTimeout;
use Plainrouter\ProposalErrors;

final class ProposalErrorsTest extends TestCase
{
    public function test_proposal_refusals_are_typed_retryable_and_retain_retry_after(): void
    {
        foreach (['proposal_not_recorded', 'workspace_lock_timeout'] as $code) {
            foreach (['propose', 'execute'] as $operation) {
                $envelope = ['error' => ['code' => $code, 'message' => 'Retry the proposal.']];
                $httpClient = new HttpClient(['handler' => HandlerStack::create(new MockHandler([
                    new Response(503, ['Content-Type' => 'application/json', 'Retry-After' => '1'], json_encode($envelope, JSON_THROW_ON_ERROR)),
                ]))]);
                $client = new Client(token: 'proposal-test-token', httpClient: $httpClient);

                try {
                    if ($operation === 'propose') {
                        (new ActionProposalApiApi($httpClient, $client->configuration))->actionsApiPropose(1, [
                            'actions' => [], 'evidence' => [], 'rationale' => 'Pause the ad.',
                            'idempotency_key' => 'retry-test', 'target_source' => 'human_supplied',
                        ]);
                    } else {
                        $client->plans->launchPlansExecute(1, 'plan-test', ['review_version' => 'review-test']);
                    }
                    $this->fail('Expected a proposal API exception.');
                } catch (ApiException $exception) {
                    $this->assertSame(503, $exception->getCode());
                    $this->assertInstanceOf(ProposalUnavailable::class, $exception->getResponseObject());
                    $this->assertSame($code, $exception->getResponseObject()->getError()->getCode());
                    $this->assertSame($envelope, json_decode(json_encode($exception->getResponseObject(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
                    $this->assertSame(['1'], $exception->getResponseHeaders()['Retry-After']);
                    $this->assertTrue(ProposalErrors::isRetryable($exception));
                }
            }
        }
    }

    public function test_copy_classifies_both_codes_and_preserves_retry_after(): void
    {
        foreach (['workspace_lock_timeout', 'proposal_not_recorded'] as $code) {
            foreach (['1', 'Wed, 14 Oct 2026 12:00:00 GMT', null] as $retryAfter) {
                foreach ([false, true] as $async) {
                    $envelope = ['error' => ['code' => $code, 'message' => 'Retry the proposal.']];
                    $headers = ['Content-Type' => 'application/json'];
                    if ($retryAfter !== null) {
                        $headers['Retry-After'] = $retryAfter;
                    }
                    $httpClient = new HttpClient(['handler' => HandlerStack::create(new MockHandler([
                        new Response(503, $headers, json_encode($envelope, JSON_THROW_ON_ERROR)),
                    ]))]);
                    $client = new Client(token: 'proposal-test-token', httpClient: $httpClient);
                    try {
                        if ($async) {
                            $client->plans->launchPlansCopyAsync(1, 'failed-plan')->wait();
                        } else {
                            $client->plans->launchPlansCopy(1, 'failed-plan');
                        }
                        $this->fail('Expected a copy API exception.');
                    } catch (ApiException $exception) {
                        $this->assertSame(503, $exception->getCode());
                        $this->assertInstanceOf($code === 'workspace_lock_timeout' ? WorkspaceLockTimeout::class : ProposalUnavailable::class, $exception->getResponseObject());
                        $this->assertSame($code, $exception->getResponseObject()->getError()->getCode());
                        $this->assertSame($envelope, json_decode(json_encode($exception->getResponseObject(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
                        $this->assertSame($retryAfter === null ? null : [$retryAfter], $exception->getResponseHeaders()['Retry-After'] ?? null);
                        $this->assertTrue(ProposalErrors::isRetryable($exception));
                    }
                }
            }
        }
    }

    public function test_generated_async_errors_are_classified_by_code_without_a_response_model(): void
    {
        foreach (['workspace_lock_timeout', 'proposal_not_recorded'] as $code) {
            foreach (['propose', 'execute'] as $operation) {
                $envelope = ['error' => ['code' => $code, 'message' => 'Retry the proposal.']];
                $httpClient = new HttpClient(['handler' => HandlerStack::create(new MockHandler([
                    new Response(503, ['Content-Type' => 'application/json', 'Retry-After' => '1'], json_encode($envelope, JSON_THROW_ON_ERROR)),
                ]))]);
                $client = new Client(token: 'proposal-test-token', httpClient: $httpClient);
                try {
                    if ($operation === 'propose') {
                        (new ActionProposalApiApi($httpClient, $client->configuration))->actionsApiProposeAsync(1, [
                            'actions' => [], 'evidence' => [], 'rationale' => 'Pause the ad.',
                            'idempotency_key' => 'retry-test', 'target_source' => 'human_supplied',
                        ])->wait();
                    } else {
                        $client->plans->launchPlansExecuteAsync(1, 'plan-test', ['review_version' => 'review-test'])->wait();
                    }
                    $this->fail('Expected an API exception.');
                } catch (ApiException $exception) {
                    $this->assertSame(503, $exception->getCode());
                    $this->assertSame($envelope, json_decode($exception->getResponseBody(), true, flags: JSON_THROW_ON_ERROR));
                    $this->assertNull($exception->getResponseObject());
                    $this->assertTrue(ProposalErrors::isRetryable($exception));
                    $this->assertSame(['1'], $exception->getResponseHeaders()['Retry-After']);
                }
            }
        }
    }

    public function test_raw_response_classifies_codes_even_when_generated_parsing_cannot_complete(): void
    {
        foreach (['workspace_lock_timeout', 'proposal_not_recorded'] as $code) {
            $body = json_encode(['error' => ['code' => $code, 'message' => 'Retry.']], JSON_THROW_ON_ERROR);
            $response = new Response(503, ['Retry-After' => '1'], $body);
            $this->assertTrue(ProposalErrors::isRetryable($response));
            $this->assertSame('1', $response->getHeaderLine('Retry-After'));
            $this->assertFalse(ProposalErrors::isRetryable(new Response(403, [], $body)));
        }
        foreach (['<html>Unavailable</html>', '{"message":"Unavailable"}', '{"error":{"code":"proposal_not_recorded"}}'] as $body) {
            $this->assertFalse(ProposalErrors::isRetryable(new Response(503, [], $body)));
        }
    }

    public function test_unrelated_errors_are_not_retryable_proposal_refusals(): void
    {
        $exception = new ApiException('Unavailable.', 503);
        $this->assertFalse(ProposalErrors::isRetryable($exception));
        $exception = new ApiException('Rejected.', 422);
        $exception->setResponseObject(new ProposalUnavailable(['error' => new ProposalUnavailableError(['code' => 'proposal_not_recorded', 'message' => 'Retry.'])]));
        $this->assertFalse(ProposalErrors::isRetryable($exception));
    }
}
