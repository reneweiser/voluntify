# Batch Issue Playbook

Resolve multiple prioritized issues on a single branch with one commit per issue, ending in a single PR. No user gates between issues — execute continuously.

---

## Workflow

### 1. Prioritize & Plan

Rank issues by impact and dependency order:

| Priority | Criteria |
|----------|----------|
| 1st | Foundational changes other issues depend on (e.g., shared utility methods) |
| 2nd | High-frequency code paths (middleware, policies — every request) |
| 3rd | Landing page / high-visibility features (dashboard) |
| 4th | Functional enhancements |
| 5th | Quick wins / DX improvements |

### 2. Branch Setup

```bash
git checkout main && git pull
git checkout -b improvement/batch-<issue numbers joined by ->
```

Single branch for all issues. Example: `improvement/batch-30-34-29-32-33`

### 3. Per-Issue Execution (repeat for each issue)

1. **Read** — All files referenced in the issue + their callers/tests
2. **Implement** — Minimal change, no scope creep
3. **Test** — Write/update tests, run `--filter=<TestClass>` to verify
4. **Lint** — `vendor/bin/sail bin pint --dirty --format agent`
5. **Commit** — Stage specific files, conventional commit with `Closes #<number>`
6. **Continue** — Immediately start next issue

### 4. After All Issues

1. `vendor/bin/sail bin pint --dirty --format agent` (final lint pass)
2. `vendor/bin/sail artisan test --compact` (full suite — must be green)
3. Update this playbook if the workflow evolved
4. Commit playbook changes
5. Push + create PR closing all issues

---

## Commit Message Convention

```
<type>(<scope>): <short description>

<body — what and why, not how>

Closes #<number>
```

Types: `feat`, `fix`, `perf`, `chore`, `refactor`, `test`, `docs`

---

## PR Format

Title: `improvement: batch resolve #X, #Y, #Z`

Body:
```markdown
## Summary
- #X — <one-line description>
- #Y — <one-line description>
- #Z — <one-line description>

## Test plan
- [ ] All existing tests pass (828+ assertions)
- [ ] New tests added for each functional change
- [ ] Linted with Pint
```

---

## Example: Batch 30-34-29-32-33

| # | Issue | Type | Commit Prefix | Key Files |
|---|-------|------|---------------|-----------|
| 1 | #30 — Memoize policy role lookups | perf | `perf(policies)` | User.php, *Policy.php |
| 2 | #34 — Targeted org query in middleware | perf | `perf(middleware)` | ResolveOrganization.php |
| 3 | #29 — Consolidate dashboard queries | perf | `perf(dashboard)` | Dashboard.php |
| 4 | #32 — Clone email templates | feat | `feat(clone)` | CloneEvent.php |
| 5 | #33 — ActivityLog factory | feat | `feat(factory)` | ActivityLog.php, ActivityLogFactory.php |

Order rationale: #30 first because `cachedRoleFor()` is used by #29's dashboard. #34 next (same perf category, independent). #29 depends on #30. #32 and #33 are independent enhancements, ordered by impact.
