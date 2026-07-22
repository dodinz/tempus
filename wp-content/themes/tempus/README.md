# Tempus — WordPress Theme

Premium WooCommerce theme for Tempus (Whisky · Cigar · Vinyl). Design tokens
ported 1:1 from the approved homepage mockup.

## Requirements
- WordPress 6.4+, PHP 8.0+
- WooCommerce 8.0+
- Advanced Custom Fields (free is enough; **ACF PRO** needed for the repeater
  fields — Three Pursuits and Membership Tiers)
- Recommended plugins: WooCommerce Product Bundles (rituals), an Age
  Verification for WooCommerce plugin (compliance), WP Mail SMTP (email
  delivery), a form plugin (WPForms or Contact Form 7) for the waitlist.

## Install
1. Zip this `tempus` folder (or upload it) into `wp-content/themes/`.
2. **Appearance → Themes → Activate "Tempus".**
   On activation the theme seeds WooCommerce categories/tags automatically.
3. **Settings → Reading →** set "Your homepage displays" to *A static page*,
   create/select a page called "Home", set it as Homepage.
4. Edit that Home page — the **"Tempus — Homepage"** field group appears with
   tabs for Hero / Pursuits / Featured Bottles / Rituals / Membership.
5. **Appearance → Menus** — create a Primary menu and assign it to
   "Primary Navigation" (optional; a sensible default nav shows until then).

## Fonts (required before launch)
Drop the client-licensed font files into `/assets/fonts/` using the filenames
referenced in `assets/css/tokens.css` (Maharlika, Proxima Nova Condensed,
Cormorant Garamond), or update those `@font-face` src paths.

## Where things live
- `assets/css/tokens.css` — colors, type, spacing (single source of truth)
- `assets/css/main.css` — layout + components
- `template-parts/home/*` — homepage sections
- `inc/taxonomy.php` — product categories/tags seeded in code
- `inc/acf-fields.php` — editable homepage fields
- `inc/woocommerce.php` — badges, shop layout, age-gate stub

## Outstanding decisions (confirm with client)
- Currency: mockup mixes $ (bottles) and ₱ (tiers). Set one in
  WooCommerce → Settings → General.
- Membership billing: waitlist-only now, or live recurring billing
  (needs WooCommerce Subscriptions + a recurring-capable gateway)?
- Age verification: pick a plugin and wire it in `inc/woocommerce.php`.
