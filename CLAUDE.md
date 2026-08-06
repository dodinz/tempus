# CLAUDE.md — Tempus Project

## What this is
A premium WooCommerce site for **Tempus** — a whisky, cigar & vinyl bar/store
(Manila). Luxury brand: warm-dark + gold aesthetic, slow/deliberate browsing.
Repackaged as **plugin + Kadence child theme**:
- `wp-content/plugins/tempus-core/` — site functionality (theme-independent)
- `wp-content/themes/tempus-kadence/` — presentation (child of Kadence)
- `wp-content/themes/tempus/` — LEGACY standalone theme; kept only until the
  switchover is verified, then delete (`wp theme delete tempus`). While it is
  the active theme, tempus-core stands down to avoid function redeclares.

## Stack
- WordPress classic theme: **Kadence** parent (install via `wp theme install
  kadence`) + `tempus-kadence` child. Not block/FSE. PHP 8.0+
- WooCommerce for catalog/cart/checkout
- Advanced Custom Fields (PRO — repeaters used) for editable homepage zones
- WooCommerce Product Bundles for "Ritual" bundles
- Plugins expected: WP Mail SMTP, a form plugin (WPForms/CF7), an age-verification plugin

## Architecture
Plugin `tempus-core` (functionality; survives theme switches):
- `includes/taxonomy.php` — product categories/tags seeded in code on plugin
  activation + theme switch.
- `includes/acf-fields.php` — homepage field group (registered in PHP, not the DB).
- `includes/woocommerce.php` — product badges, shop layout, age-gate hook stub.
- `includes/helpers.php` — `tempus_field()` ACF-with-fallback helper.

Child theme `tempus-kadence` (presentation):
- `assets/css/tokens.css` — SINGLE SOURCE OF TRUTH for color/type/spacing.
  Never hardcode brand hex or px elsewhere; reference the CSS variables.
- `assets/css/kadence-bridge.css` — maps Kadence Global Styles
  (`--global-palette1..9`, fonts, content width) FROM the tokens and restyles
  Kadence chrome (header/footer/buttons/Woo). Loads after Kadence's styles.
- `assets/css/main.css` — tz- components only (chrome comes from Kadence).
- `template-parts/home/*` — homepage sections (hero, pursuits, featured-bottles, rituals, membership).
- `front-page.php` assembles the homepage from the partials.
- `functions.php` — enqueues, one-time Kadence palette seeding (mirror of
  tokens; re-seed via `wp option delete tempus_kadence_palette_seeded` +
  re-activate), plugin-missing safety shim.
- Header/footer/nav are built with Kadence's Customizer builders, not templates.

## Conventions
- CSS classes are BEM-ish under the `tz-` prefix.
- Every homepage partial reads ACF/WooCommerce data but MUST keep a sensible
  fallback so the site never renders empty during setup. Preserve this pattern.
- Escape all output (`esc_html`, `esc_url`, `wp_kses_post`). Sanitize all input.
- Keep design tokens in sync with the approved mockup — do not invent colors.

## Common commands (WP-CLI)
- Reactivate theme (re-seeds taxonomy): `wp theme activate tempus-kadence`
- List product cats: `wp term list product_cat --fields=name,slug,parent`
- Create a product: `wp wc product create --name="..." --type=simple --user=admin`
- Flush rewrite/permalinks after template changes: `wp rewrite flush`
- Tail debug log: `wp config set WP_DEBUG true --raw && tail -f wp-content/debug.log`

## Open decisions (confirm before building further)
1. **Currency** — mockup mixes $ (bottles) and ₱ (tiers). Pick ONE in
   WooCommerce → Settings → General.
2. **Membership billing** — waitlist-only at launch, or live recurring
   (needs WooCommerce Subscriptions + a recurring-capable gateway; GCash
   manual proof can't do auto-recurring).
3. **Age verification** — compliance-critical, absent from all mockups.
   Wire into the stub in `inc/woocommerce.php`. Required before launch.

## Next build tasks (backlog)
- Manual payment + "Pending Verification" custom order status (Bank/GCash + proof upload).
- Single-product + shop-archive templates styled to match the homepage.
- Age-gate modal + mandatory checkout checkbox (server-enforced, not JS-only).
- Reservation form (separate from cart) with email notifications via SMTP.
```
