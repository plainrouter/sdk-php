<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedDeploymentPlanApi extends \Plainrouter\OpenAPI\Api\DeploymentPlanApi
{
    public function launchPlansIndexRequest($workspace, $page = 1, $cursor = null, $per_page = 25, string $contentType = self::contentTypes['launchPlansIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/plans', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPlansIndexRequest(...func_get_args());
    }

    public function launchPlansStoreRequest($workspace, $store_deployment_plan_request, string $contentType = self::contentTypes['launchPlansStore'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/plans', self::class, __FUNCTION__, func_get_args(), 'store_deployment_plan_request', []);
        return parent::launchPlansStoreRequest(...func_get_args());
    }

    public function launchPlansShowRequest($workspace, $deployment_plan, $cursor = null, $limit = 5, string $contentType = self::contentTypes['launchPlansShow'][0])
    {
        IntegerInputGuard::assertArguments('GET /workspaces/{workspace}/admin/plans/{deployment_plan}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPlansShowRequest(...func_get_args());
    }

    public function launchPlansUpdateRequest($workspace, $deployment_plan, $update_deployment_plan_request = null, string $contentType = self::contentTypes['launchPlansUpdate'][0])
    {
        IntegerInputGuard::assertArguments('PATCH /workspaces/{workspace}/admin/plans/{deployment_plan}', self::class, __FUNCTION__, func_get_args(), 'update_deployment_plan_request', []);
        return parent::launchPlansUpdateRequest(...func_get_args());
    }

    public function launchPlansCopyRequest($workspace, $deployment_plan, string $contentType = self::contentTypes['launchPlansCopy'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/plans/{deployment_plan}/copy', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPlansCopyRequest(...func_get_args());
    }

    public function launchPlansValidateRequest($workspace, $deployment_plan, string $contentType = self::contentTypes['launchPlansValidate'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/plans/{deployment_plan}/validate', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::launchPlansValidateRequest(...func_get_args());
    }

    public function launchPlansExecuteRequest($workspace, $deployment_plan, $execute_deployment_plan_request, string $contentType = self::contentTypes['launchPlansExecute'][0])
    {
        IntegerInputGuard::assertArguments('POST /workspaces/{workspace}/admin/plans/{deployment_plan}/execute', self::class, __FUNCTION__, func_get_args(), 'execute_deployment_plan_request', []);
        return parent::launchPlansExecuteRequest(...func_get_args());
    }
}
