<?php

declare(strict_types=1);

namespace Plainrouter;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use RuntimeException;

/** Reject boolean values for integer inputs described by the vendored contract. */
final class IntegerInputGuard
{
    /** @var array<string, array{operationId: string, body: list<list<string>>, query: list<string>, path: list<string>}|null */
    private static ?array $operations = null;

    /**
     * Reject boolean values from generated API method arguments before PHP query serialization can cast them.
     *
     * @param array<int|string, mixed> $arguments
     * @param list<array{0: string, 1: string}> $bodyFieldArguments
     */
    public static function assertArguments(
        string $operation,
        string $apiClass,
        string $method,
        array $arguments,
        ?string $bodyArgument = null,
        array $bodyFieldArguments = [],
    ): void
    {
        $fields = self::operations()[$operation] ?? null;
        if ($fields === null) {
            throw new RuntimeException("Missing integer input metadata for {$operation}");
        }

        $parentClass = get_parent_class($apiClass);
        if ($parentClass === false) {
            throw new RuntimeException("Cannot resolve generated API class for {$operation}");
        }

        $parameters = (new ReflectionMethod($parentClass, $method))->getParameters();
        $parameterIndexes = [];
        foreach ($parameters as $index => $parameter) {
            $parameterIndexes[$parameter->getName()] = [$index, $parameter];
        }

        foreach ([...$fields['path'], ...$fields['query']] as $field) {
            if (! isset($parameterIndexes[$field])) {
                throw new RuntimeException("Generated PHP argument {$field} was not found for {$operation}");
            }

            [$index, $parameter] = $parameterIndexes[$field];
            $value = self::argumentValue($field, $index, $parameter, $arguments);
            self::assertValueHasNoBoolean($value, $field);
        }

        if ($bodyArgument !== null) {
            if (! isset($parameterIndexes[$bodyArgument])) {
                throw new RuntimeException("Generated PHP body argument {$bodyArgument} was not found for {$operation}");
            }
            [$index, $parameter] = $parameterIndexes[$bodyArgument];
            $body = self::argumentValue($bodyArgument, $index, $parameter, $arguments);
            $body = \Plainrouter\OpenAPI\ObjectSerializer::sanitizeForSerialization($body);
            if (is_object($body)) {
                $body = json_decode(json_encode($body, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            }
            foreach ($fields['body'] as $path) {
                self::assertPathHasNoBoolean($body, $path, []);
            }
        }

        foreach ($bodyFieldArguments as [$field, $argument]) {
            if (! isset($parameterIndexes[$argument])) {
                throw new RuntimeException("Generated PHP body argument {$argument} was not found for {$operation}");
            }
            [$index, $parameter] = $parameterIndexes[$argument];
            if (is_bool(self::argumentValue($argument, $index, $parameter, $arguments))) {
                self::rejectBoolean($field);
            }
        }
    }

    /** Reject booleans in JSON request fields before the wrapped transport sends the request. */
    public static function assertRequest(RequestInterface $request, string $baseUrl): void
    {
        $operation = self::operationFor($request, $baseUrl);
        if ($operation === null) {
            return;
        }

        $fields = self::operations()[$operation];
        if ($fields['body'] === []) {
            return;
        }

        $stream = $request->getBody();
        if (! $stream->isSeekable()) {
            return;
        }

        $position = $stream->tell();
        try {
            $body = $stream->getContents();
        } finally {
            $stream->seek($position);
        }

        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        foreach ($fields['body'] as $path) {
            self::assertPathHasNoBoolean($payload, $path, []);
        }
    }

    /** @return array<string, array{operationId: string, body: list<list<string>>, query: list<string>, path: list<string>}> */
    private static function operations(): array
    {
        if (self::$operations === null) {
            $path = __DIR__.'/integer-input-fields.json';
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Cannot read generated integer input metadata at {$path}");
            }

            $operations = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($operations)) {
                throw new RuntimeException('Generated integer input metadata must be a JSON object');
            }
            self::$operations = $operations;
        }

        return self::$operations;
    }

    private static function operationFor(RequestInterface $request, string $baseUrl): ?string
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();
        $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '';
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $basePath.'/')) {
                $path = substr($path, strlen($basePath));
            } else {
                return null;
            }
        }

        foreach (self::operations() as $operation => $_fields) {
            [$operationMethod, $template] = explode(' ', $operation, 2);
            if ($method !== $operationMethod) {
                continue;
            }

            $pattern = preg_quote($template, '~');
            $pattern = preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', $pattern);
            if (preg_match('~^'.$pattern.'$~D', $path) === 1) {
                return $operation;
            }
        }

        return null;
    }

    /** @param array<int|string, mixed> $arguments */
    private static function argumentValue(string $name, int $index, \ReflectionParameter $parameter, array $arguments): mixed
    {
        if (array_key_exists($name, $arguments)) {
            return $arguments[$name];
        }
        if (array_key_exists($index, $arguments)) {
            return $arguments[$index];
        }
        return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
    }

    /** @param list<string> $path @param list<string> $prefix */
    private static function assertPathHasNoBoolean(mixed $value, array $path, array $prefix): void
    {
        if ($path === []) {
            if (is_bool($value)) {
                self::rejectBoolean(implode('.', $prefix) ?: 'request body');
            }
            return;
        }

        $segment = $path[0];
        $remaining = array_slice($path, 1);
        if ($segment === '*') {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $item) {
                self::assertPathHasNoBoolean($item, $remaining, [...$prefix, '*']);
            }
            return;
        }

        if (is_array($value) && array_key_exists($segment, $value)) {
            self::assertPathHasNoBoolean($value[$segment], $remaining, [...$prefix, $segment]);
        }
    }

    private static function rejectBoolean(string $field): never
    {
        throw new InvalidArgumentException("Boolean value is invalid for integer input field '{$field}'");
    }

    private static function assertValueHasNoBoolean(mixed $value, string $field): void
    {
        if (is_bool($value)) {
            self::rejectBoolean($field);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertValueHasNoBoolean($item, $field);
            }
        }
    }
}
