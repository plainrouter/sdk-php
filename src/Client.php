<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Plainrouter\OpenAPI\Api\ActionDryRunApiApi;
use Plainrouter\OpenAPI\Api\ActionProposalApiApi;
use Plainrouter\OpenAPI\Api\ActionReadApiApi;
use Plainrouter\OpenAPI\Api\ActionRuleApiApi;
use Plainrouter\OpenAPI\Api\AdTestApiApi;
use Plainrouter\OpenAPI\Api\AgentContextApi;
use Plainrouter\OpenAPI\Api\CookieDeclarationApi;
use Plainrouter\OpenAPI\Api\CreativeApi;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\Api\MetaInventoryExposureApi;
use Plainrouter\OpenAPI\Api\OperationsApi;
use Plainrouter\OpenAPI\Api\SandboxApi;
use Plainrouter\OpenAPI\Configuration;

/**
 * Compact entry point for the Plainrouter API.
 *
 * Each service property exposes a generated operation group through the shared
 * JSON and redirect policy; generated models and namespace classes remain in
 * Plainrouter\OpenAPI.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://plainrouter.com/api/v1';

    public readonly ActionProposalApiApi $actions;

    public readonly ActionDryRunApiApi $actionDryRun;

    public readonly ActionReadApiApi $actionReads;

    public readonly ActionRuleApiApi $actionRules;

    public readonly AdTestApiApi $adTests;

    public readonly AgentContextApi $agentContext;

    public readonly CookieDeclarationApi $cookieDeclarations;

    public readonly CreativeApi $creatives;

    public readonly Events $events;

    public readonly MetaInventoryExposureApi $inventory;

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
        ?string $accept = null,
    ) {
        $this->configuration = (new Configuration)
            ->setHost(self::validatedBaseUrl($baseUrl))
            ->setUserAgent($userAgent ?? 'plainrouter-php/'.Version::SDK);

        if ($token !== null && $token !== '') {
            $this->configuration->setAccessToken($token);
        }

        $httpClient = new JsonResponseClient($httpClient ?? new HttpClient(['timeout' => $timeout]), $accept);

        $this->actions = new ActionProposalApiApi($httpClient, $this->configuration);
        $this->actionDryRun = new ActionDryRunApiApi($httpClient, $this->configuration);
        $this->actionReads = new ActionReadApiApi($httpClient, $this->configuration);
        $this->actionRules = new ActionRuleApiApi($httpClient, $this->configuration);
        $this->adTests = new AdTestApiApi($httpClient, $this->configuration);
        $this->agentContext = new AgentContextApi($httpClient, $this->configuration);
        $this->cookieDeclarations = new CookieDeclarationApi($httpClient, $this->configuration);
        $this->creatives = new CreativeApi($httpClient, $this->configuration);
        $this->events = new Events($httpClient, $this->configuration);
        $this->inventory = new MetaInventoryExposureApi($httpClient, $this->configuration);
        $this->operations = new OperationsApi($httpClient, $this->configuration);
        $this->sandbox = new SandboxApi($httpClient, $this->configuration);
        $this->plans = new Plans($httpClient, $this->configuration);
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
