<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedSandboxApi extends \Plainrouter\OpenAPI\Api\SandboxApi
{
    public function validateSandboxEventRequest($validate_sandbox_event_request, string $contentType = self::contentTypes['validateSandboxEvent'][0])
    {
        IntegerInputGuard::assertArguments('POST /sandbox/events', self::class, __FUNCTION__, func_get_args(), 'validate_sandbox_event_request', []);
        return parent::validateSandboxEventRequest(...func_get_args());
    }

    public function validateSandboxEventWithKeyRequest($validate_sandbox_event_request, string $contentType = self::contentTypes['validateSandboxEventWithKey'][0])
    {
        IntegerInputGuard::assertArguments('POST /sandbox/keyed-events', self::class, __FUNCTION__, func_get_args(), 'validate_sandbox_event_request', []);
        return parent::validateSandboxEventWithKeyRequest(...func_get_args());
    }
}
