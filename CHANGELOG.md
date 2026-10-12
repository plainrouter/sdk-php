# Changelog

## 0.4.0 (unreleased)

- ApiException#getResponseObject() contains ProposalUnavailable for both known 503 codes. ProposalErrors::isRetryable() identifies the retryable refusal; getResponseHeaders() retains Retry-After.
- Regenerate from core main `40a2c3f1563aaa489d5658ef6058ffb6a613709c` with the existing pinned generator and settings.
- Include core contract changes: bounded Actions and Launch projections/continuations, rule summaries, workspace creative and account inventory reads, frozen target evidence, and delivery incident attention/held states. The unused DeploymentPlan schema is removed upstream; creative MCP metadata schemas reflect the template-only connector contract.
