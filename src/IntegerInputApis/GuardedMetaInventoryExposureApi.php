<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedMetaInventoryExposureApi extends \Plainrouter\OpenAPI\Api\MetaInventoryExposureApi
{
    public function getAccountInventoryRequest($workspace, $account_id, $level = null, $effective_status = null, $campaign_id = null, $adset_id = null, $after_id = null, $limit = 25, string $contentType = self::contentTypes['getAccountInventory'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/ad-accounts/{account_id}/inventory', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::getAccountInventoryRequest(...func_get_args());
    }

    public function getInventoryMetricsRequest($workspace, $account_id, $start_date = null, $end_date = null, $campaign_id = null, $adset_id = null, $ad_id = null, $include_changes = null, $limit = null, $cursor = null, string $contentType = self::contentTypes['getInventoryMetrics'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/ad-accounts/{account_id}/inventory/metrics', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::getInventoryMetricsRequest(...func_get_args());
    }
}
