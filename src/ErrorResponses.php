<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use InvalidArgumentException;
use Plainrouter\OpenAPI\ApiException;
use Plainrouter\OpenAPI\Model\ModelInterface;
use Plainrouter\OpenAPI\ObjectSerializer;

/** @internal Decodes documented 404 unions without changing generated transport outcomes. */
final class ErrorResponses
{
    /**
     * @param callable(): array $send
     * @param array<string, class-string<ModelInterface>> $models
     */
    public static function call(CopyResponseClient $observer, callable $send, array $models): array
    {
        try {
            return self::result($send(), $observer, $models);
        } catch (ApiException $error) {
            self::exception($error, $models);
            throw $error;
        } catch (InvalidArgumentException $error) {
            $response = $observer->response;
            if ($response === null || $response->getStatusCode() !== 404) {
                throw $error;
            }
            $body = (string) $response->getBody();
            $object = self::decode($response->getStatusCode(), $body, $response->getHeaders(), $models);
            if ($object === null) {
                throw $error;
            }
            if ($observer->failure === null) {
                return [$object, $response->getStatusCode(), $response->getHeaders()];
            }
            $failure = $observer->failure;
            $recovered = new ApiException("[{$failure->getCode()}] {$failure->getMessage()}", (int) $failure->getCode(), $response->getHeaders(), $body);
            $recovered->setResponseObject($object);
            throw $recovered;
        }
    }

    /** @param array<string, class-string<ModelInterface>> $models */
    public static function promise(CopyResponseClient $observer, PromiseInterface $promise, array $models): PromiseInterface
    {
        return $promise->then(
            fn (array $result): array => self::result($result, $observer, $models),
            function (mixed $error) use ($models): RejectedPromise {
                if ($error instanceof ApiException) {
                    self::exception($error, $models);
                }
                return new RejectedPromise($error);
            },
        );
    }

    /** @param array<string, class-string<ModelInterface>> $models */
    private static function result(array $result, CopyResponseClient $observer, array $models): array
    {
        $response = $observer->response;
        if ($response !== null && $response->getStatusCode() === 404) {
            $object = self::decode($response->getStatusCode(), (string) $response->getBody(), $response->getHeaders(), $models);
            if ($object !== null) {
                $result[0] = $object;
            }
        }
        return $result;
    }

    /** @param array<string, class-string<ModelInterface>> $models */
    private static function exception(ApiException $error, array $models): void
    {
        $object = self::decode($error->getCode(), $error->getResponseBody(), $error->getResponseHeaders(), $models);
        if ($object !== null) {
            $error->setResponseObject($object);
        }
    }

    /**
     * @param array<string, string[]>|null $headers
     * @param array<string, class-string<ModelInterface>> $models
     */
    private static function decode(?int $status, mixed $body, ?array $headers, array $models): ?ModelInterface
    {
        if ($status !== 404 || ! is_string($body)) {
            return null;
        }
        $data = json_decode($body, true);
        $error = is_array($data) ? ($data['error'] ?? null) : null;
        if (! is_array($error) || ! is_string($error['code'] ?? null) || ! is_string($error['message'] ?? null)) {
            return null;
        }
        $model = $models[$error['code']] ?? null;
        return $model === null ? null : ObjectSerializer::deserialize($body, $model, $headers ?? []);
    }
}
