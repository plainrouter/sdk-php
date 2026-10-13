<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use InvalidArgumentException;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ProposalUnavailable;
use Plainrouter\OpenAPI\Model\WorkspaceLockTimeout;
use Plainrouter\OpenAPI\ObjectSerializer;

/** Delegates copy to the generated class, only decoding the two known retry envelopes. */
final class Plans extends DeploymentPlanApi
{
    /** @return array{mixed, int, array<string, string[]>} */
    public function launchPlansCopyWithHttpInfo(mixed $workspace, mixed $deployment_plan, string $contentType = self::contentTypes['launchPlansCopy'][0]): array
    {
        [$observer, $generated] = $this->copyApi();
        try {
            return $generated->launchPlansCopyWithHttpInfo($workspace, $deployment_plan, $contentType);
        } catch (ApiException $error) {
            if (ProposalErrors::isRetryable($error)) {
                $error->setResponseObject(self::retryObject($error->getResponseBody(), $error->getResponseHeaders()));
            }
            throw $error;
        } catch (InvalidArgumentException $error) {
            $response = $observer->response;
            if ($response === null || ! ProposalErrors::isRetryable($response)) {
                throw $error;
            }
            $body = (string) $response->getBody();
            $object = self::retryObject($body, $response->getHeaders());
            if ($observer->failure === null) {
                return [$object, $response->getStatusCode(), $response->getHeaders()];
            }
            $failure = $observer->failure;
            $recovered = new ApiException("[{$failure->getCode()}] {$failure->getMessage()}", (int) $failure->getCode(), $response->getHeaders(), $body);
            $recovered->setResponseObject($object);
            throw $recovered;
        }
    }

    public function launchPlansCopyAsyncWithHttpInfo(mixed $workspace, mixed $deployment_plan, string $contentType = self::contentTypes['launchPlansCopy'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->copyApi();
        return $generated->launchPlansCopyAsyncWithHttpInfo($workspace, $deployment_plan, $contentType)->then(
            function (array $result) use ($observer): array {
                $response = $observer->response;
                if ($response !== null && ProposalErrors::isRetryable($response)) {
                    $result[0] = self::retryObject((string) $response->getBody(), $response->getHeaders());
                }
                return $result;
            },
            function (mixed $error): RejectedPromise {
                if ($error instanceof ApiException && ProposalErrors::isRetryable($error)) {
                    $error->setResponseObject(self::retryObject($error->getResponseBody(), $error->getResponseHeaders()));
                }
                return new RejectedPromise($error);
            },
        );
    }

    /** @return array{CopyResponseClient, DeploymentPlanApi} */
    private function copyApi(): array
    {
        $observer = new CopyResponseClient($this->client);
        return [$observer, new DeploymentPlanApi($observer, $this->config, $this->headerSelector, $this->hostIndex)];
    }

    /** @param array<string, string[]>|null $headers */
    private static function retryObject(string $body, ?array $headers): ProposalUnavailable|WorkspaceLockTimeout
    {
        $code = json_decode($body, true)['error']['code'];
        $type = $code === 'proposal_not_recorded' ? ProposalUnavailable::class : WorkspaceLockTimeout::class;
        return ObjectSerializer::deserialize($body, $type, $headers ?? []);
    }
}
