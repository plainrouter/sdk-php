<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedLaunchPolicyApi extends \Plainrouter\OpenAPI\Api\LaunchPolicyApi
{
    public function launchPoliciesIndexRequest($workspace, $page = null, $per_page = null, string $contentType = self::contentTypes['launchPoliciesIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/policies', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPoliciesIndexRequest(...func_get_args());
    }

    public function launchPoliciesStoreRequest($workspace, $store_launch_policy_request, string $contentType = self::contentTypes['launchPoliciesStore'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/policies', self::class, __FUNCTION__, func_get_args(), 'store_launch_policy_request', []);
        return parent::launchPoliciesStoreRequest(...func_get_args());
    }

    public function launchPoliciesRetireRequest($workspace, $launch_policy, string $contentType = self::contentTypes['launchPoliciesRetire'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/policies/{launch_policy}/retire', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPoliciesRetireRequest(...func_get_args());
    }
}
