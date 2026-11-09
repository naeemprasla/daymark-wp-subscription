# Daymark Subscriptions — Admin and Member Settings Architecture

**Status:** Working specification  
**Date:** 2026-11-09  
**Scope:** Super-admin, delegated admin, and member settings.  
**Security baseline:** Server-side authorization, ownership checks, input allowlists, idempotency for billing mutations, and no provider secrets in browser responses.

This document converts the settings recommendations into an implementation checklist. The canonical product architecture remains `docs/product-architecture-recommendations.md`; this file does not replace it.

## 1. Super-admin settings

### General and business

- Business/site display name
- Customer support email
- Logo and brand tokens
- WordPress timezone and date/amount formatting
- Checkout, login, profile, subscription-management, privacy/security, and thank-you pages

### Payment and environment

- Development or Production mode
- Stripe Sandbox or Live mode
- Mode-specific Stripe secret and webhook credentials
- Connection test and webhook readiness
- Stripe customer portal, automatic tax, and payment recovery
- WooCommerce selected gateway, tokenization capability, renewal adapter, and manual-renewal warning

Provider credentials are server-only. Production checkout is blocked until provider readiness passes.

### Plan and pricing controls

Each plan supports:

- Name and auto-generated slug
- Short/WYSIWYG description
- Price, billing interval, trial, intro pricing, setup fee, and tax behavior
- Quantity/seats and limits
- Promotion codes
- Recommended flag, public visibility, active/archive state, and CTA label
- Assigned features
- Direct approval or admin review
- Pause/cancellation policy, minimum commitment, plan-change timing, and refund policy

Currency is global; plans do not independently override it. Historical subscription snapshots never change when a plan is edited.

### Subscription lifecycle policy

- Default approval mode
- Payment grace period
- Retry count and retry schedule
- Immediate or period-end cancellation
- Pause availability, maximum duration, and resume behavior
- Immediate or period-end upgrades
- Period-end downgrades
- Refund eligibility
- Abandoned checkout, pending activation, and account-deletion retention

### Notifications

- Admin recipient, sender, and reply-to
- Test email
- HTML template editor with validated placeholders
- Event enable/disable controls
- Delivery log, failure state, and resend action

Events include checkout, payment, activation, review, renewal, grace period, pause/resume, cancellation, expiry, invoice, refund, dispute, and account deletion.

### Operations

- Provider readiness and webhook health
- Pending approvals and pending payments
- Failed renewals and reconciliation queue
- Dead-letter operations and failed scheduled jobs
- Webhook event log and replay
- Invoice/payment timeline
- Duplicate subscription review
- Audit log and retry controls

### Privacy and security

- Delegated admin capabilities
- Second-admin approval for refunds, revokes, and sensitive actions
- Data export and deletion policy
- Financial-history retention
- Security and recovery notifications
- Rate-limit policy visibility

Use dedicated capabilities:

- `manage_daymark_subscriptions`
- `manage_daymark_billing`
- `review_daymark_subscriptions`
- `view_daymark_reports`

## 2. Member settings

### Profile

- First name, last name, and display name
- Profile description
- Profile-picture upload and crop
- Phone number
- Public profile visibility
- Email verification state

### Subscription and billing

- Current plan, status, amount, interval, and billing period
- Next charge date
- Trial or introductory-price state
- Payment status and grace-period warning
- Pending plan change
- Upgrade/downgrade preview
- Pause/resume and cancellation actions when supported
- Provider billing portal and payment recovery
- Invoice history and payment/lifecycle timeline
- Refund policy and effective-date explanation

### Privacy and security

- Password reset
- Verified email change
- Active-session logout
- Data export
- Account deletion request and consequences
- Billing-history retention explanation
- Recovery/security notifications
- Product-update preference

MFA/passkey support remains a future phase.

## 3. Explicitly forbidden for members

- Stripe or WooCommerce credentials
- Webhook secrets or raw payment tokens
- Raw card data
- Other members' subscriptions or invoices
- Plan price, entitlement, or global policy mutation
- Admin review, refund, access-rule, or provider configuration controls

## 4. Permission and UX rules

- Every mutation is authorized on the server for the current user and resource.
- Member actions operate on the current member by default; cross-member access requires the matching admin capability.
- Nonces provide CSRF protection only; they never replace capability checks.
- Destructive actions require confirmation and a reason where applicable.
- Billing actions use idempotency keys and show a loading/recovery state.
- Unsupported provider actions are hidden or explained as unavailable.
- Secrets are masked, never returned after save, and never included in frontend payloads.

## 5. Implementation order

1. Normalize admin tabs and delegated capability boundaries.
2. Add missing general/business settings using WordPress-native defaults.
3. Complete plan-level policy fields and previews.
4. Add configurable retry/retention policy controls.
5. Add notification delivery log and resend workflow.
6. Complete member session, recovery, privacy, and deletion-retention UI.
7. Verify each route with ownership, IDOR, nonce, rate-limit, and destructive-action tests.

## 6. Acceptance criteria

- Admin and member settings are clearly separated.
- No member can read or mutate another member's data.
- Provider secrets never reach HTML, JavaScript, REST responses, logs, or SDK output.
- Every billing mutation is idempotent and auditable.
- Settings use safe defaults and preserve historical billing data.
- Unsupported provider capabilities are not presented as working controls.
- PHP lint, contract, integration, lifecycle, webhook, browser, email, and cron checks are recorded separately.
