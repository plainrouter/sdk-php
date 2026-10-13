# Changelog

## 0.4.0 (unreleased)

- On propose/execute, ApiException#getResponseObject() contains ProposalUnavailable for both known 503 codes. ProposalErrors::isRetryable() identifies the retryable refusal; getResponseHeaders() retains Retry-After.
- Regenerate from core main `40a2c3f1563aaa489d5658ef6058ffb6a613709c` with the existing pinned generator and settings.
- Include core contract changes: bounded Actions and Launch projections/continuations, rule summaries, workspace creative and account inventory reads, frozen target evidence, and delivery incident attention/held states. The unused DeploymentPlan schema is removed upstream; creative MCP metadata schemas reflect the template-only connector contract.

### Breaking changes

- `launchPlansIndex($ws, $per_page)` became `($ws, $page, $cursor, $per_page)` across `launchPlansIndex`, `WithHttpInfo`, `Async`, `AsyncWithHttpInfo`, and `Request`; `launchPlansIndex(1, 50)` now silently requests page 50 with per_page 25. Migration: use named arguments, e.g. `$client->plans->launchPlansIndex(workspace: 1, per_page: 50)`, and the same names on all variants.
- `$contentType` moved right in `actionsApiIndex`, `actionsApiBatch`, `launchPlansShow`, `getInventoryMetrics`, and `launchPlansIndex`, including their `WithHttpInfo`, `Async`, `AsyncWithHttpInfo`, and `Request` variants; positional content types can become cursor values. Migration: name it explicitly, e.g. `$client->plans->launchPlansShow(workspace: 1, deployment_plan: "plan-id", contentType: "application/json")` or `$client->plans->launchPlansIndex(workspace: 1, per_page: 50, contentType: "application/json")`.
- `Plainrouter\OpenAPI\Model\DeploymentPlan` is removed. Migration: use `Plainrouter\OpenAPI\Model\DeploymentPlanRead`.
- `Model\CreativeIntakeReadCreative` and `Model\CreativeIntakeReadCreativeTags` are removed/renamed; `CreativeIntakeRead::getCreative()` returns `LaunchCreativesIndex200ResponseCreativesDataInner`. Migration: use `Plainrouter\OpenAPI\Model\LaunchCreativesIndex200ResponseCreativesDataInner` and `LaunchCreativesIndex200ResponseCreativesDataInnerTags` for construction and class checks.
- Propose/execute 503 `getResponseObject()` now uses `ProposalUnavailable` instead of `WorkspaceLockTimeout`, including the generated handling of non-envelope 503 bodies (core #1063). Migration: detect lock timeout by checking `$exception->getResponseObject()->getError()->getCode()` for `"workspace_lock_timeout"` rather than an `instanceof WorkspaceLockTimeout` check; `ProposalErrors::isRetryable()` handles known codes and rejects malformed bodies.
- Plan `creatives` is capped at 5 by default (≤30 KB) on `plans.show` and every plan body (index, store, copy, update, validate), with `creative_count`, `next_cursor`, `truncated`, and `continuation`. Migration: request further creatives through the plan read endpoint using each returned `next_cursor` until complete; do not treat an embedded plan body as the full creative list.
- Actions batch `data.actions`, propose `proposal.actions`, and dry-run `dry_run.actions` are capped at 25 by default (≤65 KB). Migration: follow `next_cursor` with the same inputs/filters; for saved proposals continue through the batch read endpoint rather than resubmitting a write.
- Actions index and plans index pages are byte-bounded and may contain fewer than `limit`/`per_page` rows. Migration: follow the server’s `next_cursor` (raw response metadata or additional properties where omitted from generated types), not `page=n+1` after a truncated page, which can skip rows.
- Inventory metrics `metrics`, `object_metrics`, and `changes` are each capped at 25 (≤22 KB), with per-section `pagination` and a base64 `next_cursor`. Migration: follow the returned `next_cursor` as `cursor` with unchanged scope/filters until every required section is complete.

### Retry classification

- Both `workspace_lock_timeout` and `proposal_not_recorded` are retryable on propose, execute, and copy; preserve `Retry-After` when present (delta seconds or an HTTP date) when scheduling a retry. No automatic retry is added.
- Use `Client::$plans` (the `Plans` facade) for copy: it retains `WorkspaceLockTimeout` for lock timeout and decodes `proposal_not_recorded` as `ProposalUnavailable` in sync and async variants. Direct generated copy parsing remains unchanged.
