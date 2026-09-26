/* Account Menu Enhancer for HivePress: front-end behaviour. */
( function () {
	'use strict';

	/**
	 * Mirrors the HivePress menu counters into the WooCommerce account menu.
	 */
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

	/**
	 * Builds the button that opens and closes a group of nested items.
	 *
	 * Named after its parent item for a screen reader, so a menu with three
	 * groups does not announce three identical buttons.
	 *
	 * @param {Element} link The parent item's link.
	 * @return {Element}
	 */
	function makeToggle( link ) {
		var data = window.amehpFrontendData || {},
			label = link.querySelector( 'span' ),
			name = '',
			button = document.createElement( 'button' );

		if ( label ) {
			name = label.textContent;
		} else {
			// WooCommerce renders the label as bare, tab-indented text, and
			// addBadges() appends a <small> counter inside the same link, so
			// only the link's own text nodes are the item's name.
			Array.prototype.forEach.call( link.childNodes, function ( node ) {
				if ( 3 === node.nodeType ) {
					name += node.textContent;
				}
			} );
		}

		name = name.replace( /\s+/g, ' ' ).trim();

		button.type = 'button';
		button.className = 'amehp-menu__toggle';

		/*
		 * Text only, through setAttribute: the item name is whatever the site
		 * put in its menu, and since 3.5.0 that can be an owner's own Label.
		 * split/join rather than String.replace(), which reads "$$", "$&",
		 * "$`" and "$'" in a string replacement as patterns even when the
		 * search is a plain string, so a label of "Pay $$ later" would have
		 * been announced as "Pay $ later sub-menu".
		 */
		button.setAttribute( 'aria-label', ( data.toggleLabel || '%s' ).split( '%s' ).join( name ) );

		return button;
	}

	/**
	 * Adds the fold-away toggles to the HivePress account menus.
	 *
	 * The header dropdown and the account page sidebar both render a nested
	 * `<ul class="sub-menu">` inside the parent `<li>`, which is HivePress
	 * core's own nesting; the stylesheet the component emits folds that list
	 * away and this opens it. A group starts open when it holds the page being
	 * viewed, so the current page is never hidden inside a closed parent.
	 */
	function addMenuToggles() {
		var parents = document.querySelectorAll( '.hp-menu--user-account li.menu-item-has-children' );

		Array.prototype.forEach.call( parents, function ( li ) {
			if ( li.classList.contains( 'amehp-ready' ) ) {
				return;
			}

			var link = li.querySelector( ':scope > a' ),
				list = li.querySelector( ':scope > ul' );

			if ( ! link || ! list ) {
				return;
			}

			var button = makeToggle( link ),
				open = li.classList.contains( 'current-menu-item' ) || !! list.querySelector( '.current-menu-item' );

			function setOpen( state ) {
				li.classList.toggle( 'amehp-open', state );
				button.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			}

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				/*
				 * Stopped here, on the button itself. The theme's burger menu
				 * closes its overlay on any click that is not on a link or on
				 * a parent row (hivetheme frontend.js, the burger's menu click
				 * handler), and a toggle that shut the whole menu on the way
				 * to opening a group would be no toggle at all. A listener on
				 * the document would be too late: the theme's handler sits on
				 * the list, which the event reaches first.
				 */
				event.stopPropagation();

				setOpen( ! li.classList.contains( 'amehp-open' ) );
			} );

			/*
			 * A parent whose link is only a fragment ("#") is a heading for its
			 * group with no page of its own, so a click on its label opens and
			 * closes the group like the chevron does, instead of jumping to the
			 * top of the page. Stopped for the same reason as the button above.
			 */
			if ( /^#[A-Za-z0-9_-]*$/.test( link.getAttribute( 'href' ) || '' ) ) {
				link.setAttribute( 'role', 'button' );
				link.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					setOpen( ! li.classList.contains( 'amehp-open' ) );
				} );
			}

			/*
			 * Stand the theme's hover flyout down for this row. hivetheme binds
			 * hoverIntent to every header menu row with a nested list and
			 * slides that list in and out on hover, positioned as a flyout to
			 * the side (frontend.js, line 33). The component's stylesheet
			 * already outranks the inline styles that leaves behind, but the
			 * slide itself would still animate the open list shut and back on
			 * every mouse-out. Unbinding the namespace hoverIntent uses removes
			 * both handlers at once; WordPress bundles hoverIntent 1.10, which
			 * binds under exactly that namespace.
			 *
			 * This runs after the theme's own ready handler because the
			 * component enqueues this file at a later priority, and jQuery runs
			 * ready callbacks in the order they were added.
			 */
			if ( window.jQuery ) {
				window.jQuery( li ).off( '.hoverIntent' );
			}

			link.insertAdjacentElement( 'afterend', button );

			setOpen( open );

			// Tells the stylesheet the script has taken over the open state
			// from its `:has(.current-menu-item)` fallback.
			li.classList.add( 'amehp-ready' );
		} );
	}

	/**
	 * Adds the fold-away toggles to the WooCommerce account navigation.
	 *
	 * That menu is a flat list, so the component orders each child directly
	 * after its parent and marks the rows with classes; a group is the parent
	 * row plus the child rows that follow it. The rows arrive in their final
	 * state from PHP (a group holding the current page is already open), so
	 * this only wires the button.
	 */
	function addWcToggles() {
		var parents = document.querySelectorAll( '.woocommerce-MyAccount-navigation li.amehp-menu__item--parent' );

		Array.prototype.forEach.call( parents, function ( li ) {
			if ( li.querySelector( ':scope > .amehp-menu__toggle' ) ) {
				return;
			}

			var link = li.querySelector( ':scope > a' );

			if ( ! link ) {
				return;
			}

			var children = [],
				sibling = li.nextElementSibling;

			while ( sibling && sibling.classList.contains( 'amehp-menu__item--child' ) ) {
				children.push( sibling );
				sibling = sibling.nextElementSibling;
			}

			if ( ! children.length ) {
				return;
			}

			var button = makeToggle( link );

			function setOpen( state ) {
				li.classList.toggle( 'amehp-open', state );
				button.setAttribute( 'aria-expanded', state ? 'true' : 'false' );

				children.forEach( function ( child ) {
					child.classList.toggle( 'amehp-collapsed', ! state );
				} );
			}

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();

				setOpen( ! li.classList.contains( 'amehp-open' ) );
			} );

			link.insertAdjacentElement( 'afterend', button );

			setOpen( li.classList.contains( 'amehp-open' ) );
		} );
	}

	function init() {
		var data = window.amehpFrontendData;

		addBadges();

		if ( data && data.nesting ) {
			addMenuToggles();
			addWcToggles();
		}
	}

	/*
	 * Through jQuery's ready queue when jQuery is on the page, so this runs
	 * AFTER the theme's own ready handler has bound its menus (see the note in
	 * addMenuToggles); the plain listener is for a theme without jQuery, which
	 * has no hover flyout to stand down.
	 */
	if ( window.jQuery ) {
		window.jQuery( init );
	} else if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// The counters again once everything has loaded, for a WooCommerce menu a
	// theme renders late; the toggles are idempotent and run again too.
	window.addEventListener( 'load', function () {
		window.setTimeout( init, 200 );
	} );
} )();
