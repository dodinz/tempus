# Tempus Core — plugin

Site functionality for Tempus (Whisky · Cigar · Vinyl), packaged as a plugin
so it survives theme switches. Presentation lives in the **Tempus Kadence**
child theme (`wp-content/themes/tempus-kadence/`).

## What lives here
- `includes/taxonomy.php` — WooCommerce product categories/tags seeded in
  code (runs on plugin activation and on theme switch; safe to re-run)
- `includes/acf-fields.php` — "Tempus — Homepage" ACF field group,
  registered in PHP (repeaters require ACF PRO)
- `includes/woocommerce.php` — `tempus_product_badge()` + loop badge,
  4-up / 12-per-page shop grid, age-gate hook stub
- `includes/helpers.php` — `tempus_field()` ACF-with-fallback helper

## Legacy-theme handoff
While the old standalone **tempus** theme is the active theme, this plugin
stands down (the theme bundles identical functions and loading both would
fatal on redeclare). Switch to the Tempus Kadence child theme and the
plugin takes over automatically. After the switch, the legacy theme can be
deleted.

## Requirements
WordPress 6.4+, PHP 8.0+, WooCommerce 8.0+, ACF (PRO for repeaters).
