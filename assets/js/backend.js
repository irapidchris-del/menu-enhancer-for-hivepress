/* Account Menu Enhancer for HivePress: settings screen behaviour. */
( function ( $ ) {
	'use strict';

	function initColourPickers( container ) {
		container.find( 'input.amehp-colour' ).each( function () {
			var input = $( this );

			// Skip the inputs that are already initialised.
			if ( input.closest( '.wp-picker-container' ).length ) {
				return;
			}

			input.wpColorPicker( {
				defaultColor: false,
			} );
		} );
	}

	$( document ).ready( function () {
		initColourPickers( $( 'body' ) );
	} );

	// Initialise the pickers inside newly added repeater rows.
	$( document ).on( 'hivepress:init', function ( event, container ) {
		initColourPickers( container );
	} );
} )( jQuery );
