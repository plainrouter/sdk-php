<?php

declare(strict_types=1);

namespace Plainrouter;

use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ProposalUnavailable;

final class ProposalErrors
{
    /** Known 503 proposal refusals may be retried after the Retry-After header. */
    public static function isRetryable(ApiException $exception): bool
    {
        $response = $exception->getResponseObject();

        return $exception->getCode() === 503
            && $response instanceof ProposalUnavailable
            && in_array($response->getError()?->getCode(), ['proposal_not_recorded', 'workspace_lock_timeout'], true);
    }
}
