/**
 * Tempus Rituals — age confirmation guard.
 *
 * This is courtesy, not enforcement. On the checkout the real gate is the
 * PHP validation in age-confirm.php, which runs on the server and cannot be
 * clicked away with the console open. What happens here is simply that the
 * customer finds out immediately rather than after a round trip.
 *
 * On the reservation form this guard is all there is, until the reservation
 * plugin is chosen and it can be wired server-side too.
 */
( function ( $ ) {
	'use strict';

	var AC = window.tempusAC || {
		checkoutError: 'Please confirm that you are of legal age before placing your order.',
		reservationError: 'Please confirm that you are of legal age.'
	};

	function box( $scope ) {
		return $scope.find( '.tempus-ac' ).first();
	}

	function isTicked( $ac ) {
		return $ac.length === 0 || $ac.find( '.tempus-ac__input' ).is( ':checked' );
	}

	function complain( $ac, message ) {
		$ac.addClass( 'is-invalid' );
		$ac.find( '.tempus-ac__error' ).text( message );

		// Bring it into view — it sits low on a long checkout.
		var el = $ac.get( 0 );
		if ( el && el.scrollIntoView ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		$ac.find( '.tempus-ac__input' ).trigger( 'focus' );
	}

	function clear( $ac ) {
		$ac.removeClass( 'is-invalid' );
		$ac.find( '.tempus-ac__error' ).text( '' );
	}

	/* ---------------------------------------------------------------
	 * Ticking it clears any complaint
	 * ------------------------------------------------------------ */

	$( document ).on( 'change', '.tempus-ac__input', function () {
		var $ac = $( this ).closest( '.tempus-ac' );
		if ( this.checked ) {
			clear( $ac );
		}
	} );

	/* ---------------------------------------------------------------
	 * Checkout
	 *
	 * WooCommerce fires checkout_place_order before it posts; returning
	 * false from a handler aborts cleanly, without fighting its own
	 * submit handling.
	 * ------------------------------------------------------------ */

	$( document.body ).on( 'checkout_place_order', function () {

		var $ac = box( $( 'form.checkout' ) );

		if ( isTicked( $ac ) ) {
			clear( $ac );
			return true;
		}

		complain( $ac, AC.checkoutError );
		return false;
	} );

	/* ---------------------------------------------------------------
	 * Any other form carrying the checkbox — the reservation form,
	 * whichever plugin ends up building it.
	 * ------------------------------------------------------------ */

	$( document ).on( 'submit', 'form', function ( e ) {

		var $form = $( this );

		// The checkout is handled above, on WooCommerce's own event.
		if ( $form.hasClass( 'checkout' ) ) {
			return;
		}

		var $ac = $form.find( '.tempus-ac[data-context="reservation"]' ).first();

		if ( $ac.length === 0 || isTicked( $ac ) ) {
			return;
		}

		e.preventDefault();
		e.stopImmediatePropagation();
		complain( $ac, AC.reservationError );
	} );

} )( jQuery );
