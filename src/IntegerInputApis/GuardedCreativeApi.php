<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedCreativeApi extends \Plainrouter\OpenAPI\Api\CreativeApi
{
    public function launchCreativesIndexRequest($workspace, $page = 1, $cursor = null, $per_page = 25, string $contentType = self::contentTypes['launchCreativesIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/creatives', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchCreativesIndexRequest(...func_get_args());
    }

    public function storeCreativeRequest($workspace, $file, $tags = null, $source_ref = null, string $contentType = self::contentTypes['storeCreative'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/creatives', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::storeCreativeRequest(...func_get_args());
    }

    public function storeCreativeFromUrlRequest($workspace, $store_creative_from_url_request, string $contentType = self::contentTypes['storeCreativeFromUrl'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/creatives/from-url', self::class, __FUNCTION__, func_get_args(), 'store_creative_from_url_request', []);
        return parent::storeCreativeFromUrlRequest(...func_get_args());
    }

    public function launchCreativesStatusRequest($workspace, $creative, $update_creative_status_request, string $contentType = self::contentTypes['launchCreativesStatus'][0])
    {
        IntegerInputGuard::assertArguments('PATCH /workspaces/{workspace}/admin/creatives/{creative}/status', self::class, __FUNCTION__, func_get_args(), 'update_creative_status_request', []);
        return parent::launchCreativesStatusRequest(...func_get_args());
    }

    public function launchCreativesShowRequest($workspace, $creative, string $contentType = self::contentTypes['launchCreativesShow'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/creatives/{creative}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchCreativesShowRequest(...func_get_args());
    }
}
