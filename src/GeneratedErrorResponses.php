<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\ClientInterface;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ModelInterface;
use Plainrouter\OpenAPI\ObjectSerializer;

/** @internal Applies generated operation response models to async API exceptions. */
final class GeneratedErrorResponses
{
    /** @param array<int, class-string<ModelInterface>> $models */
    public static function apply(ApiException $error, ClientInterface $client, array $models): void
    {
        if ($client instanceof CopyResponseClient && in_array($error->getCode(), [404, 503], true)) {
            // Leave the curated 404 unions and copy's 503 retry recovery unchanged.
            return;
        }

        $model = $models[$error->getCode()] ?? null;
        if ($model === null) {
            return;
        }

        $error->setResponseObject(ObjectSerializer::deserialize(
            $error->getResponseBody(),
            $model,
            $error->getResponseHeaders() ?? [],
        ));
    }
}
