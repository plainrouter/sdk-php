<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use Plainrouter\IntegerInputApis\GuardedAgentContextApi;
use Plainrouter\OpenAPI\Model\ApiRouteNotFound;

/** Decodes the documented workspace 404 on the curated agent-context client. */
final class AgentContext extends GuardedAgentContextApi
{
    private const ERROR_MODELS = [
        'api_route_not_found' => ApiRouteNotFound::class,
    ];

    public function agentContextWithHttpInfo($account_id = null, $workspace_id = null, string $contentType = self::contentTypes['agentContext'][0]): array
    {
        [$observer, $generated] = $this->contextApi();
        return ErrorResponses::call($observer, fn (): array => $generated->agentContextWithHttpInfo($account_id, $workspace_id, $contentType), self::ERROR_MODELS);
    }

    public function agentContextAsyncWithHttpInfo($account_id = null, $workspace_id = null, string $contentType = self::contentTypes['agentContext'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->contextApi();
        return ErrorResponses::promise($observer, $generated->agentContextAsyncWithHttpInfo($account_id, $workspace_id, $contentType), self::ERROR_MODELS);
    }

    /** @return array{CopyResponseClient, GuardedAgentContextApi} */
    private function contextApi(): array
    {
        $observer = new CopyResponseClient($this->client);
        return [$observer, new GuardedAgentContextApi($observer, $this->config, $this->headerSelector, $this->hostIndex)];
    }
}
