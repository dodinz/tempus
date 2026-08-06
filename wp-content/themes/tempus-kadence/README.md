# Tempus — Kadence Child Theme

Presentation layer for Tempus (Whisky · Cigar · Vinyl) as a **Kadence**
child theme. Kadence supplies the header/footer/nav builders and
WooCommerce plumbing; this child supplies the warm-dark + gold design
system and the homepage. Site functionality (taxonomy seeding, ACF fields,
product badges) lives in the **Tempus Core** plugin
(`wp-content/plugins/tempus-core/`) — keep both active.

## Requirements
- WordPress 6.4+, PHP 8.0+
- **Kadence** parent theme (free) — `wp theme install kadence`
- **Tempus Core** plugin (this repo)
- WooCommerce 8.0+, ACF (PRO for repeaters)
- Recommended: WooCommerce Product Bundles, an age-verification plugin,
  WP Mail SMTP, WPForms/CF7 for the waitlist

## Install / switchover from the legacy theme
1. `wp theme install kadence`
2. `wp plugin activate tempus-core`
3. `wp theme activate tempus-kadence` — on activation the plugin re-seeds
   the WooCommerce categories/tags and the theme seeds Kadence's Global
   Color palette with the Tempus values.
4. Settings → Reading: keep the static front page ("Home"); the
   **Tempus — Homepage** ACF field group still drives it.
5. Appearance → Customize → **Header**: assign the Primary menu and lay
   out the header row (logo left, nav center, button right to match the
   mockup). The bridge CSS already gives header/footer the dark chrome.
6. Appearance → Customize → **Footer**: rebuild the Shop / Explore /
   Connect columns with menu widgets (the legacy hardcoded footer markup
   was retired in favor of Kadence's footer builder).
7. Delete the legacy theme when satisfied: `wp theme delete tempus`

## How the tokens map to Kadence Global Styles
`assets/css/tokens.css` stays the **single source of truth**. Two-way sync:

- **CSS (authoritative):** `assets/css/kadence-bridge.css` re-declares
  `--global-palette1..9`, `--global-heading-font-family`,
  `--global-body-font-family` and `--global-content-width` from the
  tokens, loaded after Kadence's styles so it wins the cascade. Kadence
  components and Kadence Blocks that reference the global palette render
  Tempus colors regardless of Customizer state.
- **DB (cosmetic):** `functions.php` seeds Kadence's Global Color palette
  option once, so Customizer swatches and block-editor color pickers show
  the brand palette instead of Kadence's blue defaults.

| Kadence slot | Semantic | Token | Hex |
|---|---|---|---|
| palette1 | Accent | `--gold-tempus` | `#c8922a` |
| palette2 | Accent hover | `--gold-deep` | `#e8b84b` |
| palette3 | Strongest text | `--white` | `#f0e6d3` |
| palette4 | Body text | `--on-surface-variant` | `#c9b99a` |
| palette5 | Muted text | `--ink-faint` | `#8d7f66` |
| palette6 | Subtle borders | `--outline-variant` | `#2a2620` |
| palette7 | Card surface | `--espresso` | `#1e1a15` |
| palette8 | Alt background | `--surface-container-low` | `#16130d` |
| palette9 | Page background | `--near-black` | `#0a0806` |

(`--gold-aged` / `--gold-warm` have no palette slot — token-only shades.)

To change brand colors: edit tokens.css, then re-seed the swatches with
`wp option delete tempus_kadence_palette_seeded` and re-activate the theme.

## Where things live
- `assets/css/tokens.css` — colors, type, spacing (single source of truth)
- `assets/css/kadence-bridge.css` — Kadence Global Styles mapping + chrome
  (header, footer, buttons, WooCommerce, form fields)
- `assets/css/main.css` — tz- components (hero, cards, membership, …)
- `template-parts/home/*` — homepage sections (assembled by front-page.php)
- `assets/fonts/` — drop licensed font files here (see its README)

## Fonts (required before launch)
See `assets/fonts/README.md`.

## Outstanding decisions (confirm with client)
Unchanged from the legacy theme — currency (₱ vs $), membership billing
model, and the age-verification implementation (stub hook lives in the
Tempus Core plugin).
