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

    public function test_unrelated_errors_are_not_retryable_proposal_refusals(): void
    {
        $exception = new ApiException('Unavailable.', 503);
        $this->assertFalse(ProposalErrors::isRetryable($exception));
        $exception = new ApiException('Rejected.', 422);
        $exception->setResponseObject(new ProposalUnavailable(['error' => new ProposalUnavailableError(['code' => 'proposal_not_recorded', 'message' => 'Retry.'])]));
        $this->assertFalse(ProposalErrors::isRetryable($exception));
    }
}
