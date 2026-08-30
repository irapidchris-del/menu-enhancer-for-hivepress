/* Account Menu Enhancer for HivePress: mirrors the HivePress menu counters into the WooCommerce account menu. */
( function () {
	'use strict';

	function addBadges() {
		var data = window.amehpFrontendData;

		if ( ! data || ! data.badges ) {
			return;
		}

		var nav = document.querySelector( '.woocommerce-MyAccount-navigation' );

		if ( ! nav ) {
			return;
		}

		Object.keys( data.badges ).forEach( function ( key ) {
			var count = String( data.badges[ key ] );

			if ( ! count || ! /^[A-Za-z0-9_-]+$/.test( key ) ) {
				return;
			}

			var link = nav.querySelector( 'li.woocommerce-MyAccount-navigation-link--' + key + ' > a' );

			if ( ! link || link.querySelector( '.amehp-badge' ) ) {
				return;
			}

			var badge = document.createElement( 'small' );

			badge.className = data.badgeClasses || 'amehp-badge';

			// The digit count lets themes that size their badges by a data-len
			// convention target this one with the same rules.
			badge.setAttribute( 'data-len', String( count.length ) );
			badge.textContent = count;

			link.appendChild( badge );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', addBadges );
	} else {
		addBadges();
	}

	window.addEventListener( 'load', function () {
		window.setTimeout( addBadges, 200 );
	} );
} )();
