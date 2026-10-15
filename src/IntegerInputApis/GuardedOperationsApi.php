<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedOperationsApi extends \Plainrouter\OpenAPI\Api\OperationsApi
{
    public function listEventsRequest($per_page = 25, string $contentType = self::contentTypes['listEvents'][0])
    {
        IntegerInputGuard::assertArguments('GET /dashboard/events', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::listEventsRequest(...func_get_args());
    }

    public function listEventsByCursorRequest($per_page = 25, $cursor = null, string $contentType = self::contentTypes['listEventsByCursor'][0])
    {
        IntegerInputGuard::assertArguments('GET /dashboard/events/cursor', self::class, __FUNCTION__, func_get_args(), null, []);
        return parent::listEventsByCursorRequest(...func_get_args());
    }

    public function setDestinationTestModeRequest($destination, $set_destination_test_mode_request, string $contentType = self::contentTypes['setDestinationTestMode'][0])
    {
        IntegerInputGuard::assertArguments('PATCH /destinations/{destination}/test-mode', self::class, __FUNCTION__, func_get_args(), 'set_destination_test_mode_request', []);
        return parent::setDestinationTestModeRequest(...func_get_args());
    }

    public function sendTestPurchaseRequest($destination, $send_test_purchase_request = null, string $contentType = self::contentTypes['sendTestPurchase'][0])
    {
        IntegerInputGuard::assertArguments('POST /destinations/{destination}/test-purchase', self::class, __FUNCTION__, func_get_args(), 'send_test_purchase_request', []);
        return parent::sendTestPurchaseRequest(...func_get_args());
    }

    public function replayDeliveriesRequest($replay_deliveries_request = null, string $contentType = self::contentTypes['replayDeliveries'][0])
    {
        IntegerInputGuard::assertArguments('POST /deliveries/replay', self::class, __FUNCTION__, func_get_args(), 'replay_deliveries_request', []);
        return parent::replayDeliveriesRequest(...func_get_args());
    }

    public function deleteUserDataRequest($delete_user_data_request, string $contentType = self::contentTypes['deleteUserData'][0])
    {
        IntegerInputGuard::assertArguments('DELETE /user-data', self::class, __FUNCTION__, func_get_args(), 'delete_user_data_request', []);
        return parent::deleteUserDataRequest(...func_get_args());
    }
}
