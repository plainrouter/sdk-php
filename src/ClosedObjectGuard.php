<?php

declare(strict_types=1);

namespace Plainrouter;

use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

/** Refuses undocumented properties on request objects closed by the vendored spec. */
final class ClosedObjectGuard
{
    /** @var array<string, mixed>|null */
    private static ?array $contract = null;

    private static int $deserializationDepth = 0;

    /** Validate the complete input before the generated deserializer follows flattened model types. */
    public static function deserialize(mixed $class, mixed $value, callable $deserialize): mixed
    {
        if (self::$deserializationDepth > 0 || self::modelPointer($class) === null) {
            return $deserialize();
        }

        self::assertModelInput($class, $value);
        self::$deserializationDepth++;
        try {
            return $deserialize();
        } finally {
            self::$deserializationDepth--;
        }
    }

    private static function modelPointer(mixed $class): ?string
    {
        if (! is_string($class) || ! class_exists($class) || ! is_a($class, \Plainrouter\OpenAPI\Model\ModelInterface::class, true)) {
            return null;
        }

        return self::contract()['models'][self::modelNameForClass($class)] ?? null;
    }

    public static function assertRequestBody(string $operation, mixed $body): void
    {
        $pointer = self::contract()['operations'][$operation] ?? null;
        if (! is_string($pointer)) {
            throw new LogicException("No request schema is registered for {$operation}");
        }

        $schema = self::resolveSchema($pointer);
        self::assertSchema($body, $pointer, $schema['title'] ?? $operation);
    }

    public static function assertModelInput(mixed $class, mixed $value): void
    {
        if (self::$deserializationDepth > 0) {
            return;
        }

        $pointer = self::modelPointer($class);
        if ($pointer !== null) {
            $modelName = self::modelNameForClass($class);
            if (is_string($value)) {
                $decoded = json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded))) {
                    $value = $decoded;
                }
            }

            self::assertSchema($value, $pointer, $modelName);
        }
    }

    public static function assertSchema(mixed $value, string $pointer, string $objectName): void
    {
        self::validate($value, $pointer, $objectName);
    }

    public static function assertClosedModelKey(string $objectName, string $pointer, mixed $key): void
    {
        $schema = self::resolveSchema($pointer);
        if (($schema['additionalProperties'] ?? null) !== false) {
            return;
        }

        if ($key === null) {
            $key = '0';
        } elseif (is_int($key) || is_float($key) || is_bool($key)) {
            $key = (string) $key;
        } elseif (! is_string($key)) {
            $key = get_debug_type($key);
        }

        $properties = $schema['properties'] ?? [];
        if (! array_key_exists($key, $properties)) {
            self::throwUnknownKey($key, $objectName);
        }
    }

    public static function assertClosedModelKeyForModel(mixed $class, mixed $key): void
    {
        if (! is_string($class) || ! class_exists($class) || ! is_a($class, \Plainrouter\OpenAPI\Model\ModelInterface::class, true)) {
            return;
        }

        $modelName = self::modelNameForClass($class);
        $pointer = self::contract()['models'][$modelName] ?? null;
        if (is_string($pointer)) {
            self::assertClosedModelKey($modelName, $pointer, $key);
        }
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function matchingBranches(array $branches, array $values): array
    {
        $commonProperties = null;
        $resolvedBranches = [];
        foreach ($branches as $index => $branch) {
            $pointer = $branch['$ref'] ?? null;
            if (! is_string($pointer)) {
                return $branches;
            }

            $schema = self::resolveSchema($pointer);
            $resolvedBranches[$index] = $schema;
            $properties = array_keys($schema['properties'] ?? []);
            $commonProperties = $commonProperties === null
                ? $properties
                : array_values(array_intersect($commonProperties, $properties));
        }

        $discriminators = [];
        foreach ($commonProperties ?? [] as $property) {
            $allowedValues = [];
            foreach ($resolvedBranches as $index => $schema) {
                $propertyPointer = $schema['properties'][$property]['$ref'] ?? null;
                if (! is_string($propertyPointer)) {
                    $allowedValues[$index] = null;
                    continue;
                }

                $propertySchema = self::resolveSchema($propertyPointer);
                $set = array_key_exists('const', $propertySchema)
                    ? [$propertySchema['const']]
                    : ($propertySchema['enum'] ?? null);
                $allowedValues[$index] = is_array($set) && $set !== [] ? $set : null;
            }

            if (count(array_filter($allowedValues, 'is_array')) !== count($branches)) {
                continue;
            }

            $encodedSets = array_map(
                static fn (array $set): string => json_encode($set, JSON_THROW_ON_ERROR),
                $allowedValues,
            );
            if (count(array_unique($encodedSets)) === 1) {
                continue;
            }

            $discriminators[$property] = $allowedValues;
        }

        if ($discriminators === []) {
            return $branches;
        }

        $matches = [];
        foreach ($branches as $index => $branch) {
            foreach ($discriminators as $property => $allowedValues) {
                if (array_key_exists($property, $values) && ! in_array($values[$property], $allowedValues[$index], true)) {
                    continue 2;
                }
            }
            $matches[$index] = $branch;
        }

        return $matches === [] ? $branches : $matches;
    }

    private static function validate(mixed $value, string $pointer, string $objectName): void
    {
        $schema = self::resolveSchema($pointer);
        $values = self::objectValues($value);

        if ($values !== null && ($schema['additionalProperties'] ?? null) === false) {
            $properties = $schema['properties'] ?? [];
            foreach ($values as $key => $_) {
                $key = (string) $key;
                if (! array_key_exists($key, $properties)) {
                    self::throwUnknownKey($key, $objectName);
                }
            }
        }

        if ($values !== null) {
            foreach ($schema['properties'] ?? [] as $property => $propertySchema) {
                if (! array_key_exists($property, $values) || ! is_string($propertySchema['$ref'] ?? null)) {
                    continue;
                }

                self::validate(
                    $values[$property],
                    $propertySchema['$ref'],
                    $objectName.'.'.$property,
                );
            }
        }

        if (is_array($value) && is_string($schema['items']['$ref'] ?? null)) {
            foreach ($value as $item) {
                self::validate(
                    $item,
                    $schema['items']['$ref'],
                    $objectName.'[*]',
                );
            }
        }

        foreach (['anyOf', 'oneOf'] as $kind) {
            $branches = $schema[$kind] ?? null;
            if (! is_array($branches) || $branches === []) {
                continue;
            }

            $candidates = $values === null ? $branches : self::matchingBranches($branches, $values);
            $errors = [];
            foreach ($candidates as $index => $branch) {
                if (! is_string($branch['$ref'] ?? null)) {
                    continue;
                }

                try {
                    self::validate(
                        $value,
                        $branch['$ref'],
                        $objectName.'.'.$kind.'['.$index.']',
                    );
                    $errors = [];
                    break;
                } catch (InvalidArgumentException $error) {
                    $errors[$index] = $error;
                }
            }

            if ($errors !== []) {
                $firstError = array_values($errors)[0] ?? null;
                if ($firstError instanceof InvalidArgumentException) {
                    throw $firstError;
                }
            }
        }

        foreach ($schema['allOf'] ?? [] as $branch) {
            if (is_string($branch['$ref'] ?? null)) {
                self::validate($value, $branch['$ref'], $objectName);
            }
        }
    }

    /** @return array<string|int, mixed>|null */
    private static function objectValues(mixed $value): ?array
    {
        if ($value instanceof \Plainrouter\OpenAPI\Model\ModelInterface) {
            $value = \Plainrouter\OpenAPI\ObjectSerializer::sanitizeForSerialization($value);
        } elseif ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return is_array($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function resolveSchema(string $pointer): array
    {
        $schemas = self::contract()['schemas'];
        $seen = [];

        while (true) {
            $schema = $schemas[$pointer] ?? null;
            if (! is_array($schema)) {
                throw new UnexpectedValueException("Missing closed-object schema {$pointer}");
            }

            $next = $schema['$ref'] ?? null;
            if (! is_string($next)) {
                return $schema;
            }
            if (isset($seen[$pointer])) {
                throw new UnexpectedValueException("Recursive schema reference at {$pointer}");
            }

            $seen[$pointer] = true;
            $pointer = $next;
        }
    }

    private static function throwUnknownKey(string $key, string $objectName): never
    {
        throw new ClosedObjectKeyException($key, $objectName);
    }

    /** @param class-string<\Plainrouter\OpenAPI\Model\ModelInterface> $class */
    private static function modelNameForClass(string $class): string
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        return $model->getModelName();
    }

    /** @return array<string, mixed> */
    private static function contract(): array
    {
        if (self::$contract === null) {
            $manifest = file_get_contents(__DIR__.'/closed-input-objects.json');
            if ($manifest === false) {
                throw new UnexpectedValueException('The generated closed request-object manifest is missing');
            }

            $contract = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
            $modelManifest = file_get_contents(__DIR__.'/closed-input-models.json');
            if ($modelManifest === false) {
                throw new UnexpectedValueException('The generated closed input-model map is missing');
            }

            $contract['models'] = json_decode($modelManifest, true, flags: JSON_THROW_ON_ERROR);
            if (
                ! is_array($contract['schemas'] ?? null)
                || ! is_array($contract['operations'] ?? null)
                || ! is_array($contract['models'] ?? null)
            ) {
                throw new UnexpectedValueException('The generated closed request-object manifest is invalid');
            }

            self::$contract = $contract;
        }

        return self::$contract;
    }
}

/** @internal Carries the unmatched key while trying JSON Schema union variants. */
final class ClosedObjectKeyException extends InvalidArgumentException
{
    public function __construct(public readonly string $key, string $objectName)
    {
        parent::__construct(
            'Unknown key '.json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .' in closed request object '.json_encode($objectName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
