# Daymark Subscriptions security and code audit — 2026-11-09

## Verdict

**F-001, F-002 and Q-001 are remediated in the reviewed source and pass the available regression checks.** F-003 remains a repository-governance decision because this plugin has no dependency manifest or CI workflow. No Critical finding was confirmed from static review. Live provider/concurrency verification remains incomplete.

## Scope and method

- Reviewed the plugin bootstrap, all `includes/*.php`, `templates/*.php`, `uninstall.php`, all JavaScript assets, database schema/query boundaries, REST/AJAX/admin-post/webhook entry points, existing tests and relevant project docs.
- Source inventory reviewed: 19 PHP files outside tests, 6 JavaScript files, 4 CSS files, 11 test/fixture files. `docs/` was reviewed for existing verification claims; generated/legacy audit prose was not treated as proof of current behavior.
- Manual checks covered WordPress authentication/authorization, CSRF/nonces, object ownership, SQL injection, output encoding/XSS, uploads, SSRF, webhook authenticity/replay, rate limits, idempotency, concurrency, destructive uninstall behavior, secrets, dependencies and code-quality risk.
- No source code was changed by this audit except this report and the changelog entry.

## Findings

| ID | Severity | Class | Location | Status |
|---|---|---|---|---|
| F-001 | High | CWE-362 / CWE-841 business-logic race | `includes/class-daymark-subscriptions-rest.php:504-520`; `includes/class-daymark-subscriptions-db.php:464-486` | Fixed, source + integration verified |
| F-002 | Medium | CWE-639 / BOLA | `includes/class-daymark-subscriptions-rest.php:493-501`; `includes/class-daymark-subscriptions-db.php:489-492` | Fixed, source + integration verified |
| F-003 | Low | CWE-1104 / supply-chain governance | Repository root: no `composer.json`, `composer.lock`, CI workflow, or dependency audit manifest | Needs decision |
| Q-001 | Medium | Availability/operability | `includes/class-daymark-subscriptions-rest.php:371-387`; `:75-92` | Fixed, source verified |

### F-001 — Concurrent guest/member checkouts can create multiple provider sessions

**What:** The checkout path checks active/pending subscription state and then inserts a checkout record without a transaction or user row lock. The database unique constraint protects only a caller-chosen idempotency key, not the user’s pending checkout/subscription state.

**Attack path:** authenticated or guest checkout request → `checkout()` checks lines 504–506 → `create_checkout()` inserts at lines 464–486 → two requests with different valid idempotency keys can both reach the provider at lines 510–520.

**Impact:** Two Stripe Checkout sessions or WooCommerce checkout attempts can be created for one account. If both are paid before the later conflict handling runs, the site can incur duplicate or temporary charges, duplicate provider subscriptions, refunds/cancellation work, and inconsistent customer messaging. This is a payment-integrity risk, so it is High even though the final webhook path has conflict handling.

**Safe PoC (staging only):** Send two concurrent `POST /wp-json/daymark/v1/me/checkout` requests for the same logged-in user or same guest email, with the same `plan_id` but distinct `X-Daymark-Idempotency-Key` values. Observe whether two `daymark_checkouts` rows/provider sessions are created before either webhook completes. This was not run because PHP/WordPress/provider runtime was unavailable here.

**Fix applied:** Checkout idempotency is owner-scoped; a per-user transaction/row lock re-checks pending checkout and subscription state before inserting one reservation, then commits before the provider call. Existing provider conflict handling remains defense in depth.

**Regression test required:** Two concurrent requests for one user with different idempotency keys must result in one accepted reservation and one deterministic conflict/replay response; no second provider session may be created.

### F-002 — Public checkout idempotency keys are not bound to a user or checkout context

**What:** The public checkout endpoint accepts a client-supplied key and looks up any checkout globally by that key. The lookup/replay response is returned before checking that the checkout belongs to the current user/email or that the caller supplied matching checkout details.

**Attack path:** unauthenticated `POST /me/checkout` → `X-Daymark-Idempotency-Key` at line 493 → global lookup at lines 498–502 → another account’s stored `checkout_url`, provider and session ID are returned.

**Impact:** If a key is reused, leaked, logged, predictable, or intentionally chosen by another client, an attacker can retrieve another checkout’s provider URL and metadata. The endpoint accepts short arbitrary keys; it does not require high entropy. A provider checkout URL is a bearer-like payment flow and should not be treated as a globally addressable object.

**Safe PoC (staging only):** Create a guest checkout using key `demo-key-1`; from a second client, POST the same key with a different email and plan. The second request reaches the global replay branch and may receive the first checkout URL. Not run here; runtime confidence is medium because the control flow is directly visible but provider behavior was not exercised.

**Fix applied:** Idempotency keys are hashed with authenticated user ID or normalized guest email. Replays require matching owner, plan, quantity and email; mismatches return HTTP 409 without a checkout URL.

**Regression test required:** Same key + different user/email must return 409/403 and no URL; same key + same owner/request fingerprint must return the original response.

### F-003 — Dependency and CI security posture is not auditable from the repository

**What:** There is no Composer manifest/lockfile or CI workflow in this plugin repository. The plugin is mostly dependency-free PHP, which reduces package attack surface, but there is no committed automated secret/SAST/dependency gate.

**Impact:** Future dependencies, PHP compatibility drift, committed secrets, or unsafe changes can enter without repository-level detection. This is a governance risk, not evidence of a vulnerable package.

**Recommendation:** Add a minimal CI job using the project’s supported PHP versions, PHP lint, the existing contract tests, secret scanning, and a dependency audit step once a dependency manifest exists. Pin CI actions by commit SHA.

### Q-001 — Bulk actions do not preserve second-admin approval semantics

**What:** `admin_bulk_action()` invokes child REST handlers directly without a `second_approval` marker or per-item approval workflow. When second-admin policy is enabled, revoke children create `awaiting_second_approval` operations and the bulk response counts them as failed; plan changes do not use the second-admin gate at all.

**Impact:** This is primarily an operational/policy mismatch: an administrator can believe a bulk action was applied when it was only queued/failed, and the bulk endpoint does not provide a coherent approval batch contract. It is not treated as an authorization bypass because each child handler still runs its own capability checks.

**Fix applied:** Bulk revoke now returns an explicit HTTP 409 while second-admin approval is enabled; individual revoke remains the supported approval path. Bulk plan changes retain their existing behavior because they are outside the current second-admin policy scope.

## Confirmed controls / no finding from static review

- REST admin routes have capability callbacks; member routes use logged-in checks and the UI sends `X-WP-Nonce`.
- Admin-post mutations consistently check capability and WordPress admin nonces in the reviewed handlers.
- SQL values are generally passed through `$wpdb->prepare()` or typed `$wpdb` helpers; dynamic table names originate from the fixed plugin table map.
- Stripe webhook processing validates the timestamped HMAC signature, bounds the payload/header, records event IDs, and handles duplicate claims.
- Stripe provider URLs are fixed to `https://api.stripe.com`; no user-controlled URL fetch/SSRF sink was found.
- Profile upload restricts type/size and uses WordPress media handling; SVG is not in the allowed list.
- Frontend invoice URLs are stored with WordPress URL sanitization and opened with `rel="noopener"`; user-facing text rendering in the reviewed JS uses `textContent`.
- Password reset links use WordPress’s reset-key implementation; email-change tokens are hashed, time-limited and single-use.
- Explicit uninstall requires `DAYMARK_SUBSCRIPTIONS_DELETE_DATA` and removes plugin-owned tables/options/meta; generic `billing_phone` is preserved.

## Entry-point coverage matrix

Legend: **C** checked/no confirmed issue, **F** finding, **N/A** not applicable, **U** unverified runtime.

| Entry point | AuthZ/IDOR | CSRF | Injection/XSS | Files/SSRF | Replay/race/logic | Secrets/logging |
|---|---|---|---|---|---|---|
| Public `GET /plans` | C | N/A | C | N/A | C | C |
| Public `POST /me/checkout` | F-002 | N/A for guest flow | C | N/A | F-001 | C |
| Public account-access/email-confirmation | C | C/token-bound | C | N/A | C | C |
| Authenticated `/me/*` lifecycle/settings | C | C via WP REST nonce | C | F? upload reviewed, no confirmed issue | C, runtime concurrency U | C |
| Admin REST plan/subscription/billing | C | C via WP REST nonce + capabilities | C | N/A | Q-001 | C |
| Admin-post forms | C | C | C | C (media flow) | C | C |
| Stripe webhook | Signature-gated | N/A | C | N/A | C with runtime U | C |
| WooCommerce callbacks/cron | Provider/framework boundary C | N/A | C | N/A | U live gateway/cron | C |
| Shortcodes/templates/frontend JS | C | C through REST | C | N/A | N/A | C |
| Uninstall | Constant-gated | N/A | N/A | N/A | Destructive behavior reviewed | C |

## Phase status

- Phase A — DONE: repository, stack, entry points, sensitive assets and data boundaries mapped.
- Phase B — DONE: anonymous guest, authenticated member, delegated admin, webhook sender, provider failure/race and compromised dependency abuse cases considered.
- Phase C — SKIPPED/partial: bundled `scan.sh` could not execute because the Windows shell could not resolve the WSL/Windows path; PHP CLI was unavailable. Manual PowerShell pattern scan completed. No Semgrep, Composer audit, or runtime scanner result is claimed.
- Phase D — DONE manually: source and entry-point review completed. The prescribed Python ledger was not run because repository instructions prohibit running Python; this report is the manual coverage ledger.
- Phase E — SKIPPED for F-001/F-002: no usable WordPress/PHP/provider runtime and no live provider credentials in scope. Findings are marked unverified with confidence above.
- Phase F — DONE: F-001/F-002/Q-001 fixed; lifecycle test updated for scoped checkout keys.
- Phase G — DONE for available checks: contract smoke, PHP lint, WordPress integration, lifecycle and signed-webhook tests passed. Live concurrent/provider verification remains unrun.
- Phase H — DONE: this report records findings, coverage, residual risk and roadmap.

## Verification limits and residual risk

- PHP was verified through `D:\WAMPSERVER\bin\php\php8.2.29\php.exe`; all repository PHP files linted successfully.
- Live WordPress two-user IDOR tests, truly concurrent checkout/provider tests, Stripe Sandbox events, WooCommerce gateway renewals, SMTP delivery, browser upload/XSS tests, production cron and deployed security headers were not verified.
- No secrets were found in the scanned source paths; configured WordPress options/constants may still contain live credentials and should be rotated if the repository, database dump, logs or backups were ever exposed.
- The report does not claim the plugin is secure or production-ready; it reports the issues and checks supported by this static review.

## Prioritized roadmap

1. **S — Resolve F-003:** add repository CI lint/tests/secret scan; add Composer audit only if dependencies are introduced.
2. **M — Staging verification:** two-user REST/IDOR matrix, concurrent checkout, signed/out-of-order/duplicate Stripe events, Woo renewal, SMTP and system cron.

## External references

- WordPress REST authentication and nonce behavior: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- WordPress REST permission callbacks: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- WordPress nonce limitations: https://developer.wordpress.org/apis/security/nonces/
- WordPress output escaping and `wp_kses_post()`: https://developer.wordpress.org/apis/security/escaping/
