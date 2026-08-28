/**
 * Tempus — Founding Members waitlist.
 *
 * Handles submission via fetch, inline validation feedback, and wiring the
 * membership tier cards (rendered by a separate ACF block) to the hidden
 * tier field.
 */
( function () {
	'use strict';

	var wrap = document.querySelector( '[data-tempus-waitlist]' );
	if ( ! wrap || typeof TempusWaitlist === 'undefined' ) {
		return;
	}

	var form      = wrap.querySelector( 'form' );
	var status    = wrap.querySelector( '[data-tempus-status]' );
	var submit    = wrap.querySelector( '.tempus-waitlist__submit' );
	var label     = wrap.querySelector( '[data-submit-label]' );
	var tierInput = wrap.querySelector( '[data-tempus-tier-input]' );
	var readout   = wrap.querySelector( '[data-tempus-tier-readout]' );

	var originalLabel = label ? label.textContent : '';

	/* ------------------------------------------------------------------
	 * Tier cards
	 *
	 * The cards live in a different block, so they may legitimately not be
	 * on the page. Everything below is optional-by-design.
	 * ----------------------------------------------------------------- */

	var cards = document.querySelectorAll( '.tempus-tier' );

	/**
	 * Drop any tier selection — pressed state, highlight, hidden input and
	 * readout. The cards are rendered by the membership block and live OUTSIDE
	 * the <form>, so form.reset() cannot reach them.
	 */
	function clearTierSelection() {
		Array.prototype.forEach.call( cards, function ( c ) {
			c.classList.remove( 'is-selected' );
			c.setAttribute( 'aria-pressed', 'false' );
		} );
		if ( tierInput ) { tierInput.value = ''; }
		if ( readout ) { readout.textContent = ''; }
	}

	Array.prototype.forEach.call( cards, function ( card ) {
		card.addEventListener( 'click', function () {

			var alreadyOn = card.getAttribute( 'aria-pressed' ) === 'true';

			clearTierSelection();

			// Clicking the selected card again clears it — otherwise there is
			// no way to undo a misclick.
			if ( alreadyOn ) {
				return;
			}

			card.classList.add( 'is-selected' );
			card.setAttribute( 'aria-pressed', 'true' );

			var tier = card.dataset.tierLabel || card.dataset.tier || '';
			if ( tierInput ) { tierInput.value = tier; }
			if ( readout ) { readout.textContent = tier + ' tier selected'; }
		} );
	} );

	/* ------------------------------------------------------------------
	 * Submission
	 * ----------------------------------------------------------------- */

	function clearErrors() {
		wrap.querySelectorAll( '.tempus-waitlist__error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		wrap.querySelectorAll( '[aria-invalid]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
	}

	function showErrors( errors ) {
		var first = null;

		Object.keys( errors ).forEach( function ( field ) {
			var target = wrap.querySelector( '[data-error-for="' + field + '"]' );
			var input  = form.querySelector( '[name="' + field + '"]' );

			if ( target ) { target.textContent = errors[ field ]; }
			if ( input ) {
				input.setAttribute( 'aria-invalid', 'true' );
				if ( ! first ) { first = input; }
			}
		} );

		// Move focus to the first problem so keyboard and screen reader users
		// are not left hunting for what went wrong.
		if ( first ) { first.focus(); }
	}

	/**
	 * Reset the Turnstile widget.
	 *
	 * CRITICAL: a Turnstile token is SINGLE USE and expires after about five
	 * minutes. If the first submit fails for any reason — a mistyped email,
	 * a 500, a dropped connection — the token is already spent. Without this
	 * reset the visitor's second attempt fails verification no matter what
	 * they do, and the form looks permanently broken to them.
	 */
	function resetTurnstile() {
		if ( window.turnstile && typeof window.turnstile.reset === 'function' ) {
			try {
				window.turnstile.reset();
			} catch ( e ) {
				// Widget not rendered yet — nothing to reset.
			}
		}
	}

	function setBusy( busy ) {
		submit.disabled = busy;
		wrap.classList.toggle( 'is-submitting', busy );
		if ( label ) {
			label.textContent = busy ? 'Joining…' : originalLabel;
		}
	}

	/**
	 * Return the form to a pristine state: fields, tier selection and any
	 * lingering inline errors.
	 */
	function resetForm() {
		form.reset();
		clearTierSelection();
		clearErrors();
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		clearErrors();
		status.textContent = '';
		status.className = 'tempus-waitlist__status';
		setBusy( true );

		var payload = {};
		new FormData( form ).forEach( function ( value, key ) {
			payload[ key ] = value;
		} );

		fetch( TempusWaitlist.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( payload )
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				setBusy( false );

				if ( result.body && result.body.success ) {
					/*
					 * Empty the fields, then replace the form entirely.
					 *
					 * The message stays put on purpose. Under double opt-in the
					 * next step is in their inbox, and resubmitting issues a
					 * fresh token that silently invalidates the link already
					 * sent. Handing back an empty form reads as "that didn't
					 * work" and invites exactly that.
					 *
					 * No resetTurnstile() here — this token was spent
					 * successfully and the form is going away.
					 */
					resetForm();
					form.hidden = true;
					status.className = 'tempus-waitlist__status is-success';
					status.textContent = result.body.message;
					return;
				}

				// Any non-success path burns the token. Always reset.
				resetTurnstile();

				if ( result.body && result.body.errors ) {
					showErrors( result.body.errors );
				}

				status.className = 'tempus-waitlist__status is-error';
				status.textContent = ( result.body && result.body.message )
					? result.body.message
					: 'Something went wrong. Please try again.';
			} )
			.catch( function () {
				setBusy( false );
				resetTurnstile();
				status.className = 'tempus-waitlist__status is-error';
				status.textContent = 'Could not reach the server. Please check your connection and try again.';
			} );
	} );

} )();
