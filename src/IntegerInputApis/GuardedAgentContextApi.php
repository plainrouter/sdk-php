<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedAgentContextApi extends \Plainrouter\OpenAPI\Api\AgentContextApi
{
    public function agentContextRequest($account_id = null, $workspace_id = null, string $contentType = self::contentTypes['agentContext'][0])
    {
        IntegerInputGuard::assertArguments('GET /agent/context', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::agentContextRequest(...func_get_args());
    }
}
