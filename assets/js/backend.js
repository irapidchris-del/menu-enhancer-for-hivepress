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

			text = $.trim( text || '' );

			if ( ! text || '—' === text ) {
				return;
			}

			$( '<label class="amehp-field-label"></label>' ).text( text ).prependTo( cell );
		} );
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
