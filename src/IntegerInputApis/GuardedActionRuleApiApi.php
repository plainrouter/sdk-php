<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedActionRuleApiApi extends \Plainrouter\OpenAPI\Api\ActionRuleApiApi
{
    public function actionsRulesIndexRequest($workspace, $page = null, $account_id = null, $platform_ad_account_id = null, $cursor = null, $per_page = 25, string $contentType = self::contentTypes['actionsRulesIndex'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/action-rules', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsRulesIndexRequest(...func_get_args());
    }

    public function actionsRulesStoreRequest($workspace, $actions_rules_store_request, string $contentType = self::contentTypes['actionsRulesStore'][0])
    {
        IntegerInputGuard::assertArguments('POST /agent/workspaces/{workspace}/action-rules', self::class, __FUNCTION__, func_get_args(), 'actions_rules_store_request', []);
        return parent::actionsRulesStoreRequest(...func_get_args());
    }

    public function actionsRulesShowRequest($workspace, $action_rule, $account_id = null, string $contentType = self::contentTypes['actionsRulesShow'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/workspaces/{workspace}/action-rules/{action_rule}', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::actionsRulesShowRequest(...func_get_args());
    }

    public function actionsRulesUpdateRequest($workspace, $action_rule, $actions_rules_update_request = null, string $contentType = self::contentTypes['actionsRulesUpdate'][0])
    {
        IntegerInputGuard::assertArguments('PATCH /agent/workspaces/{workspace}/action-rules/{action_rule}', self::class, __FUNCTION__, func_get_args(), 'actions_rules_update_request', []);
        return parent::actionsRulesUpdateRequest(...func_get_args());
    }
}
