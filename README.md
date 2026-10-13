# Plainrouter PHP SDK

The official PHP SDK for the Plainrouter Signals Conversion API. It is generated from the repository's signed OpenAPI contract and is currently in `0.x` development.

Plainrouter is the paid ads platform for developers and agents. Its hosted [Meta Ads MCP server](https://plainrouter.com/solutions/meta-ads-mcp) lets Claude, ChatGPT, Codex, Cursor and other MCP clients read a Meta ad account and propose changes that pass policy checks. MCP setup, Agent Skills and the other SDKs live in [plainrouter/sdk](https://github.com/plainrouter/sdk).

## Install

```sh
composer require plainrouter/sdk
```

PHP 8.2 or newer is required.

## Use

Create one client with a Signal Tracker secret and call an API group:

```php
use Plainrouter\Client;

$client = new Client(token: getenv('PLAINROUTER_TOKEN'));

$events = $client->operations->listEvents(25);
$event = $client->events->getEvent('event-id');
```

The default base URL is `https://plainrouter.com/api/v1` and the default timeout is 30 seconds. Credentials are supplied by the caller and are never embedded in the SDK. Pass your own Guzzle client as `httpClient` to control transport options.

To copy a failed plan to a fresh draft, configure a client with a plan-writer bearer token:

```php
$client = new Client(token: getenv('PLAINROUTER_TOKEN'));
$draft = $client->plans->launchPlansCopy(1, 'failed-plan-id');
```

The zero-auth sandbox uses the same client without a token:

```php
$example = (new Client())->sandbox->getSandbox();
```

`$client->events->createEvent()` rejects a body that carries `visitor_id` or `user_data` without a valid `consent.captured_at` before any request is sent.

For response metadata, append `WithHttpInfo` to a generated operation. Models, API exceptions, configuration, and every generated operation are available in the deliberately separate `Plainrouter\OpenAPI` namespace.

## Retryable proposal errors

`ProposalErrors::isRetryable()` accepts either a
`Plainrouter\OpenAPI\ApiException` or a raw
`Psr\Http\Message\ResponseInterface`. It recognizes only 503 error envelopes
with a string message and either `workspace_lock_timeout` or
`proposal_not_recorded`; other bodies return `false`. Use the response body to
distinguish codes because async calls and endpoints with no documented 503 may
leave `getResponseObject()` null.

With Guzzle `stream => true`, generated parsing can consume a non-seekable
body. Streamed bodies cannot be re-read. If middleware reads the envelope bytes,
pass a buffered copy on so the SDK can still parse it, for example:
`$response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($bytes))`.
Alternatively, use the default buffered transport for these examples.
Async calls with `http_errors => false` can return a success-typed model on a 503;
check the captured raw response, even when the promise resolves.

Given a raw PSR response captured by your Guzzle middleware, use this after a
sync call or after an async promise settles, including if generated parsing
throws before an `ApiException` is available:

```php
use Plainrouter\ProposalErrors;

$retryable = ProposalErrors::isRetryable($response);
$errorCode = $retryable ? json_decode((string) $response->getBody(), true)['error']['code'] : null;
$retryAfter = $retryable && $response->hasHeader('Retry-After') ? $response->getHeaderLine('Retry-After') : null;
```

For a caught `ApiException`, the equivalent classification and code extraction
are:

```php
use Plainrouter\ProposalErrors;

$retryable = ProposalErrors::isRetryable($exception);
$errorCode = $retryable ? json_decode((string) $exception->getResponseBody(), true)['error']['code'] : null;
$headers = new \GuzzleHttp\Psr7\Response(200, $exception->getResponseHeaders() ?? []);
$retryAfter = $retryable && $headers->hasHeader('Retry-After') ? $headers->getHeaderLine('Retry-After') : null;
```

No automatic retry is added. Honor `Retry-After` when present (delta seconds or
an HTTP date) when scheduling a retry. Use `Client::$plans` for copy, including
its async methods. `Plainrouter\CopyResponseClient` is public but marked
`@internal`: it records responses for the copy facade, and callers should not
instantiate or use it directly.

`Plainrouter\Client` defaults to `Accept: application/json`; override with `accept: "..."`. API redirects throw `OpenAPI\ApiException` with status (`getCode()`) and `getResponseHeaders()`, before sync or async parsing, even with `http_errors => false`. Redirect following is disabled. `$client->actions` exposes Actions propose through this transport. The separate generated namespace provides raw clients without this policy.

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

Packagist reads the read-only mirror [`plainrouter/sdk-php`](https://github.com/plainrouter/sdk-php), which the `mirror-php.yml` workflow splits from `packages/php`. Releases use `php-v<version>` tags in this repository; the workflow pushes them to the mirror as `v<version>`. It then asks Packagist to refresh the package. Open issues and pull requests here, not on the mirror.

Licensed under Apache-2.0.

When `accept:` is omitted, a caller Guzzle client's configured Accept is kept;
absent or exact `*/*` uses JSON. An explicit `accept:` overrides this default.
`Plainrouter\JsonResponseClient` is public but marked `@internal`; use `Client`
instead of constructing it directly. It forces `allow_redirects => false`.
