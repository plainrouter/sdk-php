<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use Plainrouter\IntegerInputApis\GuardedMetaInventoryExposureApi;
use Plainrouter\OpenAPI\Model\ApiRouteNotFound;
use Plainrouter\OpenAPI\Model\ErrorMessage;
use Plainrouter\OpenAPI\Model\GetAccountInventory404Response;

/** Decodes the documented workspace 404 union on the curated inventory client. */
final class Inventory extends GuardedMetaInventoryExposureApi
{
    private const ERROR_MODELS = [
        'api_route_not_found' => ApiRouteNotFound::class,
    ];

    public function getAccountInventoryWithHttpInfo($workspace, $account_id, $level = null, $effective_status = null, $campaign_id = null, $adset_id = null, $after_id = null, $limit = 25, string $contentType = self::contentTypes['getAccountInventory'][0]): array
    {
        [$observer, $generated] = $this->inventoryApi();
        return ErrorResponses::call($observer, fn (): array => $generated->getAccountInventoryWithHttpInfo($workspace, $account_id, $level, $effective_status, $campaign_id, $adset_id, $after_id, $limit, $contentType), self::ERROR_MODELS + ['' => GetAccountInventory404Response::class]);
    }

    public function getAccountInventoryAsyncWithHttpInfo($workspace, $account_id, $level = null, $effective_status = null, $campaign_id = null, $adset_id = null, $after_id = null, $limit = 25, string $contentType = self::contentTypes['getAccountInventory'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->inventoryApi();
        return ErrorResponses::promise($observer, $generated->getAccountInventoryAsyncWithHttpInfo($workspace, $account_id, $level, $effective_status, $campaign_id, $adset_id, $after_id, $limit, $contentType), self::ERROR_MODELS + ['' => GetAccountInventory404Response::class]);
    }

    public function getInventoryMetricsWithHttpInfo($workspace, $account_id, $start_date = null, $end_date = null, $campaign_id = null, $adset_id = null, $ad_id = null, $include_changes = null, $limit = null, $cursor = null, string $contentType = self::contentTypes['getInventoryMetrics'][0]): array
    {
        [$observer, $generated] = $this->inventoryApi();
        return ErrorResponses::call($observer, fn (): array => $generated->getInventoryMetricsWithHttpInfo($workspace, $account_id, $start_date, $end_date, $campaign_id, $adset_id, $ad_id, $include_changes, $limit, $cursor, $contentType), self::ERROR_MODELS + ['' => ErrorMessage::class]);
    }

    public function getInventoryMetricsAsyncWithHttpInfo($workspace, $account_id, $start_date = null, $end_date = null, $campaign_id = null, $adset_id = null, $ad_id = null, $include_changes = null, $limit = null, $cursor = null, string $contentType = self::contentTypes['getInventoryMetrics'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->inventoryApi();
        return ErrorResponses::promise($observer, $generated->getInventoryMetricsAsyncWithHttpInfo($workspace, $account_id, $start_date, $end_date, $campaign_id, $adset_id, $ad_id, $include_changes, $limit, $cursor, $contentType), self::ERROR_MODELS + ['' => ErrorMessage::class]);
    }

    /** @return array{CopyResponseClient, GuardedMetaInventoryExposureApi} */
    private function inventoryApi(): array
    {
        $observer = new CopyResponseClient($this->client);
        return [$observer, new GuardedMetaInventoryExposureApi($observer, $this->config, $this->headerSelector, $this->hostIndex)];
    }
}
