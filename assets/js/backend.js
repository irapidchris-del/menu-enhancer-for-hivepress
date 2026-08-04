/* Account Menu Enhancer for HivePress: settings screen behaviour. */
( function ( $ ) {
	'use strict';

	function labelFields( container ) {
		container.find( 'div[data-component="repeater"] table.hp-table > tbody > tr > td' ).each( function () {
			var cell = $( this );

			// Skip the cells that are already labelled.
			if ( cell.children( '.amehp-field-label' ).length ) {
				return;
			}

			var control = cell.find( 'input, select, textarea' ).first();

			// Skip the drag and remove cells, which have no field control.
			if ( ! control.length ) {
				return;
			}

			// Prefer an explicit label, then fall back to the placeholder.
			var text = control.attr( 'data-amehp-label' );

			if ( ! text ) {
				if ( control.is( 'select' ) ) {
					text = control.attr( 'data-placeholder' ) || control.find( 'option[value=""]' ).first().text();
				} else {
					text = control.attr( 'placeholder' );
				}
			}

			text = ( text || '' ).trim();

			if ( ! text || '—' === text ) {
				return;
			}

			$( '<label class="amehp-field-label"></label>' ).text( text ).prependTo( cell );
		} );
	}

	// HivePress colour fields carry pattern="#[0-9a-fA-F]{6}", which both the
	// browser and HivePress enforce, so a typed three digit value such as #fff
	// would block the whole settings page from saving. Expanding it keeps the
	// shorthand people paste from a style guide working.
	function expandShorthandHex( input ) {
		var match = /^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/.exec( input.value.trim() );

		if ( match ) {
			input.value = '#' + match[1] + match[1] + match[2] + match[2] + match[3] + match[3];
		}
	}

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

			// Blur covers clicking Save, and Enter is handled separately because
			// the browser validates the pattern before any submit handler runs,
			// so the value has to be expanded while the key is still being
			// processed.
			input.on( 'change blur', function () {
				expandShorthandHex( this );
			} );

			input.on( 'keydown', function ( event ) {
				if ( 13 === event.which ) {
					expandShorthandHex( this );
				}
			} );
		} );
	}

	function init( container ) {
		// Label the fields before the colour pickers wrap their inputs.
		labelFields( container );
		initColourPickers( container );
	}

	$( document ).ready( function () {
		init( $( 'body' ) );
	} );

	// Initialise newly added repeater rows.
	$( document ).on( 'hivepress:init', function ( event, container ) {
		init( container );
	} );
} )( jQuery );
