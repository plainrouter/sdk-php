<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedActionProposalApiApi extends \Plainrouter\OpenAPI\Api\ActionProposalApiApi
{
    public function actionsApiProposeRequest($workspace, $action_proposal_input, string $contentType = self::contentTypes['actionsApiPropose'][0])
    {
        IntegerInputGuard::assertArguments('POST /agent/workspaces/{workspace}/actions', self::class, __FUNCTION__, func_get_args(), 'action_proposal_input', []);
        return parent::actionsApiProposeRequest(...func_get_args());
    }
}
