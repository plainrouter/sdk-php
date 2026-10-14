<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use Plainrouter\OpenAPI\Api\ActionReadApiApi;
use Plainrouter\OpenAPI\Model\ActionReceiptUnavailable;
use Plainrouter\OpenAPI\Model\ApiRouteNotFound;

/** Retains decision-receipt 404 status and body while decoding its documented union. */
final class ActionReads extends ActionReadApiApi
{
    private const ERROR_MODELS = [
        'receipt_not_available' => ActionReceiptUnavailable::class,
        'api_route_not_found' => ApiRouteNotFound::class,
    ];

    public function actionsApiDecisionReceiptWithHttpInfo($workspace, $action, $account_id = null, string $contentType = self::contentTypes['actionsApiDecisionReceipt'][0]): array
    {
        [$observer, $generated] = $this->receiptApi();
        return ErrorResponses::call($observer, fn (): array => $generated->actionsApiDecisionReceiptWithHttpInfo($workspace, $action, $account_id, $contentType), self::ERROR_MODELS);
    }

    public function actionsApiDecisionReceiptAsyncWithHttpInfo($workspace, $action, $account_id = null, string $contentType = self::contentTypes['actionsApiDecisionReceipt'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->receiptApi();
        return ErrorResponses::promise($observer, $generated->actionsApiDecisionReceiptAsyncWithHttpInfo($workspace, $action, $account_id, $contentType), self::ERROR_MODELS);
    }

    /** @return array{CopyResponseClient, ActionReadApiApi} */
    private function receiptApi(): array
    {
        $observer = new CopyResponseClient($this->client);
        return [$observer, new ActionReadApiApi($observer, $this->config, $this->headerSelector, $this->hostIndex)];
    }
}
