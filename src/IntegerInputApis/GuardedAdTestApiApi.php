<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedAdTestApiApi extends \Plainrouter\OpenAPI\Api\AdTestApiApi
{
    public function actionsTestsApiIndexRequest($workspace, $page = null, $per_page = null, string $contentType = self::contentTypes['actionsTestsApiIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/tests', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsTestsApiIndexRequest(...func_get_args());
    }

    public function actionsTestsApiStoreRequest($workspace, $actions_tests_api_store_request, string $contentType = self::contentTypes['actionsTestsApiStore'][0])
    {
        IntegerInputGuard::assertArguments('POST /agent/workspaces/{workspace}/tests', self::class, __FUNCTION__, func_get_args(), 'actions_tests_api_store_request', []);
        return parent::actionsTestsApiStoreRequest(...func_get_args());
    }

    public function actionsTestsApiShowRequest($workspace, $ad_test, string $contentType = self::contentTypes['actionsTestsApiShow'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/tests/{ad_test}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsTestsApiShowRequest(...func_get_args());
    }
}
