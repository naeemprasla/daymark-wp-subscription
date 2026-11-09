# Daymark test fixtures

`test-contracts.php` is a dependency-free static contract smoke test. It validates high-risk source contracts and parses the checked-in Stripe webhook and WooCommerce renewal payload fixtures.

Run it from the plugin directory with:

```powershell
D:\WAMPSERVER\bin\php\php8.2.29\php.exe tests/test-contracts.php
```

The fixtures are shape fixtures only. They do not prove a live provider signature, database transaction, gateway charge, or browser checkout.

`test-wp-integration.php` is a read-only WordPress bootstrap and permission smoke test. `test-wp-lifecycle.php` is an isolated local lifecycle test; it temporarily switches the option-backed environment to Development, creates temporary hidden plans and a guest account, verifies checkout/invoice/access/pause/resume/plan-change/cancellation, and removes its data before exiting. Do not run the lifecycle test against a site with a locked production environment or a shared production database.

`test-wp-webhooks.php` is an isolated signed-webhook test. It uses the checked-in Stripe invoice fixtures with a temporary option-backed webhook secret and verifies signature acceptance, duplicate suppression, paid renewal, failed renewal recovery state, and cleanup. It does not contact Stripe.
