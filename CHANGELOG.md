# Changelog

## 0.4.1 (Unreleased)

- Fix launcher import with custom base URLs so it reaches the route core serves.
- Decode documented error response models on generated and facade async calls, matching sync `ApiException` response objects while preserving curated 404 and copy 503 handling.
- Reflect core #1196's stricter server behavior for the existing workspace contract: live credentials targeting foreign, inaccessible same-team, or missing workspaces, and resources inside them, now receive 404 `api_route_not_found`.
- Non-canonical workspace path IDs (including leading zeros, signs, spaces, decimals, and `true`/`false` in any case) receive the same 404, even for the caller's own workspace. Unauthenticated requests receive the same response for existing and missing workspaces.
- `workspace_id` is checked only by routes that read it; on those routes, missing, foreign, and non-canonical selectors receive the canonical-missing response, while it has no effect elsewhere. Foreign or non-canonical entries in `workspace_ids` receive the same 422 as nonexistent entries.
- Callers that branched on 401, 403, 400, or 302 for a foreign or missing workspace now receive 404 `api_route_not_found`.
- Regenerate the documented contract: `GET /agent/context` and `POST /mcp/launcher/import` now document 404 `ApiRouteNotFound`; `GET /agent/context` 403 now reads “The accessible workspace scope or operation is unauthorized, or an ambiguous workspace selection was omitted.” and 422 now reads “Invalid account ID, mismatched account binding, or ambiguous active accounts.” Curated PHP `agentContext` and `fileImports` calls also decode the new 404 into `ApiRouteNotFound` in sync and async response modes.

## 0.4.0 (2026-10-15)

- Tighten page validation. On the actions, action-rules, tests, creatives, plans and policies lists, page is an integer from 1 to 10^16. Unset and language-null page values are omitted from requests, preserving the server default page 1. Integer strings such as "2" are accepted. Values outside the bounds are refused by the SDK before sending; the curated SDK also refuses booleans before sending. Other invalid values are refused by the SDK before sending or by the server with 422 on page.

- Refuse unknown keys, including case variants, on closed request objects before sending; errors name the key and object. Closed objects and their allowed keys are derived from the vendored contract, including model construction, mapping conversion, and raw request bodies.
- Honor an explicit custom base URL for the newly generated file-import operation; retain its contract-defined host when using the default configuration.
- Expose the regenerated Launch policy index, create and retire operations and draft-batch file import through the curated client, preserving its shared header, Accept and redirect policy.
- Tighten validation of the existing contract: integer inputs refuse JSON booleans instead of treating them as 1 or 0. The curated client rejects booleans before sending with an error naming the integer field; integer selectors are generated from the contract, and integers and numeric strings remain accepted.
- Tighten existing Action params validation: all 14 variants accept only their documented keys; extra keys, including case variants, fail with `action_params_invalid`.
- Document `launch_video_upload_unavailable` for Launch plans that require a new Meta video upload, at validation and execution.
- Include newly documented integer-boolean 422 responses, the plan validate 422 code, creative-list 404 and 403 responses, and corrected descriptions.

- Correct `actions.api.index`, `actions.api.policy`, `actions.api.show`, `actions.api.batch`, and `actions.api.decision-receipt` errors: 401 is `AgentCredentialError` or `ErrorMessage`, and 403 is `AgentCredentialError`; list, policy, show, and batch 404 is `ApiRouteNotFound`, while the curated client decodes decision-receipt 404 as `ActionReceiptUnavailable` or `ApiRouteNotFound`, preserving the raw body and `ApiException` status 404 (or the returned model/status with `http_errors=false`) in sync and async calls. The same handling decodes copy 404 as `PlanCopyNotFound` or `ApiRouteNotFound`. Remove `enabled` from action-rule create requests and `platform_ad_account_id` from update requests; both fields are prohibited by the contract.
- Curate the previously generated-only `actions.api.dry-run`, `actions.api.index`, `actions.api.policy`, `actions.api.decision-receipt`, `actions.api.show`, `actions.api.batch`, `actions.rules.index`, `actions.rules.store`, `actions.rules.show`, `actions.rules.update`, `actions.tests.api.index`, `actions.tests.api.store`, `actions.tests.api.show`, `agent.context`, `getCookieDeclaration`, `launch.creatives.index`, `storeCreative`, `storeCreativeFromUrl`, `get_account_inventory`, and `get_inventory_metrics` operations through the `actionDryRun`, `actionReads`, `actionRules`, `adTests`, `agentContext`, `cookieDeclarations`, `creatives`, and `inventory` groups.
- Add `GET /agent/context` and action-rule `POST` collection, item `GET`, and item `PATCH` operations.
- Correct Destination `config`, `attention_reason`, `held_count` and `notices` types; expose plans-list `last_page`, `next_cursor`, `truncated`, `next_page_arguments` and `continuation`; clarify that `account_id` is the internal platform-account ID; correct action-rule list authentication and account/workspace selectors; fix the empty-array branches of action-rule write unions so Python generation succeeds with `--fail-on-warning`.

- The new public `Plainrouter\JsonResponseClient` is marked `@internal` and wraps the curated service groups; callers should use `Client`. It forces `allow_redirects => false`, even on a caller Guzzle client.
- With `accept:` omitted, caller Guzzle Accept is retained; absent or exact `*/*` selects JSON. Explicit `accept:` overrides that value.

- Default the hand-written client to `Accept: application/json`; override with `accept:`. Reject API 3xx responses with `ApiException`, retaining status and Location, in sync and async calls, including `http_errors => false`. Retry examples read headers case-insensitively and return `null` for absent Retry-After; document consumed streamed bodies and async success-typed 503 results. Actions propose is available through the hand-written client's `actions` service group.

- On synchronous propose/execute, `ApiException::getResponseObject()` contains `ProposalUnavailable` for both known 503 codes. `ProposalErrors::isRetryable()` classifies an `ApiException` or a raw PSR response independently of that model, including async calls and endpoints with no documented 503; response headers retain `Retry-After`.
- Regenerate from core main `40a2c3f1563aaa489d5658ef6058ffb6a613709c` with the existing pinned generator and settings.
- Include core contract changes: bounded Actions and Launch projections/continuations, rule summaries, workspace creative and account inventory reads, frozen target evidence, and delivery incident attention/held states. The unused DeploymentPlan schema is removed upstream; creative MCP metadata schemas reflect the template-only connector contract.

### Breaking changes

- `launchPlansIndex($ws, $per_page)` became `($ws, $page, $cursor, $per_page)` across `launchPlansIndex`, `WithHttpInfo`, `Async`, `AsyncWithHttpInfo`, and `Request`; `launchPlansIndex(1, 50)` now silently requests page 50 with per_page 25. Migration: use named arguments, e.g. `$client->plans->launchPlansIndex(workspace: 1, per_page: 50)`, and the same names on all variants.
- `$contentType` moved right in `actionsApiIndex`, `actionsApiBatch`, `launchPlansShow`, `getInventoryMetrics`, and `launchPlansIndex`, including their `WithHttpInfo`, `Async`, `AsyncWithHttpInfo`, and `Request` variants; positional content types can become cursor values. Migration: name it explicitly, e.g. `$client->plans->launchPlansShow(workspace: 1, deployment_plan: "plan-id", contentType: "application/json")` or `$client->plans->launchPlansIndex(workspace: 1, per_page: 50, contentType: "application/json")`.
- `Plainrouter\OpenAPI\Model\DeploymentPlan` is removed. Migration: use `Plainrouter\OpenAPI\Model\DeploymentPlanRead`.
- `Model\CreativeIntakeReadCreative` and `Model\CreativeIntakeReadCreativeTags` are removed/renamed; `CreativeIntakeRead::getCreative()` returns `LaunchCreativesIndex200ResponseCreativesDataInner`. Migration: use `Plainrouter\OpenAPI\Model\LaunchCreativesIndex200ResponseCreativesDataInner` and `LaunchCreativesIndex200ResponseCreativesDataInnerTags` for construction and class checks.
- Propose/execute 503 `getResponseObject()` now uses `ProposalUnavailable` instead of `WorkspaceLockTimeout`, including the generated handling of non-envelope 503 bodies (core #1063). Migration: classify an `ApiException` or raw PSR response with `ProposalErrors::isRetryable()`. To distinguish lock timeout, decode the response body after the helper succeeds and compare `error.code` with `"workspace_lock_timeout"` (see the short example below). This works for async calls and endpoints with no documented 503, where `getResponseObject()` can be null; malformed bodies are not retryable.
- Plan `creatives` is capped at 5 by default (≤30 KB) on `plans.show` and every plan body (index, store, copy, update, validate), with `creative_count`, `next_cursor`, `truncated`, and `continuation`. Migration: request further creatives through the plan read endpoint using each returned `next_cursor` until complete; do not treat an embedded plan body as the full creative list.
- Actions batch `data.actions`, propose `proposal.actions`, and dry-run `dry_run.actions` are capped at 25 by default (≤65 KB). Migration: follow `next_cursor` with the same inputs/filters; for saved proposals continue through the batch read endpoint rather than resubmitting a write.
- Actions index and plans index pages are byte-bounded and may contain fewer than `limit`/`per_page` rows. Migration: follow the server’s `next_cursor` (raw response metadata or additional properties where omitted from generated types), not `page=n+1` after a truncated page, which can skip rows.
- Inventory metrics `metrics`, `object_metrics`, and `changes` are each capped at 25 (≤22 KB), with per-section `pagination` and a base64 `next_cursor`. Migration: follow the returned `next_cursor` as `cursor` with unchanged scope/filters until every required section is complete.

### Retry classification

- Both `workspace_lock_timeout` and `proposal_not_recorded` are retryable on propose, execute, and copy; preserve `Retry-After` when present (delta seconds or an HTTP date) when scheduling a retry. No automatic retry is added.
- Use `Client::$plans` (the `Plans` facade) for copy: it retains `WorkspaceLockTimeout` for lock timeout and decodes `proposal_not_recorded` as `ProposalUnavailable` in sync and async variants. Direct generated copy parsing remains unchanged.
- `ProposalErrors::isRetryable()` accepts a raw `Psr\Http\Message\ResponseInterface` as well as an `ApiException`. Capture the raw response with your Guzzle middleware if generated parsing throws before an `ApiException` is available.
- `Plainrouter\CopyResponseClient` is a public class marked `@internal`, used by the `Plans` facade to record copy responses. Callers should not instantiate or use it directly; use `Client::$plans` instead.

With Guzzle `stream => true`, generated parsing can consume a non-seekable
body. Streamed bodies cannot be re-read. If middleware reads the envelope bytes,
pass a buffered copy on so the SDK can still parse it, for example:
`$response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($bytes))`.
Alternatively, use the default buffered transport for these examples.
Async calls with `http_errors => false` can return a success-typed model on a 503;
check the captured raw response, even when the promise resolves.

Given a raw PSR response captured by your Guzzle middleware, the same example
works after a sync call or an async promise settles, on any endpoint:

```php
use Plainrouter\ProposalErrors;

$retryable = ProposalErrors::isRetryable($response);
$errorCode = $retryable ? json_decode((string) $response->getBody(), true)['error']['code'] : null;
$retryAfter = $retryable && $response->hasHeader('Retry-After') ? $response->getHeaderLine('Retry-After') : null;
```

For an `ApiException` caught from a sync call or async rejection, use
`ProposalErrors::isRetryable($exception)` and, only when true,
`json_decode((string) $exception->getResponseBody(), true)['error']['code']`.
Read `Retry-After` case-insensitively from `$exception->getResponseHeaders()` using a PSR response as shown in the README; an absent header is `null` in both examples. Neither path relies
on `getResponseObject()` being populated.
