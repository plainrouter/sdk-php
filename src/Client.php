<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\Api\OperationsApi;
use Plainrouter\OpenAPI\Api\SandboxApi;
use Plainrouter\OpenAPI\Configuration;

/**
 * Compact entry point for the Plainrouter API.
 *
 * Events, operations, sandbox, and plans expose the generated service groups; every
 * generated model and operation stays in the separate Plainrouter\OpenAPI
 * namespace.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://plainrouter.com/api/v1';

    public readonly Events $events;

    public readonly OperationsApi $operations;

    public readonly SandboxApi $sandbox;

    public readonly DeploymentPlanApi $plans;

    public readonly Configuration $configuration;

    /**
     * @param  string|null  $token  Bearer token required by the operation, or null for the zero-auth sandbox.
     * @param  float  $timeout  Request timeout in seconds; ignored when $httpClient is supplied.
     */
    public function __construct(
        ?string $token = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 30.0,
        ?string $userAgent = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->configuration = (new Configuration)
            ->setHost(self::validatedBaseUrl($baseUrl))
            ->setUserAgent($userAgent ?? 'plainrouter-php/'.Version::SDK);

        if ($token !== null && $token !== '') {
            $this->configuration->setAccessToken($token);
        }

        $httpClient ??= new HttpClient(['timeout' => $timeout]);

        $this->events = new Events($httpClient, $this->configuration);
        $this->operations = new OperationsApi($httpClient, $this->configuration);
        $this->sandbox = new SandboxApi($httpClient, $this->configuration);
        $this->plans = new DeploymentPlanApi($httpClient, $this->configuration);
    }

    private static function validatedBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);

        if (
            $parts === false
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException('baseUrl must be an absolute HTTP(S) URL without a query or fragment');
        }

        return rtrim($baseUrl, '/');
    }
}
