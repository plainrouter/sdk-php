<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedActionDryRunApiApi extends \Plainrouter\OpenAPI\Api\ActionDryRunApiApi
{
    public function actionsApiDryRunRequest($workspace, $action_proposal_input, string $contentType = self::contentTypes['actionsApiDryRun'][0])
    {
        IntegerInputGuard::assertArguments('POST /agent/workspaces/{workspace}/actions/dry-run', self::class, __FUNCTION__, func_get_args(), 'action_proposal_input', []);
        return parent::actionsApiDryRunRequest(...func_get_args());
    }
}
