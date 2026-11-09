# Daymark Subscriptions — admin design direction

## Brand idea

Daymark is a quiet operations tool for subscription businesses. The interface should feel like a dependable control surface: state is visible, actions are explicit, and attention is reserved for billing or access risk.

The signature mark is a small open ring beside the Daymark Subscriptions lockup. It references a daymark as a reliable point of orientation without introducing a decorative logo system or an external asset dependency. The open cut is intentional: the product manages an active lifecycle, not a static archive.

## Visual system

- Ink: `#17212b` for headings and primary data.
- Teal signal: `#087f73` for primary actions, links, and the brand mark.
- Canvas: `#f6f8f8` for form surfaces and quiet grouping.
- Border: `#dfe5e8` for structure instead of shadows.
- Warm warning: `#9a5b00` on `#fff5df` for payment attention.
- Typography: WordPress/system UI font stack; hierarchy comes from size, weight, and spacing.
- Shape: 6px panels, pill status chips only where status semantics require them.
- Rhythm: 8px-based spacing with larger 18–26px section gaps.

## Type system

The admin uses the WordPress/system UI stack so the plugin feels native, loads without a new web-font request, and respects the site's admin environment.

| Token | Size | Weight | Use |
| --- | ---: | ---: | --- |
| `--daymark-text-xs` | 11px | 700 | Table headers, compact status text, product metadata |
| `--daymark-text-sm` | 12px | 600 | Supporting labels, helper text, secondary metadata |
| `--daymark-text-md` | 14px | 400 | Body copy, inputs, table data |
| `--daymark-text-lg` | 16px | 400 | Emphasized explanatory copy when needed |
| `--daymark-title-sm` | 18px | 600 | Panel titles |
| `--daymark-title-md` | 25px | 600 | Overview banner title |
| `--daymark-title-lg` | 28px | 600 | Page title |

Headings use tight tracking and weight for hierarchy. Body copy stays left-aligned with roughly 1.5 line-height. Code and provider IDs use the system monospace stack only when the value is actually technical.

## Form design

- Labels sit above controls and use 13px/600 text with a 6px label-to-control gap.
- Text inputs, selects, and textareas share a 40px control height, 4px radius, 1px neutral border, and 10px horizontal padding.
- Focus uses the teal border plus a 3px translucent ring; color is paired with the browser focus outline removal only after the replacement ring is visible.
- Checkboxes and radios use the teal accent but retain their labels and native semantics.
- Primary buttons are 38px high, 4px radius, 13px/600, and use teal with white text. Secondary actions remain WordPress-native outlined controls.
- Modal forms inherit the same tokens even though JavaScript moves them to `document.body`.

## Card and panel design

Panels are role-based surfaces, not a repeated decoration:

- Data panels: white surface, 1px border, 6px radius, 22px padding.
- Form panels: mineral canvas background to distinguish input work from read-only data.
- Attention panels: warm warning tint and border; the label, copy, and action all state the risk.
- Metric strip: one bordered horizontal surface with dividers, not four floating cards.
- Tables: the primary record surface for subscriptions and access rules; zebra rows are subtle and borders do the structural work.

## Spacing, shape, and responsive rules

The base rhythm is 4px, with common steps at 8, 12, 16, 20, and 24px. Use 18px between adjacent panels and 26px around page-level headings. Use 4px radius for controls and 6px for panels; status chips are the only pill shape because they encode a compact state.

At 960px, metric strips become two columns and side-by-side layouts stack. At 700px, form grids become one column and wide data tables scroll within their panel rather than forcing page-wide overflow.

## State contract

## Themeable accent

The Design admin page stores one validated six-digit hex accent in `daymark_subscriptions_brand_color`. The same value drives primary actions, focus rings, the open-ring mark, and frontend subscription actions. The default remains Daymark teal (`#087f73`); presets are starting points, not a second visual language.

The picker exposes both a native color control and an exact hex field, with a small preview before save. The setting is capability- and nonce-protected and falls back to the default when the stored value is invalid.

Descriptions in the admin use a compact inline visual editor with formatting controls. Saved markup is restricted through `wp_kses_post()` before it is rendered in the frontend.

Every interactive pattern must retain visible default, hover, focus, disabled, success, error, empty, and long-content behavior. The current plugin uses WordPress notices for save results, factual empty-state copy, native validation attributes, modal focus return, Escape close, and reduced-motion support.

## Decisions that avoid generic AI output

- No gradients, glass surfaces, glow effects, emoji icons, invented metrics, or decorative hero artwork.
- The open-ring lockup appears only at the product boundary (page header and overview banner); it is not repeated as an icon on every control.
- Dashboard hierarchy uses a rule-based metric strip and one attention panel rather than four floating cards.
- Tables remain the primary data surface because subscriptions and access rules are operational records.
- WordPress-native admin navigation, buttons, notices, and form semantics remain intact.

## Component rules

- One primary action per screen; the action label describes the result.
- Warnings use both a label and color, never color alone.
- Modals trap focus, close on Escape, and return focus to the opening trigger.
- Empty states remain factual and should state the next safe action when the copy is updated.

## 2026-11-07 UI/UX refinement

- The shared UI uses semantic aliases for accent, surface, canvas, focus, control height, and section rhythm so admin and frontend overrides resolve through the same brand roles.
- Admin tables remain the operational primary surface; billing controls use a bounded action grid and scroll inside the table surface at narrow widths.
- Frontend cards remain flat and quiet; plan CTAs are compact on larger screens and become full-width only on smaller screens where touch ergonomics require it.
- Drawers and modals use solid white surfaces, bordered headers/footers, restrained elevation, visible focus, and responsive footer actions.
- Reduced-motion users receive minimal transitions and no animation-dependent information.
