# Subscription System — Missing Features & Flow Findings

**Review date:** 2026-11-05  
**Scope:** Current subscription, checkout, payment, member, admin, SDK, REST, and operational flows.

## Summary

The core subscription foundation is implemented: Stripe modes, approval workflow, payment recovery, lifecycle actions, plan management, invoices, branding, privacy controls, provider health, admin subscriber operations, and the server-side SDK are present.

The following gaps remain important for a production-grade subscription system.

## Critical production gaps

### 1. WooCommerce recurring billing contract

WooCommerce checkout and renewal-order flows exist, but automatic recurring charging depends on the configured gateway and adapter capability.

Required process:

- Detect whether the selected gateway supports saved payment tokens and automatic renewals.
- Block or warn before enabling a plan when automatic renewal is unavailable.
- Track renewal payment status and failed renewal state.
- Provide a manual payment recovery path.
- Show clear member/admin messaging about gateway limitations.

### 2. Provider/local state reconciliation

A provider operation can succeed while the local database update fails. Audit logging alone is not enough to repair this state safely.

Required process:

- Create an operation ID and idempotency key.
- Store provider result and local result separately.
- Add a reconciliation queue with retry state and last error.
- Show unresolved mismatches to administrators.
- Provide a safe manual repair action.

### 3. Failed scheduled-job retry system

Cron monitoring exists, but a complete failed-job workflow is still needed.

Required options:

- Last attempt and error message.
- Retry count and next retry time.
- Exponential backoff.
- Manual retry.
- Dead-letter/failed state.
- Admin acknowledgement after resolution.

### 4. Runtime integration and end-to-end tests

Static contract tests are not sufficient for payment and subscription lifecycle confidence.

Required test coverage:

- Guest checkout and account creation.
- Admin approval and rejection.
- Stripe sandbox webhook processing.
- Failed renewal and payment recovery.
- Upgrade, downgrade, pause, resume, and cancellation.
- WooCommerce renewal behavior.
- Invoice creation and member invoice access.
- Access denial and entitlement enforcement.
- Cron recovery and duplicate webhook handling.
- REST permissions, nonce/authentication, and role boundaries.

## Member and account-flow gaps

### 5. Guest account recovery

Guest checkout creates an account, but the complete account-claim experience should include:

- Set-password link after checkout.
- Resend login/access link.
- Existing-email handling.
- Explicit abandoned-checkout state.
- Account claim confirmation screen.

### 6. Email-change confirmation hardening

The email confirmation flow should avoid performing a state-changing action directly from a public GET link.

Recommended flow:

1. Email link opens a confirmation page.
2. User sees the new email and expiry state.
3. User explicitly clicks Confirm.
4. A POST request completes the change.

The page should clearly handle expired, already-used, invalid, and successful tokens.

### 7. Subscription change preview

Before an upgrade or downgrade, members should see:

- New plan and price.
- Effective date.
- Immediate versus period-end behavior.
- Proration amount, when applicable.
- Next billing amount and date.
- Existing plan end date.

### 8. Subscription billing timeline

Members and administrators need a single chronological timeline for:

- Payment attempts and outcomes.
- Invoice generation.
- Renewal retries.
- Approval or rejection.
- Pause, resume, cancellation, and plan changes.
- Administrative revoke or billing actions.

### 9. Cancellation policy and feedback

Cancellation should capture a reason and clearly explain:

- Immediate versus period-end cancellation.
- Access end date.
- Refund policy.
- Optional feedback.
- Confirmation email and next steps.

## Pricing and catalog gaps

Depending on the product model, the following options may still be required:

- Coupon and promotion codes.
- One-time setup fee.
- Quantity or seat-based pricing.
- Minimum commitment period.
- Per-plan tax behavior.
- Proration preview.
- Trial-ending reminders.
- Introductory-price expiry warnings.
- Plan archiving instead of hard deletion.
- Grandfathered pricing for existing subscribers.

## Admin, security, and operations gaps

### 10. Capability-based admin roles

The system would benefit from separate capabilities for:

- Billing managers.
- Subscription reviewers.
- Support staff.
- Reporting/read-only users.

### 11. Sensitive-action approval controls

Refunds, revokes, and manual billing changes should support:

- Required reason.
- Confirmation step.
- Full audit record.
- Optional second-admin approval.

### 12. Data-retention policy

Document and, where appropriate, configure retention for:

- Invoices and billing records.
- Audit logs.
- Deleted-member data.
- Profile media.
- Abandoned checkout accounts.
- Legally required financial records.

### 13. Operational observability

Admin reporting should eventually include:

- Failed-payment rate.
- Renewal success rate.
- Webhook failures.
- Email delivery failures.
- Pending approvals.
- Reconciliation errors.
- Provider latency and error trends.

## Recommended implementation order

1. Define and enforce the WooCommerce recurring-billing capability contract.
2. Add provider/local reconciliation with durable operation records.
3. Add failed-job retry and dead-letter controls.
4. Build real WordPress, provider-sandbox, webhook, and browser integration tests.
5. Complete guest account recovery and claim flows.
6. Add plan-change previews and a unified billing timeline.
7. Add admin roles, retention controls, and operational metrics.

## Already implemented and not considered missing

The review did not identify these as current gaps:

- Stripe sandbox/live modes.
- Development/production mode.
- Admin approval workflow.
- Payment recovery and billing actions.
- Pause, resume, cancel, upgrade, and downgrade actions.
- Trials and introductory pricing.
- Plan benefits, ordering, and subscriber management.
- Privacy export/delete request flow.
- Email-change flow foundation.
- Profile picture upload/crop.
- Branding and notification-template settings.
- Provider and webhook health checks.
- Server-side SDK and REST API foundation.

## Review boundary

This is a code-level product and flow review. External payment behavior, real email delivery, WooCommerce gateway tokenization, and production cron reliability require sandbox or deployed integration tests before being treated as verified.
