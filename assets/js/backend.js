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

			// HivePress renders the colour field as a native colour input,
			// which has no editable hex field. Convert it to a text input so
			// the colour picker exposes a hex field that accepts a typed
			// value, keeping any empty value empty.
			if ( 'color' === this.type ) {
				var hasValue = !! this.getAttribute( 'value' );

				this.type = 'text';

				if ( ! hasValue ) {
					this.value = '';
				}
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
