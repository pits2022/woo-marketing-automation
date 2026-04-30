/* global jQuery */
jQuery( function ( $ ) {
	'use strict';

	$( '.wma-sendy-form' ).each( function () {
		var $form   = $( this );
		var cfgKey  = $form.data( 'config' );
		var config  = window[ cfgKey ];

		if ( ! config ) {
			return;
		}

		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			var $status = $( '#' + config.form_id + '-status' );
			var $submit = $form.find( '.wma-submit' );

			$status.text( '' ).removeClass( 'wma-success wma-error' );
			$submit.prop( 'disabled', true );

			var data = {
				action:    'wma_subscribe',
				_nonce:    config.nonce,
				email:     $form.find( '[name="email"]' ).val(),
				name:      $form.find( '[name="name"]' ).val(),
				config_id: config.config_id,
			};

			var token = $form.find( '[name="cf-turnstile-response"]' ).val();
			if ( token ) {
				data[ 'cf-turnstile-response' ] = token;
			}

			$.post( config.ajax_url, data )
				.done( function ( response ) {
					if ( response.success ) {
						$status.text( response.data.message ).addClass( 'wma-success' );
						$form.find( 'input[type="email"], input[type="text"]' ).val( '' );
						if ( response.data.redirect ) {
							setTimeout( function () {
								window.location.href = response.data.redirect;
							}, 1500 );
						}
					} else {
						$status.text( response.data.message ).addClass( 'wma-error' );
						$submit.prop( 'disabled', false );
					}
				} )
				.fail( function () {
					$status.text( config.error_message ).addClass( 'wma-error' );
					$submit.prop( 'disabled', false );
				} );
		} );
	} );
} );
