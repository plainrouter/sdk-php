# Plainrouter PHP SDK

The official PHP SDK for the Plainrouter Signals Conversion API. It is generated from the repository's signed OpenAPI contract and is currently in `0.x` development. It is not yet published to Packagist.

PHP 8.2 or newer is required.

## Use

Create one client with a Signal Tracker secret and call one of the three API groups:

```php
use Plainrouter\Client;

$client = new Client(token: getenv('PLAINROUTER_TOKEN'));

$events = $client->operations->listEvents(25);
$event = $client->events->getEvent('event-id');
```

The default base URL is `https://plainrouter.com/api/v1` and the default timeout is 30 seconds. Credentials are supplied by the caller and are never embedded in the SDK. Pass your own Guzzle client as `httpClient` to control transport options.

The zero-auth sandbox uses the same client without a token:

```php
$example = (new Client())->sandbox->getSandbox();
```

`$client->events->createEvent()` rejects a body that carries `visitor_id` or `user_data` without a valid `consent.captured_at` before any request is sent.

For response metadata, append `WithHttpInfo` to a generated operation. Models, API exceptions, configuration, and every generated operation are available in the deliberately separate `Plainrouter\OpenAPI` namespace.

## Development

From `packages/php`:

```sh
composer install
composer test
```

Generated files live in `src/OpenAPI/`; do not edit them by hand. From the repository root, verify deterministic generation with:

```sh
scripts/check-php-generated.sh
```

`php scripts/live-smoke.php` calls the live zero-auth sandbox through the SDK. It persists nothing and needs no credentials.

Licensed under Apache-2.0.
