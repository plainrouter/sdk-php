<?php

declare(strict_types=1);

namespace Plainrouter;

use Plainrouter\OpenAPI\ApiException;
use Psr\Http\Message\ResponseInterface;

final class ProposalErrors
{
    /** Classify a known 503 envelope by its code, independently of its generated model class. */
    public static function isRetryable(ApiException|ResponseInterface $exception): bool
    {
        if (($exception instanceof ResponseInterface ? $exception->getStatusCode() : $exception->getCode()) !== 503) {
            return false;
        }

        $body = $exception instanceof ResponseInterface ? (string) $exception->getBody() : $exception->getResponseBody();
        $envelope = is_string($body) ? json_decode($body, true) : null;
        if (! is_array($envelope) && $exception instanceof ApiException && $exception->getResponseObject() !== null) {
            $envelope = json_decode(json_encode($exception->getResponseObject()), true);
        }

        return is_array($envelope)
            && is_array($envelope['error'] ?? null)
            && is_string($envelope['error']['message'] ?? null)
            && in_array($envelope['error']['code'] ?? null, ['proposal_not_recorded', 'workspace_lock_timeout'], true);
    }
}
