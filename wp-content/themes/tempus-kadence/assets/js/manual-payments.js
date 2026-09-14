/**
 * Tempus Rituals — payment confirmation.
 *
 * Lives only on the order confirmation page. By this point the order exists,
 * so there is an amount, a reference number and an order key — which is what
 * authorises a guest to confirm without ever making an account.
 *
 * The customer sends us either a reference number or a screenshot. Either one
 * is enough; on a phone, typing thirteen digits off the GCash confirmation
 * screen is far less work than hunting for a screenshot in the gallery.
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.tempusMP === 'undefined' ) {
		return;
	}

	var MP = window.tempusMP;

	function endpoint( action ) {
		return MP.ajaxUrl.toString().replace( '%%endpoint%%', action );
	}

	function setStatus( $form, message, state ) {
		$form.find( '.tempus-confirm__status' )
			.text( message || '' )
			.attr( 'data-state', state || '' );
	}

	function busy( $form, isBusy ) {
		$form.toggleClass( 'is-busy', !! isBusy );
		$form.find( 'input, button' ).prop( 'disabled', !! isBusy );
		$form.find( '.tempus-confirm__submit' ).text( isBusy ? MP.i18n.sending : MP.i18n.submit );
	}

	/* ---------------------------------------------------------------
	 * Show the chosen filename, and catch oversized files early
	 * ------------------------------------------------------------ */

	$( document ).on( 'change', '.tempus-confirm__input', function () {

		var input = this;
		var $form = $( input ).closest( '.tempus-confirm' );
		var file  = input.files && input.files[ 0 ];

		if ( ! file ) {
			$form.find( '.tempus-confirm__filename' ).text( '' );
			return;
		}

		if ( file.size > MP.maxBytes ) {
			setStatus( $form, MP.i18n.tooBig, 'error' );
			input.value = '';
			$form.find( '.tempus-confirm__filename' ).text( '' );
			return;
		}

		setStatus( $form, '', '' );
		$form.find( '.tempus-confirm__filename' ).text( file.name );
		$form.find( '.tempus-confirm__file-text' ).text( MP.i18n.attach );
	} );

	/* ---------------------------------------------------------------
	 * Sending the confirmation
	 * ------------------------------------------------------------ */

	$( document ).on( 'submit', '.tempus-confirm', function ( e ) {

		e.preventDefault();

		var $form     = $( this );
		var reference = $.trim( $form.find( '.tempus-confirm__ref' ).val() || '' );
		var input     = $form.find( '.tempus-confirm__input' ).get( 0 );
		var file      = input && input.files && input.files[ 0 ];

		// One or the other. Not both required, but not neither.
		if ( ! reference && ! file ) {
			setStatus( $form, MP.i18n.empty, 'error' );
			return;
		}

		var data = new FormData();

		data.append( 'nonce', MP.nonce );
		data.append( 'order_id', $form.data( 'order' ) );
		data.append( 'order_key', $form.data( 'key' ) );
		data.append( 'reference', reference );

		if ( file ) {
			data.append( 'tempus_proof', file );
		}

		busy( $form, true );
		setStatus( $form, MP.i18n.sending, 'busy' );

		$.ajax( {
			url: endpoint( 'tempus_mp_confirm' ),
			type: 'POST',
			data: data,
			processData: false,
			contentType: false
		} )
			.done( function ( response ) {

				if ( response && response.success ) {
					setStatus( $form, response.data.message, 'ok' );

					// Let the server redraw the page in its "received" state,
					// so what the customer sees is what we actually stored.
					if ( response.data.reload ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );
						return;
					}

					busy( $form, false );
					return;
				}

				setStatus(
					$form,
					response && response.data && response.data.message
						? response.data.message
						: MP.i18n.failed,
					'error'
				);
				busy( $form, false );
			} )
			.fail( function () {
				setStatus( $form, MP.i18n.failed, 'error' );
				busy( $form, false );
			} );
	} );

} )( jQuery );
