/**
 * Account Menu Enhancer for HivePress - the settings preview's pure logic.
 *
 * Everything in here is input to output: no DOM, no jQuery, no settings, no
 * globals beyond the one this file publishes. admin-preview.js reads the
 * screen and paints it; this file answers the questions it asks along the way -
 * what key the server will know a custom item by, how a new arrangement merges
 * into the stored one, which items belong in which menu, which nest under
 * which, how many panels the site's menus need, and whether a half-typed
 * colour or icon name is usable yet.
 *
 * WHY IT IS A SEPARATE FILE. admin-preview.js is an IIFE, so nothing inside it
 * can be reached from outside the browser, and the worst bug of the 3.3.x round
 * lived in exactly this logic: until 3.3.2 the key builder identified a custom
 * item by its row POSITION while the server identifies it by its stored uid, so
 * an item dragged to the top of the preview was saved to the bottom of the real
 * menu. A browser confirms one page in one state; it cannot walk a function
 * across its cases. Splitting these functions out makes them reachable from
 * Node, and tests/js/logic-tests.js pins every one of them.
 *
 * KEEP IT PURE. If a function here ever needs an element, an option or a
 * jQuery call, it belongs in admin-preview.js instead - the moment this file
 * touches the page it stops being testable, which is the only reason it exists.
 *
 * Loaded in the browser as the amehp-preview-logic script, declared as a
 * dependency of amehp-preview so it is always on the page first; loaded in Node
 * by tests/js/load-logic.js, which evaluates this exact file with a stand-in
 * `window`. There is no build step, and there must not be one.
 */
( function ( window ) {
	'use strict';

	/**
	 * The stroke widths get_stroke_width() emits on the front end. Keep the two
	 * in step: the preview claims to show what the site will render.
	 */
	var STROKES = {
		semibold: '0.5px',
		bold: '1px',
	};

	var api = {

		/**
		 * Accepts a colour only in the forms sanitize_hex_color() accepts, so a
		 * half-typed value clears rather than paints.
		 *
		 * @param {string} val Candidate colour.
		 * @return {string} The colour, or an empty string.
		 */
		hex: function ( val ) {
			return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( val ) ? val : '';
		},

		/**
		 * The JavaScript side of absint().
		 *
		 * @param {string|number} val Candidate number.
		 * @return {number}
		 */
		absint: function ( val ) {
			var number = parseInt( val, 10 );

			return isNaN( number ) ? 0 : Math.abs( number );
		},

		/**
		 * A Font Awesome icon name, or nothing.
		 *
		 * The pattern is deliberately narrow: the name is concatenated into a
		 * class attribute, so anything outside [a-z0-9-] is refused rather than
		 * escaped.
		 *
		 * @param {string} val Candidate name.
		 * @return {string}
		 */
		iconName: function ( val ) {
			val = ( val || '' ).trim();

			return /^[a-z0-9-]+$/.test( val ) ? val : '';
		},

		/**
		 * A CSS font weight, in the hundreds only, matching what the emitted
		 * stylesheet will accept.
		 *
		 * @param {string} val Candidate weight.
		 * @return {string} The weight, or an empty string.
		 */
		menuWeight: function ( val ) {
			return /^[1-9]00$/.test( val ) ? val : '';
		},

		/**
		 * The icon size in pixels, clamped to the range the settings field
		 * allows. Zero means "not set", which leaves the theme's own size.
		 *
		 * @param {string|number} val Candidate size.
		 * @return {number}
		 */
		iconSize: function ( val ) {
			var size = api.absint( val );

			return size ? Math.max( 8, Math.min( 48, size ) ) : 0;
		},

		/**
		 * The gap after an icon, in pixels, or null when the owner has not set
		 * one.
		 *
		 * Null and zero are different answers: zero is a deliberate "no gap"
		 * and must still be written, which is why an empty field cannot simply
		 * return 0.
		 *
		 * @param {string|number} val Candidate spacing.
		 * @return {number|null}
		 */
		iconSpacing: function ( val ) {
			if ( '' === val || null === val || 'undefined' === typeof val || isNaN( parseInt( val, 10 ) ) ) {
				return null;
			}

			return Math.max( 0, Math.min( 60, api.absint( val ) ) );
		},

		/**
		 * The stroke width for an icon weight.
		 *
		 * @param {string} weight Weight name.
		 * @return {string} A CSS length, or an empty string.
		 */
		stroke: function ( weight ) {
			return STROKES[ weight ] || '';
		},

		/**
		 * The key the SERVER will know this custom item by.
		 *
		 * It has to be worked out exactly as get_custom_items() works it out,
		 * because the stored menu order is a list of these keys and the two
		 * sides have to agree on them. Since 3.3.0 that is the row's own id;
		 * the row's position is the fallback, for a row saved before the id
		 * migration ran.
		 *
		 * Getting this wrong is not a small error. Until 3.3.2 the preview
		 * built the key positionally while the server used the id, so the two
		 * never matched: an item dragged to the TOP of the preview and saved
		 * came back LAST, below Sign Out, because the order written for
		 * "amehp_item_25" placed nothing and the real item fell through to the
		 * unplaced block at the end. It also meant the panel drew items in a
		 * position the site did not use. Measured on 2026-08-30.
		 *
		 * The id is coerced to a string first. RegExp.test() stringifies what it
		 * is given, so an absent one arrives as the nine alphanumeric
		 * characters "undefined", matches the pattern and produces the key
		 * amehp_item_undefined - a key the server has never written and never
		 * will. It cannot happen through admin-preview.js, whose rowField()
		 * always returns a string, but this is a published function now and the
		 * next caller has no way of knowing that. Found by tests/js, 2026-08-30.
		 *
		 * @param {string} uid The row's stored id.
		 * @param {number} index The row's position.
		 * @return {string}
		 */
		customItemKey: function ( uid, index ) {
			uid = uid || '';

			return /^[A-Za-z0-9]{6,32}$/.test( uid ) ? 'amehp_item_' + uid : 'amehp_item_' + ( index + 1 );
		},

		/**
		 * Every custom item key on the settings screen.
		 *
		 * Used to tell an off-screen item (hidden, or in the other menu, and
		 * whose stored place must be kept) from one that no longer exists. A
		 * row with no label is skipped, because get_custom_items() skips it
		 * too - but it still counts towards the positions of the rows below it,
		 * which is why the index comes from the row list and not from a counter
		 * of the keys pushed.
		 *
		 * @param {Array} rows Objects of `uid` and `label`, in row order.
		 * @return {Array}
		 */
		customItemKeys: function ( rows ) {
			var keys = [];

			( rows || [] ).forEach( function ( row, index ) {
				if ( row && row.label ) {
					keys.push( api.customItemKey( row.uid, index ) );
				}
			} );

			return keys;
		},

		/**
		 * Reads a stored order string into a list of keys.
		 *
		 * @param {string} raw Comma-separated keys.
		 * @return {Array}
		 */
		parseOrder: function ( raw ) {
			return raw ? String( raw ).split( ',' ).filter( Boolean ) : [];
		},

		/**
		 * Merges a new on-screen arrangement into the stored one.
		 *
		 * The keys on screen are only the ones the preview is SHOWING, and an
		 * item can be missing from it for reasons that have nothing to do with
		 * where it belongs: it is hidden, or it is a WooCommerce row on a site
		 * that is not combining the menus. Overwriting the stored list with
		 * just the visible keys would therefore throw away the place of every
		 * item the owner had already arranged and then hidden, and unhiding it
		 * later would drop it at the bottom of the menu with nothing to say
		 * why.
		 *
		 * So the old list is walked, and each key in it that is on screen is
		 * replaced by the next key from the new visible order, in place. Keys
		 * that are not on screen keep their slot and their neighbours. Anything
		 * newly visible is appended.
		 *
		 * The one key that is dropped is a CUSTOM item that matches no row.
		 * Every custom item is on this screen, so such a key is an item that
		 * has been deleted, or a positional key left over from before 3.3.2
		 * wrote them by id. Both are dead weight that would otherwise sit in
		 * the stored order for ever, and the second kind accumulates.
		 *
		 * @param {Array} previous The stored order.
		 * @param {Array} visible Item keys in their new on-screen order.
		 * @param {Array} known Every custom item key on the screen.
		 * @return {Array} The merged order.
		 */
		mergeOrder: function ( previous, visible, known ) {
			var queue = ( visible || [] ).slice(),
				merged = [];

			visible = visible || [];
			known = known || [];

			( previous || [] ).forEach( function ( key ) {
				if ( -1 !== visible.indexOf( key ) ) {
					if ( queue.length ) {
						merged.push( queue.shift() );
					}

					return;
				}

				if ( 0 === key.indexOf( 'amehp_item_' ) && -1 === known.indexOf( key ) ) {
					return;
				}

				merged.push( key );
			} );

			queue.forEach( function ( key ) {
				if ( -1 === merged.indexOf( key ) ) {
					merged.push( key );
				}
			} );

			return merged;
		},

		/**
		 * Whether an item key belongs to the WooCommerce account menu.
		 *
		 * @param {string} value Item key.
		 * @return {boolean}
		 */
		isWooItem: function ( value ) {
			return 0 === String( value ).indexOf( 'wc:' );
		},

		/**
		 * Whether one catalogue entry belongs in the panel being drawn.
		 *
		 * Hidden is hidden in every panel. Otherwise: combining the menus is
		 * exactly what puts a WooCommerce endpoint and a HivePress item in both,
		 * so the combined panel takes everything, and when the menus are apart
		 * each panel takes only its own side.
		 *
		 * EXCEPT for the items HivePress core adds to its own account menu under
		 * a WooCommerce name. "Placed Orders" appears there as soon as the member
		 * has an order, with or without this plugin's integration, and it is
		 * listed under `wc:orders` because it leads to the same page as the
		 * WooCommerce row. Reading the prefix alone therefore dropped it from the
		 * HivePress panel, the owner could not drag what the panel would not
		 * show, and the item rendered below Sign Out on the real menu. The server
		 * works out which keys those are and hands them over; see
		 * get_hp_menu_wc_keys(). They are in both menus, so they show in both
		 * panels, which is what the site does.
		 *
		 * The second hidden list is the WooCommerce panel's alone. "Also Hidden
		 * from the WooCommerce Menu" takes an item out of that menu while
		 * leaving it in the HivePress one, so it is tested before the combined
		 * shortcut below and never anywhere but that panel - a copy of this test
		 * on the HivePress side would make the setting a duplicate of the list
		 * above it.
		 *
		 * @param {string} value Item key.
		 * @param {string} which Which menu: "hivepress", "woocommerce" or
		 *                       "combined".
		 * @param {boolean} combined Whether the menus are combined.
		 * @param {Object} hidden Lookup of hidden item keys.
		 * @param {Array} hpWcKeys Keys the HivePress menu carries in their own
		 *                         right despite their WooCommerce name.
		 * @param {Object} wcHidden Lookup of keys hidden from the WooCommerce
		 *                          menu alone.
		 * @return {boolean}
		 */
		includesCatalogueEntry: function ( value, which, combined, hidden, hpWcKeys, wcHidden ) {
			if ( hidden && hidden[ value ] ) {
				return false;
			}

			if ( 'woocommerce' === which && wcHidden && wcHidden[ value ] ) {
				return false;
			}

			if ( combined ) {
				return true;
			}

			var isWc = api.isWooItem( value );

			if ( 'woocommerce' === which && ! isWc ) {
				return false;
			}

			if ( 'hivepress' === which && isWc ) {
				return -1 !== ( hpWcKeys || [] ).indexOf( value );
			}

			return true;
		},

		/**
		 * The catalogue entries one panel should show, in catalogue order.
		 *
		 * @param {Array} catalogue Objects of `value` and `label`.
		 * @param {Object} hidden Lookup of hidden item keys.
		 * @param {string} which Which menu.
		 * @param {boolean} combined Whether the menus are combined.
		 * @param {Array} hpWcKeys Keys the HivePress menu carries in their own
		 *                         right despite their WooCommerce name.
		 * @param {Object} wcHidden Lookup of keys hidden from the WooCommerce
		 *                          menu alone.
		 * @return {Array}
		 */
		catalogueItems: function ( catalogue, hidden, which, combined, hpWcKeys, wcHidden ) {
			return ( catalogue || [] ).filter( function ( entry ) {
				return api.includesCatalogueEntry( entry.value, which, combined, hidden, hpWcKeys, wcHidden );
			} );
		},

		/**
		 * Reads a Menus value into the list of menus it names.
		 *
		 * THIS IS normalise_menus() IN JAVASCRIPT, and the two have to agree or
		 * the panel shows an item in a menu the site leaves it out of. An empty
		 * list means every menu: that is the field's "All Menus" placeholder,
		 * and every menu ticked is the same answer and is read as the same
		 * answer. The two single strings are what the custom item field stored
		 * before 3.5.0, when it was a choice of one menu or the other; a row
		 * saved then still carries them until the migration has run.
		 *
		 * @param {string|Array} value The stored value.
		 * @return {Array} Menu names in canonical order, or empty for all.
		 */
		normaliseMenus: function ( value ) {
			var all = [ 'header', 'sidebar', 'woocommerce' ];

			if ( 'hivepress' === value ) {
				value = [ 'header', 'sidebar' ];
			} else if ( 'woocommerce' === value ) {
				value = [ 'woocommerce' ];
			}

			if ( ! Array.isArray( value ) ) {
				return [];
			}

			var menus = all.filter( function ( menu ) {
				return -1 !== value.indexOf( menu );
			} );

			return menus.length === all.length ? [] : menus;
		},

		/**
		 * Whether an item limited to some menus belongs in the one being drawn.
		 *
		 * @param {Array} menus The item's menus, from normaliseMenus().
		 * @param {string} which Which menu: "header", "sidebar" or "woocommerce".
		 * @return {boolean}
		 */
		includesItemInMenu: function ( menus, which ) {
			menus = menus || [];

			return ! menus.length || -1 !== menus.indexOf( which );
		},

		/**
		 * Which panels to draw, given what each of the site's menus would show.
		 *
		 * One panel is the truth only while the menus agree. The header
		 * dropdown and the HivePress sidebar are one menu built twice, so they
		 * agree until an item is limited to one of them; the WooCommerce menu
		 * agrees with them only while the integration merges everything and
		 * nothing is hidden or limited on one side. So: one panel when all
		 * agree, two when only the WooCommerce menu differs, three otherwise.
		 * A site without WooCommerce passes null for that menu and gets one or
		 * two.
		 *
		 * Compared by key sequence, because that is what a panel draws; the
		 * labels a key renders with can legitimately differ between the
		 * HivePress and WooCommerce menus (see itemLabel) without making them
		 * different menus.
		 *
		 * @param {Array} header Keys the header dropdown shows, in order.
		 * @param {Array} sidebar Keys the HivePress sidebar shows, in order.
		 * @param {Array|null} woocommerce Keys the WooCommerce menu shows, or
		 *                                 null where the site has no such menu.
		 * @return {Array} Objects of `panel` (the panel to use), `which` (the
		 *                 menu to draw in it) and `title` (the label key).
		 */
		panelPlan: function ( header, sidebar, woocommerce ) {
			function same( a, b ) {
				a = a || [];
				b = b || [];

				return a.length === b.length && a.every( function ( key, index ) {
					return key === b[ index ];
				} );
			}

			var hpSame = same( header, sidebar ),
				hasWc = Array.isArray( woocommerce );

			if ( hpSame && ( ! hasWc || same( sidebar, woocommerce ) ) ) {
				return [ { panel: 'hivepress', which: 'sidebar', title: 'combined' } ];
			}

			if ( hpSame ) {
				return [
					{ panel: 'hivepress', which: 'sidebar', title: 'hpMenu' },
					{ panel: 'woocommerce', which: 'woocommerce', title: 'wcMenu' },
				];
			}

			var plan = [
				{ panel: 'header', which: 'header', title: 'headerMenu' },
				{ panel: 'hivepress', which: 'sidebar', title: 'sidebarMenu' },
			];

			if ( hasWc ) {
				plan.push( { panel: 'woocommerce', which: 'woocommerce', title: 'wcMenu' } );
			}

			return plan;
		},

		/**
		 * Folds a further Menu Items row for the same item into what earlier rows
		 * set.
		 *
		 * Nothing stops an owner adding two rows for one item (the dropdown
		 * offers it again), and the front end then takes each setting from the
		 * LAST ROW THAT SET IT: get_label_overrides() the last typed label,
		 * get_item_menus() the last row with a limit, get_parent_map() the last
		 * row with a parent, get_icon_rules() the icon together with the colour
		 * and weight of the row that supplied it, get_text_colour_css() the last
		 * text colour. A later row's empty box never clears an earlier row's
		 * value. Until this existed the preview took the last row wholesale,
		 * blanks included, and showed an item unlimited and unnamed while the
		 * site limited and renamed it. Keep this in step with those readers.
		 *
		 * @param {Object|undefined} existing What earlier rows set, or nothing.
		 * @param {Object} incoming This row's values: key, label, rename, icon,
		 *                          colour, weight, textColour, menus, parent.
		 * @return {Object} The merged override.
		 */
		mergeItemRow: function ( existing, incoming ) {
			var out = existing || {
				label: incoming.label,
				rename: '',
				icon: '',
				colour: '',
				weight: '',
				textColour: '',
				menus: [],
				parent: '',
			};

			out.label = incoming.label;

			if ( incoming.rename ) {
				out.rename = incoming.rename;
			}

			// The icon's colour and weight travel with the icon, from the row
			// that supplied it, because get_icon_rules() reads all three from
			// one row and skips a row with no icon entirely.
			if ( incoming.icon ) {
				out.icon = incoming.icon;
				out.colour = incoming.colour || '';
				out.weight = incoming.weight || '';
			}

			if ( incoming.textColour ) {
				out.textColour = incoming.textColour;
			}

			if ( incoming.menus && incoming.menus.length ) {
				out.menus = incoming.menus;
			}

			// An item naming itself is dropped on the PHP side too.
			if ( incoming.parent && incoming.parent !== incoming.key ) {
				out.parent = incoming.parent;
			}

			return out;
		},

		/**
		 * Nests sorted items under their chosen parents, one level deep.
		 *
		 * THIS IS resolve_parents() IN JAVASCRIPT; keep the two in step. A
		 * child whose parent is not among the items - hidden, in another menu,
		 * or gone - stays at the top level rather than vanishing with it. A
		 * child whose parent is itself nested is promoted, and two items
		 * naming each other are both promoted, so the answer never depends on
		 * row order.
		 *
		 * Every item is given a `children` list, and the top-level items are
		 * returned in the order they arrived, each with its children in the
		 * order they arrived - which is the order the front end renders each
		 * level in, because the flat order is what the site stores.
		 *
		 * @param {Array} items Objects of `key` and `parent`, in menu order.
		 * @return {Array} The top-level items.
		 */
		nestItems: function ( items ) {
			items = items || [];

			var byKey = {},
				resolved = {},
				children = {},
				top = [];

			items.forEach( function ( item ) {
				item.children = [];

				if ( item.key && ! byKey[ item.key ] ) {
					byKey[ item.key ] = item;
				}
			} );

			items.forEach( function ( item ) {
				var parent = item.parent || '';

				if ( item.key && parent && parent !== item.key && byKey[ parent ] ) {
					resolved[ item.key ] = parent;
				}
			} );

			Object.keys( resolved ).forEach( function ( key ) {
				if ( ! resolved[ resolved[ key ] ] ) {
					children[ key ] = resolved[ key ];
				}
			} );

			items.forEach( function ( item ) {
				if ( item.key && children[ item.key ] ) {
					byKey[ children[ item.key ] ].children.push( item );
				} else {
					top.push( item );
				}
			} );

			return top;
		},

		/**
		 * The label one item is really rendered with in one menu.
		 *
		 * THE CATALOGUE'S OWN LABEL IS THE LAST RESORT, NOT THE FIRST. The
		 * panel reads its items from the Menu Item Styling dropdown, and that
		 * dropdown suffixes its WooCommerce entries with "(WooCommerce)" so an
		 * owner can tell two similarly named destinations apart. In a dropdown
		 * that is a help; in the preview it is untrue, and the panel was drawing
		 * "Orders (WooCommerce)" for a row the site renders as "Placed Orders".
		 * Reported from a live site on 2026-08-30.
		 *
		 * The server sends the labels the menus really rendered, one map per
		 * menu, because the two menus genuinely disagree: HivePress core names
		 * that row from the WooCommerce menu ("Orders") and HivePress
		 * Marketplace relabels it to "Placed Orders" in the HivePress menu for a
		 * member who is also a vendor. So the WooCommerce panel asks its own map
		 * first, and every panel falls back to the HivePress one. See
		 * get_preview_labels().
		 *
		 * @param {string} key Item key.
		 * @param {string} fallback The catalogue's own label.
		 * @param {Object} labels Labels the HivePress menu renders.
		 * @param {Object} wcLabels Labels the WooCommerce menu renders.
		 * @param {string} which Which menu is being drawn.
		 * @return {string}
		 */
		itemLabel: function ( key, fallback, labels, wcLabels, which ) {
			if ( 'woocommerce' === which && wcLabels && wcLabels[ key ] ) {
				return wcLabels[ key ];
			}

			if ( labels && labels[ key ] ) {
				return labels[ key ];
			}

			return fallback;
		},

		/**
		 * A custom item's position.
		 *
		 * The Order box was retired in 3.2.0 - dragging the panel is how an
		 * item is placed now - but a number stored by an earlier version is
		 * still honoured until the item is dragged, which is what keeps an
		 * upgraded site rendering exactly as it did. An item with no stored
		 * number is 100 plus the row's position, which is what
		 * get_custom_items() does on the front end; keep the two in step.
		 *
		 * @param {string} typed The stored Order number, as a string.
		 * @param {number} index The row's position.
		 * @return {number}
		 */
		customItemOrder: function ( typed, index ) {
			return '' !== typed && ! isNaN( parseInt( typed, 10 ) ) ? parseInt( typed, 10 ) : 100 + index;
		},

		/**
		 * Sorts collected items the way the front end will.
		 *
		 * With no arrangement stored, that is the numeric order - the real menu
		 * order, not the dropdown's alphabetical one, which is what the panel
		 * showed until 3.2.0. Once the owner has dragged the menu into an order
		 * of their own, their arrangement is kept exactly and anything they have
		 * never placed is slotted in beside the placed item its own numeric
		 * order puts it next to.
		 *
		 * THIS IS apply_menu_order() IN JAVASCRIPT, and the two have to give the
		 * same answer or the panel tells the owner something untrue about their
		 * own menu. Both changed together in 3.3.10, when unplaced items stopped
		 * being appended after everything else - keep them in step, and change
		 * neither alone. The PHP side carries the reasoning.
		 *
		 * A comparator cannot express this: the position an unplaced item takes
		 * depends on where the placed items ended up, not on a pairwise
		 * comparison, so the sequence is built rather than sorted. The array is
		 * still sorted in place and returned, so callers need not change.
		 *
		 * @param {Array} items Objects of `key` and `order`.
		 * @param {Array} arranged The owner's stored arrangement.
		 * @return {Array} The same array, sorted.
		 */
		sortItems: function ( items, arranged ) {
			arranged = arranged || [];

			var placed = [],
				unplaced = [],
				sequence = [];

			( items || [] ).forEach( function ( item ) {
				var at = arranged.indexOf( item.key );

				if ( -1 !== at ) {
					placed.push( { item: item, at: at } );
				} else {
					unplaced.push( item );
				}
			} );

			if ( ! placed.length ) {
				return items.sort( function ( a, b ) {
					return a.order - b.order;
				} );
			}

			placed.sort( function ( a, b ) {
				return a.at - b.at;
			} );

			placed.forEach( function ( entry ) {
				sequence.push( entry.item );
			} );

			unplaced.sort( function ( a, b ) {
				return a.order - b.order;
			} );

			unplaced.forEach( function ( item ) {
				var index = 0;

				// The LAST match wins, so one row the owner dragged out of its
				// natural place cannot capture everything behind it.
				sequence.forEach( function ( placedItem, offset ) {
					if ( placedItem.order <= item.order ) {
						index = offset + 1;
					}
				} );

				sequence.splice( index, 0, item );
			} );

			items.length = 0;

			sequence.forEach( function ( item ) {
				items.push( item );
			} );

			return items;
		},
	};

	window.amehpPreviewLogic = api;
}( typeof window !== 'undefined' ? window : this ) );
