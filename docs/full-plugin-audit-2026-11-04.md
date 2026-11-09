# Daymark Subscriptions — Full Plugin Audit

**Audit date:** 2026-11-04  
**Scope:** Static code, database, REST/SDK, payment, subscription lifecycle, admin UI, member UI, security, uninstall, documentation and testing readiness.  
**Mode:** Read-only audit; no code changes were made.

## Verdict

The plugin has a workable foundation, but it is not production-ready yet. The highest risks are WooCommerce recurring billing, Stripe period-end plan-change billing, guest checkout identity handling, provider reconciliation, and refund/chargeback synchronization.

## High-risk findings

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| F-01 | High | WooCommerce native recurring payment is not implemented. Renewal processing does not charge the customer; the subscription becomes `past_due` or is canceled at period end. | `includes/class-daymark-payment-provider.php:132`, `includes/class-daymark-subscriptions-db.php:346` |
| F-02 | High | Period-end Stripe plan changes are applied during `invoice.payment_succeeded` using `always_invoice`, creating a risk of duplicate or incorrectly timed charges. | `includes/class-daymark-stripe.php:120`, `includes/class-daymark-stripe.php:186` |
| F-03 | High | Guest checkout can reuse an existing account by email without ownership verification and update that account's phone metadata. | `includes/class-daymark-subscriptions-rest.php:223` |
| F-04 | High | Refunds, chargebacks, disputes, and void/uncollectible invoice events are not synchronized to local subscription state. | `includes/class-daymark-stripe.php:72` |
| F-05 | High | Editing an existing plan does not invalidate its Stripe Price mapping. A changed plan price can continue charging through the old Stripe Price. | `includes/class-daymark-stripe.php:50`, `includes/class-daymark-subscriptions-rest.php:109` |

## Medium-risk findings

- Provider API updates can succeed while the local database commit fails; no compensating action or reconciliation process exists.
- Payment-method update, Stripe Customer Portal, failed-payment recovery, retry schedule and grace-period policy are missing.
- Invoice email sends a plain-text summary rather than a guaranteed provider invoice document or invoice link.
- WP-Cron is the only renewal scheduler, so renewals are traffic-dependent on sites without a persistent cron runner.
- The settings REST API still accepts `profile_picture_url`, although the UI is intended to use file uploads only.
- The uninstall script does not drop the invoices table and leaves some options/user metadata behind. Evidence: `uninstall.php:10-30`.
- Access & Entitlements was removed from the active menu/runtime, but old admin methods, redirects, dashboard links and documentation references remain. Evidence: `includes/class-daymark-subscriptions-admin.php:209`, `includes/class-daymark-subscriptions-admin.php:240`.
- `readme.txt` has an outdated stable tag and still documents removed access/feature functionality. Evidence: `readme.txt:7`, `readme.txt:65-69`.

## Missing complete processes

### Billing

- Refund and partial-refund handling
- Chargeback and dispute handling
- Failed-payment retry policy
- Grace period and access-status policy
- Payment-method update flow
- Customer billing portal
- Tax/VAT handling
- Provider-supported currency validation
- Provider reconciliation job

### Subscription lifecycle

- Correct provider-side scheduling for period-end upgrades/downgrades
- Admin audit log for revoke and plan-change actions
- Bulk subscriber actions
- Restore/reactivate canceled subscriptions
- Expired-subscription cleanup
- Subscription migration when a plan is archived
- Duplicate-subscription repair flow

### WooCommerce

- Native recurring charge adapter
- Provider-side plan upgrade/downgrade synchronization
- Provider-side pause/resume synchronization
- Renewal order/webhook reconciliation
- Renewal invoice creation

### Admin and operations

- Automated test suite
- PHPUnit tests
- REST contract tests
- Browser/E2E tests
- Stripe/WooCommerce sandbox fixtures
- Database migration tests
- Failed webhook replay UI
- Event/job monitoring dashboard
- Admin audit trail

## Working areas

- PHP syntax passed for all plugin PHP files.
- REST permission checks generally use `manage_options` or authenticated-user checks.
- Stripe webhook signature verification and event replay storage exist.
- Checkout idempotency keys exist.
- Transactions and user-row locking are used in several lifecycle paths.
- Profile image uploads have size and file-type checks.
- Plan deletion protects plans referenced by subscriptions or invoices.
- Development mode and Stripe Sandbox/Live separation exist.

## Verification boundary

- Static code audit: completed.
- PHP lint: PASS.
- Live Chrome audit: NOT RUN.
- Real database test: NOT RUN.
- Stripe sandbox payment/webhook test: NOT RUN.
- WooCommerce checkout/renewal test: NOT RUN.
- Refund/chargeback test: NOT RUN.
- Automated security scanner: NOT RUN because project rules prohibit running Python/server tooling in this session.

## Recommended priority

1. Fix Stripe plan-change billing transition.
2. Decide and implement native WooCommerce recurring billing.
3. Add refund, chargeback and payment-failure synchronization.
4. Secure guest checkout for existing email accounts.
5. Invalidate/recreate Stripe Price mappings after plan edits.
6. Add provider reconciliation and admin audit logs.
7. Add PHPUnit, REST, Stripe and WooCommerce integration tests.
8. Remove stale Access & Entitlements code/documentation and complete uninstall cleanup.
