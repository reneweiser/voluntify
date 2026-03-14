# Issue Fix Playbook

Step-by-step workflow for taking an issue from triage to merged PR. Each phase includes the what, why, and specific commands.

---

## Phase 1: Branch Setup

1. Switch to `main` and pull latest:
   ```bash
   git checkout main && git pull
   ```
2. Delete the previous feature branch if needed (local + remote):
   ```bash
   git branch -D feature/old-branch
   git push origin --delete feature/old-branch
   ```
3. Create the new feature branch:
   ```bash
   git checkout -b feature/<issue-number>-<short-description>
   ```

**Why:** Clean branch from latest main avoids merge conflicts and keeps history linear.

---

## Phase 2: Understand the Code

1. Read the file(s) referenced in the issue to understand current behavior.
2. Trace the data flow: who calls this code, what inputs are trusted, what guards exist.
3. Check sibling files for patterns and conventions to follow.

**Why:** Fixing code you haven't read leads to regressions. Understanding the call chain reveals whether the fix belongs at the component layer, action layer, or both.

---

## Phase 3: Implement the Fix

1. Make the minimal change that addresses the issue.
2. Remove any imports or code that become unused as a result.
3. Follow existing code conventions (check sibling files).

**Why:** Minimal changes are easier to review, less likely to introduce side effects, and faster to ship.

---

## Phase 4: Write Tests

1. Find the existing test file for the component/class being fixed.
2. Add a test that reproduces the exact vulnerability or bug:
   - Set up the preconditions (e.g., foreign org, foreign data).
   - Attempt the disallowed action.
   - Assert the correct failure mode (exception type, HTTP status, unchanged DB state).
3. Run the new test in isolation:
   ```bash
   vendor/bin/sail artisan test --compact --filter=<TestClassName>
   ```
4. If the test fails, adjust assertions to match the actual failure mode (e.g., Livewire tests throw exceptions rather than returning HTTP status codes).

**Why:** A test that fails without the fix and passes with it proves the fix works and prevents regressions.

---

## Phase 5: Lint and Full Test Suite

1. Run the code formatter on modified files:
   ```bash
   vendor/bin/sail bin pint --dirty --format agent
   ```
2. Run the full test suite:
   ```bash
   vendor/bin/sail artisan test --compact
   ```
3. All tests must pass before proceeding.

**Why:** Formatting consistency and full-suite green are table stakes for a mergeable PR.

---

## Phase 6: Automated Review

Launch review agents in parallel to critique the changes from multiple angles:

1. **Security review agent** — Search the entire codebase for similar patterns to the one just fixed. The same vulnerability often exists in sibling components or related actions.
2. **Test quality review agent** — Evaluate whether the new tests are comprehensive, follow project conventions, and cover edge cases.

If the review finds additional issues (e.g., the same vulnerability in another file):
- Fix them in the same branch.
- Add tests for each additional fix.
- Re-run Pint + full test suite.

**Why:** A single vulnerability often has siblings. Automated review catches what manual inspection misses and keeps the scope of the fix complete.

---

## Phase 7: Browser Testing with Playwright

### 7a: Set Up Test Data

1. Use `tinker` to create realistic test data spanning multiple orgs/roles:
   - Org A: user with role, events, groups, volunteers with magic tokens
   - Org B: separate events, groups, volunteers (the "attacker" org)
2. Note public tokens and magic tokens for URL construction.

### 7b: Test the Admin Perspective

1. Log in as the staff user via Playwright.
2. Navigate to the affected pages.
3. **Happy path:** Verify legitimate actions work through the UI (assign group, remove event, etc.).
4. **Attack path:** Use `Livewire.all()` + `$wire` calls via `browser_evaluate` to bypass UI controls and call component methods directly with foreign IDs.
5. Verify:
   - Attacks return errors (404, 403).
   - Database state is unchanged after failed attacks.
   - UI dropdowns/selects only list org-scoped data.

### 7c: Test the Volunteer Perspective

1. Visit public-facing pages using magic tokens:
   - Public group page (`/groups/{publicToken}`)
   - Ticket page (`/my-ticket/{magicToken}`)
   - Volunteer portal (`/my-portal/{magicToken}`)
2. Verify each page only shows data belonging to that volunteer's org/events.
3. Test tampered/invalid tokens return 404.

### 7d: Clean Up

1. Truncate all test data via `tinker`.
2. Close the browser.

**Why:** Playwright tests catch issues that unit tests miss: UI state leakage, client-side trust assumptions, and real HTTP response behavior. Testing both admin and volunteer perspectives ensures the fix holds across all access patterns.

---

## Phase 8: Commit

1. Stage only the modified files (never `git add -A`):
   ```bash
   git add <file1> <file2> ...
   ```
2. Write a conventional commit message using a heredoc:
   ```bash
   git commit -m "$(cat <<'EOF'
   fix(<scope>): <what changed>

   <Why it changed. Reference the issue number.>

   - Bullet points for each discrete change
   - Include both the fix and the additional findings

   Closes #<issue-number>
   EOF
   )"
   ```

**Why:** Atomic commits with clear messages make `git log` useful and bisect reliable.

---

## Phase 9: Create PR

1. Push the branch:
   ```bash
   git push -u origin feature/<branch-name>
   ```
2. Create the PR with `gh`:
   ```bash
   gh pr create --title "<short title> (#<issue>)" --body "$(cat <<'EOF'
   ## Summary
   - Bullet points describing each change

   ## Test plan
   - [x] Unit/feature tests added and passing
   - [x] Full test suite green (N tests)
   - [x] Playwright browser tests verified (list scenarios)

   Closes #<issue-number>
   EOF
   )"
   ```

**Why:** A well-structured PR with a test plan makes review fast and builds confidence in the fix.

---

## Quick Reference

| Phase | Command / Action | Gate |
|-------|-----------------|------|
| 1. Branch | `git checkout -b feature/...` | Clean main |
| 2. Read | Read affected files + call chain | Understand before changing |
| 3. Fix | Minimal targeted change | Follows conventions |
| 4. Test | `--filter=TestClass` | New test passes |
| 5. Lint | `pint --dirty` + full suite | All green |
| 6. Review | Parallel security + quality agents | No siblings missed |
| 7. Browser | Playwright: happy path + attack path + volunteer | No leakage |
| 8. Commit | Conventional commit message | Staged files only |
| 9. PR | `gh pr create` with summary + test plan | Ready for review |
