# SDK Add-on Examples

These examples are patterns for independent WordPress add-ons. They do not modify Daymark core files or access its database tables.

- `member-gated-content.php` — gate an add-on feature using the canonical access check.
- `activation-event.php` — provision add-on-owned data after verified activation.
- `member-rest-endpoint.php` — expose a protected add-on REST endpoint.
- `provider-aware-ui.php` — disable an add-on action when the selected provider does not support it.
