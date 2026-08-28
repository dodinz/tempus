<?php
/**
 * Founding Members waitlist — module loader.
 *
 * Self-contained waitlist capture for Tempus. No form-builder plugin required.
 *
 * SECURITY CONTRACT — do not remove any of these:
 *   1. HMAC-signed render token   (rest.php :: tempus_waitlist_verify_token)
 *   2. Honeypot field             (render.php + rest.php)
 *   3. Timing trap, min 2s        (rest.php)
 *   4. Rate limit, 5/hour per IP  (rest.php :: tempus_waitlist_rate_limited)
 *   5. Input sanitisation         (rest.php)
 *   6. Output escaping            (render.php, cpt.php)
 *   7. Capability check on admin  (cpt.php :: export handler)
 *   8. Cloudflare Turnstile       (turnstile.php) — optional, see that file
 *   9. Double opt-in confirmation (confirm.php)
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'TEMPUS_WAITLIST_VERSION', '1.2.0' );

require_once __DIR__ . '/waitlist/cpt.php';
require_once __DIR__ . '/waitlist/turnstile.php';
require_once __DIR__ . '/waitlist/mail.php';
require_once __DIR__ . '/waitlist/confirm.php';
require_once __DIR__ . '/waitlist/rest.php';
require_once __DIR__ . '/waitlist/render.php';
