<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedEventApi extends \Plainrouter\OpenAPI\Api\EventApi
{
    public function createEventRequest($create_event_request, $idempotency_key = null, string $contentType = self::contentTypes['createEvent'][0])
    {
        IntegerInputGuard::assertArguments('POST /events', self::class, __FUNCTION__, func_get_args(), 'create_event_request', []);
        return parent::createEventRequest(...func_get_args());
    }
}
