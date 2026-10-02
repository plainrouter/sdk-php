<?php

declare(strict_types=1);

namespace Plainrouter;

use InvalidArgumentException;
use Plainrouter\OpenAPI\Api\EventApi;
use Plainrouter\OpenAPI\ObjectSerializer;

/**
 * The generated Event API with the consent capture-time check applied before
 * any createEvent request is built.
 */
final class Events extends EventApi
{
    /**
     * Keep this literal identical to app/Domains/Signals/Data/ConsentDecision.php::CAPTURED_AT_PATTERN.
     */
    public const CAPTURED_AT_PATTERN = '/\A(?<datetime>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d{1,6}))?(?<offset>Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/';

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException when identity is sent without a valid consent.captured_at
     */
    public function createEventRequest($create_event_request, $idempotency_key = null, string $contentType = self::contentTypes['createEvent'][0])
    {
        self::validateCreateEventBody($create_event_request);

        return parent::createEventRequest($create_event_request, $idempotency_key, $contentType);
    }

    public static function validateCreateEventBody(mixed $body): void
    {
        $event = self::toArray($body);

        if (($event['visitor_id'] ?? null) === null && ($event['user_data'] ?? null) === null) {
            return;
        }

        $capturedAt = self::toArray($event['consent'] ?? null)['captured_at'] ?? null;

        if ($capturedAt === null) {
            throw new InvalidArgumentException(
                'consent.captured_at is required when visitor_id or user_data is present (missing).'
            );
        }

        if (! self::isValidCapturedAt($capturedAt)) {
            throw new InvalidArgumentException(
                'consent.captured_at has invalid format; expected the strict ISO-8601 capture time grammar (invalid format).'
            );
        }
    }

    private static function isValidCapturedAt(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::CAPTURED_AT_PATTERN, $value, $matches) !== 1) {
            return false;
        }

        [$year, $month, $day, $hour, $minute, $second] = array_map(
            'intval',
            preg_split('/\D/', $matches['datetime']) ?: [],
        );

        return checkdate($month, $day, $year) && $hour <= 23 && $minute <= 59 && $second <= 59;
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(mixed $value): array
    {
        if (is_object($value)) {
            $value = json_decode(
                json_encode(ObjectSerializer::sanitizeForSerialization($value), JSON_THROW_ON_ERROR),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        }

        return is_array($value) ? $value : [];
    }
}
