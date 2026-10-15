<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedActionReadApiApi extends \Plainrouter\OpenAPI\Api\ActionReadApiApi
{
    public function actionsApiIndexRequest($workspace, $status = null, $page = null, $account_id = null, $cursor = null, $limit = 25, string $contentType = self::contentTypes['actionsApiIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/actions', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsApiIndexRequest(...func_get_args());
    }

    public function actionsApiPolicyRequest($workspace, $account_id = null, string $contentType = self::contentTypes['actionsApiPolicy'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/actions/policy', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsApiPolicyRequest(...func_get_args());
    }

    public function actionsApiDecisionReceiptRequest($workspace, $action, $account_id = null, string $contentType = self::contentTypes['actionsApiDecisionReceipt'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/actions/{action}/decision-receipt', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsApiDecisionReceiptRequest(...func_get_args());
    }

    public function actionsApiShowRequest($workspace, $action, $account_id = null, string $contentType = self::contentTypes['actionsApiShow'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/actions/{action}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsApiShowRequest(...func_get_args());
    }

    public function actionsApiBatchRequest($workspace, $action_batch, $account_id = null, $cursor = null, $limit = 25, string $contentType = self::contentTypes['actionsApiBatch'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/action-batches/{actionBatch}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsApiBatchRequest(...func_get_args());
    }
}
