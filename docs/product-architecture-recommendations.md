# Daymark Subscriptions — Product Architecture Recommendations

**Date:** 2026-11-09  
**Scope:** Product model, admin options, member journey, subscription lifecycle, and security recommendations.  
**Code changes:** None. This document records design recommendations only.

## Product direction

Daymark should be developed as a provider-neutral subscription lifecycle system, not only as a plans and checkout plugin.

The core principle is:

> Access is granted only after verified payment and account activation. Provider state and signed webhooks are the billing source of truth; browser redirects are only user-interface signals.

## Subscription state model

| State | Meaning |
| --- | --- |
| `pending_checkout` | Guest details were submitted, but payment is not complete. |
| `pending_review` | The plan requires administrator approval. |
| `payment_pending` | Provider checkout or payment is incomplete. |
| `pending_activation` | Payment is verified, but the member has not claimed the account. |
| `active` | Subscription is active and access is available. |
| `paused` | Subscription is temporarily paused. |
| `past_due` | A payment failed and the grace period is active. |
| `canceled` | The subscription has ended through cancellation. |
| `expired` | The grace period or access period has ended. |
| `recovery_required` | Provider and local billing state do not match and require repair. |

Historical subscription records should preserve the original price, currency, interval, quantity, and plan snapshot. Editing a current plan must never rewrite billing history.

## Recommended administrator options

### General setup

- Currency
- Business/site name
- Customer support email
- Timezone
- Date and amount formatting
- Checkout, login, profile, and subscription-management pages

Currency should be configured globally. Plans should define prices in that configured currency.

### Payment providers

#### Stripe

- Sandbox and Live modes
- Separate keys and webhook secrets per mode
- Webhook endpoint display
- Connection test
- Required webhook checklist
- Stripe customer portal
- Automatic tax
- Payment recovery URL
- Webhook replay
- Failed-event queue

Production checkout should remain blocked until credentials, webhook secret, HTTPS, required events, and connection health are ready.

#### WooCommerce

- Selected gateway
- Gateway enabled state
- Tokenization capability
- Recurring/subscription capability
- Renewal adapter status
- Payment-token ownership validation
- Failed-renewal recovery
- Manual-renewal warning

Raw card data should never be stored. WooCommerce tokenized gateways should be used through the documented Payment Token API.

#### Development mode

- Provider calls bypassed
- Test subscriptions and invoices
- Clearly visible testing-only warning
- Production environment hard-block

### Plan configuration

Each plan should support:

- Name and auto-generated slug
- Short description
- WYSIWYG description
- Billing interval and price
- Trial days
- Introductory price and cycle count
- Setup fee
- Tax behavior
- Quantity/seat support and limits
- Promotion codes
- Recommended-plan flag
- Public visibility
- Active/archive state
- CTA label
- Assigned feature display
- Direct activation or admin-review approval
- Cancellation and pause policy
- Minimum commitment cycles
- Immediate or period-end plan changes
- Refund policy copy

### Subscription lifecycle policy

Administrators should control:

- Default approval mode
- Payment grace period
- Failed-payment retry count and schedule
- Immediate or period-end cancellation
- Pause availability and maximum duration
- Resume availability
- Immediate or period-end upgrades
- Period-end-only downgrades
- Refund eligibility
- Trial-ending reminders
- Intro-price-ending reminders
- Abandoned-checkout retention
- Pending-activation expiry
- Account-deletion retention

### Notifications

Notification templates should cover:

- Checkout started
- Payment successful
- Payment failed
- Payment action required
- Activation link
- Review requested, approved, and rejected
- Subscription activated
- Renewal successful or failed
- Grace period started
- Subscription paused, resumed, canceled, or expired
- Invoice created
- Refund processed
- Dispute received
- Account deletion approved

HTML templates should expose validated dynamic placeholders such as:

```text
{{member_name}}
{{plan_name}}
{{subscription_status}}
{{next_charge_at}}
{{invoice_url}}
{{activation_url}}
{{payment_recovery_url}}
```

### Operations and recovery

The admin dashboard should include:

- Provider readiness
- Pending approvals
- Pending payments
- Failed renewals
- Reconciliation queue
- Dead-letter operations
- Failed scheduled jobs
- Webhook event log
- Invoice/payment timeline
- Audit log
- Account-deletion requests
- Email delivery and resend status

Every billing operation should retain an internal operation ID, idempotency key, subscription ID, provider response, local persistence result, attempts, last error, retry time, and recovery state.

## Visitor journeys

### Direct-approval plan

1. Visitor opens the Plans page.
2. Visitor reviews price, currency, interval, trial, tax, and setup fee.
3. Visitor selects a plan.
4. Guest checkout collects name, email, phone, and quantity when enabled.
5. The server validates the plan and amount again.
6. A `pending_checkout` record is created.
7. Hosted Stripe or WooCommerce checkout opens.
8. Provider payment is verified.
9. Webhook processing activates the local subscription.
10. The member receives an activation/password-claim email.
11. The member claims the account.
12. The account becomes active and invoices/timeline become available.

### Admin-review plan

1. Visitor submits plan and contact details.
2. A `pending_review` record is created.
3. No payment is charged before approval.
4. The administrator receives a review notification.
5. The administrator approves or rejects the request.
6. Approval sends a secure payment link.
7. Verified payment triggers activation.
8. Rejection prevents access and records the reason.

### Failed-payment journey

1. Renewal payment fails.
2. Subscription becomes `past_due`.
3. Member receives a clear payment-failure message.
4. Grace period begins.
5. Member opens the Stripe billing portal or WooCommerce payment link.
6. Successful recovery returns the subscription to `active`.
7. Expired grace period changes access according to policy.

## Member experience

### Member dashboard

Show:

- Current plan
- Subscription status
- Current billing period
- Next charge date
- Recurring amount
- Provider and payment state
- Trial or introductory-price state
- Grace-period warning
- Pending plan change
- One clear next action

### Subscription management

Members should be able to:

- View the current plan
- Preview and request upgrades/downgrades
- See effective date and next charge
- Pause and resume when supported
- Cancel immediately or at period end when allowed
- Recover failed payments
- Open the provider billing portal
- View and open invoices
- Review the payment/lifecycle timeline
- See refund and dispute status

Unsupported WooCommerce actions should be hidden or explained as unavailable rather than rendered as broken controls.

### Profile and settings

- First name
- Last name
- Display name
- Profile description
- Profile-picture upload and crop
- Email management
- Phone number
- Product-update preference
- Email-verification state

The public profile should remain content-focused: image, name, description, and relevant member status only.

### Privacy and security

- Password reset
- Active-session logout
- Verified email change
- Notification preferences
- Data export request
- Account deletion request
- Deletion consequences
- Subscription and billing retention explanation
- Recovery/security notifications
- Optional MFA/passkey support in a future phase

## Admin operating flow

1. Complete setup wizard.
2. Configure currency and business details.
3. Configure Stripe Sandbox.
4. Verify webhook delivery.
5. Create the first plan.
6. Preview checkout.
7. Test the full lifecycle in Development mode.
8. Run Sandbox payment and webhook tests.
9. Test failed renewal and recovery.
10. Test approval and rejection.
11. Verify email delivery.
12. Pass the production-readiness checklist.
13. Enable Stripe Live.
14. Unlock production checkout.

Overview should be an operational control surface, showing active subscriptions, pending approvals, payment attention, failed/reconciliation items, recent activity, webhook health, job health, and provider readiness.

## Security rules

- Never trust browser-supplied price, amount, entitlement, or provider identifiers.
- Enforce permission and ownership checks on every REST mutation.
- Treat WordPress nonces as CSRF protection, not authorization.
- Do not silently mutate an existing account through guest checkout.
- Grant access only after verified payment and activation.
- Verify webhook signatures, timestamps, and replay state.
- Handle duplicate checkout and duplicate webhook events idempotently.
- Keep provider keys out of browsers, logs, SDK responses, and frontend payloads.
- Do not store raw card data.
- Restrict invoice access to the owning member or authorized administrator.
- Require confirmation and a reason for destructive admin actions.
- Rate-limit login, recovery, activation, and resend endpoints.
- Anonymize profile data on deletion without destroying financial history.
- Use integer smallest-unit or decimal-safe money calculations.
- Mark provider/local mismatches as `recovery_required` rather than silently succeeding.

## Priority recommendations for the current plugin

The current plugin already has a strong foundation: provider separation, Development mode, approval flow, invoices, reconciliation, SDK boundaries, and provider capability checks.

The highest-value product decisions to lock are:

1. Treat `pending_checkout` as the primary guest identity state and do not grant normal account access before payment verification.
2. Keep verified payment and password activation as separate states.
3. Show WooCommerce automatic recurring billing only when a compatible tokenized renewal adapter exists.
4. Explain the next result before every member action.
5. Require plan-change previews where provider proration is available.
6. Keep Overview focused on recovery and operational risk rather than decorative metrics.
7. Make email delivery logs and resend controls first-class features.
8. Use provider readiness as a hard checkout gate.
9. Keep the add-on SDK read-heavy and permission-scoped; do not expose raw billing writes.
10. Connect every state transition to a timeline entry, audit event, and appropriate notification.

## Sources

- [Stripe subscription lifecycle](https://docs.stripe.com/billing/subscriptions/overview)
- [Stripe webhooks](https://docs.stripe.com/webhooks)
- [Stripe idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [WooCommerce Payment Token API](https://developer.woocommerce.com/docs/features/payments/payment-token-api/)
- [WordPress Cron](https://developer.wordpress.org/plugins/cron/)
- [WordPress nonce guidance](https://developer.wordpress.org/apis/security/nonces/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
