<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\PlanCopyRead;
use Plainrouter\OpenAPI\Model\ProposalUnavailable;
use Plainrouter\OpenAPI\Model\WorkspaceLockTimeout;
use Plainrouter\OpenAPI\ObjectSerializer;
use Psr\Http\Message\ResponseInterface;

/** Copy's generated schema only names lock timeout; the facade also decodes proposal_not_recorded. */
final class Plans extends DeploymentPlanApi
{
    /** @return array{mixed, int, array<string, string[]>} */
    public function launchPlansCopyWithHttpInfo(mixed $workspace, mixed $deployment_plan, string $contentType = self::contentTypes['launchPlansCopy'][0]): array
    {
        $request = $this->launchPlansCopyRequest($workspace, $deployment_plan, $contentType);
        try {
            $response = $this->client->send($request, $this->createHttpClientOption());
        } catch (RequestException $exception) {
            $error = new ApiException(
                "[{$exception->getCode()}] {$exception->getMessage()}",
                (int) $exception->getCode(),
                $exception->getResponse()?->getHeaders(),
                $exception->getResponse() ? (string) $exception->getResponse()->getBody() : null,
            );
            if ($exception->getResponse() !== null && in_array($exception->getResponse()->getStatusCode(), [201, 401, 403, 404, 413, 422, 429, 500, 503], true)) {
                $error->setResponseObject(self::copyResponseObject($exception->getResponse(), decodeJson: false));
            }
            throw $error;
        } catch (ConnectException $exception) {
            throw new ApiException("[{$exception->getCode()}] {$exception->getMessage()}", (int) $exception->getCode(), null, null);
        }

        $status = $response->getStatusCode();
        if (($status < 200 || $status > 299) && ! in_array($status, [401, 403, 404, 413, 422, 429, 500, 503], true)) {
            throw new ApiException(sprintf('[%d] Error connecting to the API (%s)', $status, $request->getUri()), $status, $response->getHeaders(), (string) $response->getBody());
        }

        return [self::copyResponseObject($response), $status, $response->getHeaders()];
    }

    public function launchPlansCopyAsyncWithHttpInfo(mixed $workspace, mixed $deployment_plan, string $contentType = self::contentTypes['launchPlansCopy'][0]): PromiseInterface
    {
        $request = $this->launchPlansCopyRequest($workspace, $deployment_plan, $contentType);

        return $this->client->sendAsync($request, $this->createHttpClientOption())->then(
            function (ResponseInterface $response): array {
                $body = json_decode((string) $response->getBody());
                $knownCode = in_array($body->error->code ?? null, ['workspace_lock_timeout', 'proposal_not_recorded'], true);
                $type = $response->getStatusCode() === 503 && $knownCode ? self::copyErrorType($body) : PlanCopyRead::class;

                return [ObjectSerializer::deserialize($body, $type, []), $response->getStatusCode(), $response->getHeaders()];
            },
            function (RequestException $exception): never {
                $response = $exception->getResponse();
                $error = new ApiException(
                    sprintf('[%d] Error connecting to the API (%s)', $response->getStatusCode(), $exception->getRequest()->getUri()),
                    $response->getStatusCode(), $response->getHeaders(), (string) $response->getBody(),
                );
                if (ProposalErrors::isRetryable($error)) {
                    $error->setResponseObject(self::copyResponseObject($response));
                }
                throw $error;
            },
        );
    }

    private static function copyErrorType(mixed $body): string
    {
        return ($body->error->code ?? null) === 'proposal_not_recorded' ? ProposalUnavailable::class : WorkspaceLockTimeout::class;
    }

    private static function copyResponseObject(ResponseInterface $response, bool $decodeJson = true): mixed
    {
        $body = (string) $response->getBody();
        if ($decodeJson) {
            try {
                $body = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ApiException('Error JSON decoding server response', $response->getStatusCode(), $response->getHeaders(), $body);
            }
        }
        $type = match ($response->getStatusCode()) {
            401, 413, 429, 500 => \Plainrouter\OpenAPI\Model\ErrorMessage::class,
            403 => \Plainrouter\OpenAPI\Model\LaunchPlansCopy403Response::class,
            404 => \Plainrouter\OpenAPI\Model\LaunchPlansCopy404Response::class,
            422 => \Plainrouter\OpenAPI\Model\PlanCopyRejected::class,
            503 => self::copyErrorType(is_string($body) ? json_decode($body) : $body),
            default => PlanCopyRead::class,
        };

        return ObjectSerializer::deserialize($body, $type, $response->getHeaders());
    }
}
