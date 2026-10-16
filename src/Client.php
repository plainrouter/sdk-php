<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Plainrouter\IntegerInputApis\GuardedActionDryRunApiApi;
use Plainrouter\IntegerInputApis\GuardedActionProposalApiApi;
use Plainrouter\IntegerInputApis\GuardedActionRuleApiApi;
use Plainrouter\IntegerInputApis\GuardedAdTestApiApi;
use Plainrouter\IntegerInputApis\GuardedCookieDeclarationApi;
use Plainrouter\IntegerInputApis\GuardedCreativeApi;
use Plainrouter\IntegerInputApis\GuardedLaunchPolicyApi;
use Plainrouter\IntegerInputApis\GuardedMetaInventoryExposureApi;
use Plainrouter\IntegerInputApis\GuardedOperationsApi;
use Plainrouter\IntegerInputApis\GuardedSandboxApi;
use Plainrouter\OpenAPI\Api\ActionDryRunApiApi;
use Plainrouter\OpenAPI\Api\ActionProposalApiApi;
use Plainrouter\OpenAPI\Api\ActionReadApiApi;
use Plainrouter\OpenAPI\Api\ActionRuleApiApi;
use Plainrouter\OpenAPI\Api\AdTestApiApi;
use Plainrouter\OpenAPI\Api\AgentContextApi;
use Plainrouter\OpenAPI\Api\CookieDeclarationApi;
use Plainrouter\OpenAPI\Api\CreativeApi;
use Plainrouter\OpenAPI\Api\DraftBatchFileImportApi;
use Plainrouter\OpenAPI\Api\DeploymentPlanApi;
use Plainrouter\OpenAPI\Api\LaunchPolicyApi;
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

    public readonly DraftBatchFileImportApi $fileImports;

    public readonly Events $events;

    public readonly MetaInventoryExposureApi $inventory;

    public readonly LaunchPolicyApi $launchPolicies;

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

        $httpClient = new JsonResponseClient($httpClient ?? new HttpClient(['timeout' => $timeout]), $accept, $this->configuration->getHost());

        $this->actions = new GuardedActionProposalApiApi($httpClient, $this->configuration);
        $this->actionDryRun = new GuardedActionDryRunApiApi($httpClient, $this->configuration);
        $this->actionReads = new ActionReads($httpClient, $this->configuration);
        $this->actionRules = new GuardedActionRuleApiApi($httpClient, $this->configuration);
        $this->adTests = new GuardedAdTestApiApi($httpClient, $this->configuration);
        $this->agentContext = new AgentContext($httpClient, $this->configuration);
        $this->cookieDeclarations = new GuardedCookieDeclarationApi($httpClient, $this->configuration);
        $this->creatives = new GuardedCreativeApi($httpClient, $this->configuration);
        $this->fileImports = new FileImports($httpClient, $this->configuration);
        $this->events = new Events($httpClient, $this->configuration);
        $this->launchPolicies = new GuardedLaunchPolicyApi($httpClient, $this->configuration);
        $this->inventory = new GuardedMetaInventoryExposureApi($httpClient, $this->configuration);
        $this->operations = new GuardedOperationsApi($httpClient, $this->configuration);
        $this->sandbox = new GuardedSandboxApi($httpClient, $this->configuration);
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
