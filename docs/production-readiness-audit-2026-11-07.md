# Daymark Subscriptions production-readiness audit — 2026-11-07

## Scope

This audit covers the current checkout at plugin version 1.29.25 after implementing the launch plan's highest-risk local changes. It does not claim live Stripe, WooCommerce, SMTP, browser, or deployed-cron verification.

## Implemented in this pass

- Guest checkout accounts are marked `pending_payment`; the plugin no longer sends WordPress's normal new-user email before payment.
- Verified provider payment moves the account to `pending_activation`; protected subscription access is denied until the user completes the single-use WordPress password-reset claim flow.
- Password claim moves the account to `active`, removes the abandoned-checkout marker, and writes an audit entry.
- Development bypass explicitly marks the local test account active so development lifecycle tests remain useful without representing production payment proof.
- WooCommerce production checkout now requires an enabled gateway that advertises tokenization/subscriptions and the `daymark_woocommerce_renewal_charge` adapter. No compatible adapter means readiness is false and checkout fails closed.
- Stripe invoice-finalized, invoice-paid, payment-action-required, duplicate-event, and provider-managed renewal paths now have explicit handling; WooCommerce renewal jobs create durable operation records and update them from payment callbacks.
- Added `/daymark/v1/admin/readiness` and server SDK helpers for `checkout_status()` and `provider_capabilities()`.
- Uninstall cleanup owns only Daymark metadata; generic `billing_phone` is not removed.

## Evidence

| Check | Result | Evidence |
|---|---|---|
| PHP lint | PASS | All PHP files lint with WAMP PHP 8.0.30 |
| Contract smoke | PASS | `tests/test-contracts.php` |
| WordPress integration | PASS | `tests/test-wp-integration.php` |
| Development lifecycle | PASS | `tests/test-wp-lifecycle.php`, including pending activation isolation and claim activation |
| Signed Stripe webhook fixture | PASS | `tests/test-wp-webhooks.php` |
| Provider live transaction | NOT_RUN | Requires Stripe Sandbox/Live credentials and provider dashboard |
| Woo tokenized renewal | NOT_RUN | Requires a compatible installed gateway adapter and sandbox account |
| SMTP activation delivery | NOT_RUN | Requires configured SMTP/mail catcher or staging mailbox |
| Browser responsive/accessibility | NOT_RUN | Browser transport was not available in this execution |
| Production server cron | NOT_RUN | Requires deployed Task Scheduler/cron invocation |

## Remaining launch blockers

1. Run Stripe Sandbox checkout, signed webhook, duplicate/out-of-order events, failed renewal, recovery, refund, dispute, and cancellation tests.
2. Install and verify the selected WooCommerce tokenized recurring gateway adapter, or keep WooCommerce Production disabled.
3. Configure HTTPS webhook delivery, SMTP, system scheduler, backups, monitoring, and rollback access in staging.
4. Complete two-user REST/IDOR and browser access-rule tests in a runtime environment.
5. Review legal/tax/refund/grace-period policy values before enabling Live mode.

## Release decision

**NOT READY FOR PRODUCTION LAUNCH** based on unavailable external evidence. Local code and regression checks pass for the implemented scope; production enablement remains blocked until the external checks above are PASS or explicitly risk-accepted by the owner.
