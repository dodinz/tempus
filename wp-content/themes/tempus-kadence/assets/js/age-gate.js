/**
 * Tempus — Age Verification Gate
 *
 * Location: /wp-content/themes/kadence-child/assets/js/age-gate.js
 *
 * Responsibilities:
 *   1. Answer "Yes" → write the cookie, dissolve the overlay, release the page.
 *   2. Answer "No"  → swap to the polite decline panel.
 *   3. Keep keyboard focus inside the dialog while it is up.
 *
 * Deliberately plain JavaScript with no dependencies — it has to run before
 * anything else on the page and must never be the thing that fails.
 */

( function () {
	'use strict';

	/* ---- Configuration passed in from PHP (wp_localize_script) ---------- */

	var config = window.TempusAgeGate || {};
	var COOKIE = config.cookie || 'tempus_age_verified';
	var DAYS = parseInt( config.days, 10 ) || 30;
	var EXIT_URL = config.exitUrl || 'https://www.google.com';

	var root = document.documentElement;
	var gate = document.getElementById( 'tz-age-gate' );

	if ( ! gate ) {
		return; // Nothing to do on this page.
	}

	var panel = gate.querySelector( '.tz-age-gate__panel' );
	var stepAsk = gate.querySelector( '[data-step="ask"]' );
	var stepDeny = gate.querySelector( '[data-step="deny"]' );

	/* ---- Cookie helpers ------------------------------------------------- */

	function writeCookie() {
		var expires = new Date();
		expires.setTime( expires.getTime() + ( DAYS * 24 * 60 * 60 * 1000 ) );

		var parts = [
			COOKIE + '=1',
			'expires=' + expires.toUTCString(),
			'path=/',
			'SameSite=Lax'
		];

		// Only mark it Secure on HTTPS — on a local http:// dev site a
		// Secure cookie is silently dropped and the gate would never clear.
		if ( 'https:' === window.location.protocol ) {
			parts.push( 'Secure' );
		}

		document.cookie = parts.join( '; ' );
	}

	function hasCookie() {
		return new RegExp( '(?:^|;\\s*)' + COOKIE + '=1' ).test( document.cookie );
	}

	/* ---- Opening and closing -------------------------------------------- */

	function release() {
		writeCookie();

		// Verify the cookie actually stuck. If the browser is blocking
		// cookies entirely, still let the visitor in for this page view
		// rather than trapping them behind a door that will not open.
		var stored = hasCookie();

		root.classList.remove( 'tz-gate-on' );
		gate.classList.add( 'is-leaving' );

		window.setTimeout( function () {
			if ( gate.parentNode ) {
				gate.parentNode.removeChild( gate );
			}
			document.removeEventListener( 'keydown', onKeydown, true );

			// Hand focus to the top of the page for screen-reader users.
			var main = document.getElementById( 'main' ) || document.body;
			if ( main && main.setAttribute ) {
				main.setAttribute( 'tabindex', '-1' );
				main.focus( { preventScroll: true } );
			}
		}, 450 );

		if ( ! stored && window.console && window.console.warn ) {
			window.console.warn( 'Tempus age gate: cookie could not be stored, the visitor will be asked again.' );
		}
	}

	function showDeny() {
		stepAsk.hidden = true;
		stepDeny.hidden = false;
		focusFirst();
	}

	function showAsk() {
		stepDeny.hidden = true;
		stepAsk.hidden = false;
		focusFirst();
	}

	/* ---- Focus management ------------------------------------------------
	   While the gate is up it is the only thing on the page, so Tab must
	   cycle inside it. Think of it as a revolving door: you can keep going
	   round, but you cannot step out until you answer.
	   -------------------------------------------------------------------- */

	function focusable() {
		return Array.prototype.filter.call(
			panel.querySelectorAll( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' ),
			function ( el ) {
				return ! el.disabled && el.offsetParent !== null;
			}
		);
	}

	function focusFirst( preventScroll ) {
		var items = focusable();
		if ( ! items.length ) {
			return;
		}
		try {
			// On a short screen (a phone held sideways) a plain .focus()
			// scrolls the button into view and pushes the wordmark off the
			// top. preventScroll keeps the panel where it was drawn.
			items[0].focus( { preventScroll: !! preventScroll } );
		} catch ( e ) {
			items[0].focus();
		}
	}

	function onKeydown( event ) {
		if ( 'Tab' !== event.key && 9 !== event.keyCode ) {
			return;
		}

		var items = focusable();
		if ( ! items.length ) {
			return;
		}

		var first = items[0];
		var last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		} else if ( ! panel.contains( document.activeElement ) ) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ---- Wire up the buttons -------------------------------------------- */

	gate.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-age-gate]' );
		if ( ! trigger ) {
			return;
		}

		switch ( trigger.getAttribute( 'data-age-gate' ) ) {
			case 'yes':
				release();
				break;
			case 'no':
				showDeny();
				break;
			case 'back':
				showAsk();
				break;
			case 'exit':
				window.location.href = EXIT_URL;
				break;
		}
	} );

	/* ---- Start ----------------------------------------------------------- */

	if ( root.classList.contains( 'tz-gate-on' ) ) {
		document.addEventListener( 'keydown', onKeydown, true );
		focusFirst( true );
	} else if ( gate.parentNode ) {
		// Cookie was already present when the page loaded — drop the markup.
		gate.parentNode.removeChild( gate );
	}

	/* If the visitor returns using the back button, browsers may restore
	   the page from memory without re-running the head script. Re-check. */
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted && ! hasCookie() && ! document.getElementById( 'tz-age-gate' ) ) {
			window.location.reload();
		}
	} );

} )();