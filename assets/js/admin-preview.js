/**
 * Account Menu Enhancer for HivePress - settings screen live preview.
 *
 * Keeps the sample account menu in the Live preview panel in step with the
 * settings on screen, before anything is saved.
 *
 * The panel shows a sidebar-style menu built from the site's own account menu
 * items, dressed by the Menu Item Styling rows and followed by the Custom
 * Items rows. Styling is applied
 * with the same values the component's emitted CSS uses on the front end -
 * hex-gated colours, the same stroke widths for the icon weights, the same
 * background chip - but written per element here, because the front-end
 * stylesheet does not load in wp-admin.
 *
 * THIS FILE READS AND PAINTS THE SCREEN. The questions it asks along the way -
 * what key the server will know a custom item by, how an arrangement merges
 * into the stored one, which items belong in which panel, whether a colour or
 * an icon name is usable yet - are answered in assets/js/preview-logic.js,
 * because this file is an IIFE and nothing inside it can be reached from a
 * test. Anything new that is input-to-output belongs over there; anything that
 * needs an element or jQuery belongs here.
 */
( function () {
	'use strict';

	/*
	 * preview-logic.js is a declared dependency of this script, so it is always
	 * on the page first. The guard is here anyway: a missing global would
	 * otherwise fail in the middle of a paint, with a stack that says nothing
	 * about the load order that actually broke.
	 */
	var logic = window.amehpPreviewLogic;

	if ( ! window.jQuery || ! logic ) {
		return;
	}

	/**
	 * Option names of the global settings this panel reflects. HivePress
	 * names every settings input after its option, so the option name is the
	 * binding. The repeater rows are read straight from their tables instead,
	 * because their input names carry per-row indexes.
	 */
	var OPTIONS = {
		order: 'hp_amehp_menu_order',
		wcIntegration: 'hp_amehp_wc_integration',
		iconColour: 'hp_amehp_icon_colour',
		background: 'hp_amehp_icon_background',
		size: 'hp_amehp_icon_size',
		weight: 'hp_amehp_icon_weight',
		spacing: 'hp_amehp_icon_spacing',
		menuWeight: 'hp_amehp_menu_weight',
		headingFont: 'hp_amehp_sidebar_heading_font',
		chevrons: 'hp_amehp_hide_chevrons',
	};

	window.jQuery( function ( $ ) {
		var panel = document.querySelector( '.amehp-preview' );

		if ( ! panel ) {
			return;
		}

		/*
		 * The panels. One per menu the site actually renders: while the
		 * header dropdown, the HivePress sidebar and the WooCommerce menu all
		 * show the same items that is a single combined menu, and as they
		 * come apart - an item limited to some menus, the WooCommerce
		 * integration off, the WooCommerce-only hidden list - each gets a
		 * panel of its own. All are in the page and paint() shows or hides
		 * them live, so no save is needed to see the split.
		 */
		var panels = Array.prototype.map.call( panel.querySelectorAll( '.amehp-preview__panel' ), function ( element ) {
			return {
				element: element,
				menu: element.getAttribute( 'data-menu' ),
				list: element.querySelector( '.amehp-preview__menu' ),
				title: element.querySelector( '.amehp-preview__panel-title' ),
				header: element.querySelector( '.amehp-preview__header' ),
				body: element.querySelector( '.amehp-preview__body' ),
			};
		} );

		if ( ! panels.length || ! panels[ 0 ].list ) {
			return;
		}

		var data = window.amehpBackendData || {};
		var labels = data.labels || {};
		var reset = panel.querySelector( '.amehp-preview__reset' );
		var repaintTimer = null;

		function input( option ) {
			return document.querySelector( '[name="' + option + '"]' );
		}

		/**
		 * Reads the current value of a settings input. A row hidden by
		 * HivePress's "_parent" argument is read exactly like a visible one,
		 * on purpose: the input stays in the DOM, still posts on save and
		 * still takes effect.
		 *
		 * @param {string} option Option name.
		 * @return {string}
		 */
		function value( option ) {
			var field = input( option );

			if ( ! field ) {
				return '';
			}

			if ( 'checkbox' === field.type ) {
				return field.checked ? '1' : '';
			}

			return ( field.value || '' ).trim();
		}

		// Local names for the validators in preview-logic.js, so the calls
		// below read exactly as they did before the split.
		var hex = logic.hex,
			iconName = logic.iconName;

		/**
		 * Finds one of this plugin's repeaters by its field key.
		 *
		 * @param {string} key Field key, e.g. "hp_amehp_icons".
		 * @return {Array} Row elements.
		 */
		function repeaterRows( key ) {
			var repeaters = document.querySelectorAll( 'div[data-component="repeater"]' );

			for ( var i = 0; i < repeaters.length; i++ ) {
				if ( repeaters[ i ].querySelector( '[name^="' + key + '["]' ) ) {
					return Array.prototype.slice.call( repeaters[ i ].querySelectorAll( 'tbody > tr' ) );
				}
			}

			return [];
		}

		function rowField( row, name ) {
			var field = row.querySelector( '[name$="[' + name + ']"]' );

			return field ? ( field.value || '' ).trim() : '';
		}

		/**
		 * Every chosen value of a row's multiple select.
		 *
		 * rowField() reads `.value`, which on a multiple select is the FIRST
		 * chosen option only, so a Menus field set to two menus would read as
		 * one. The name carries the "[]" suffix core gives a multiple select.
		 *
		 * @param {Element} row Repeater row.
		 * @param {string} name Field name.
		 * @return {Array}
		 */
		function rowValues( row, name ) {
			var field = row.querySelector( '[name$="[' + name + '][]"]' ),
				values = [];

			if ( field && field.selectedOptions ) {
				Array.prototype.forEach.call( field.selectedOptions, function ( option ) {
					if ( option.value ) {
						values.push( option.value );
					}
				} );
			}

			return values;
		}

		/**
		 * Fetches the theme's Heading Font, the first time it is needed.
		 *
		 * The stylesheet comes from fonts.googleapis.com, and nothing else in
		 * wp-admin asks Google for anything, so the component enqueues it only
		 * when the Heading Font option is already switched on. That leaves one
		 * case for this: the owner ticks the box now, without reloading. The
		 * preview would otherwise fall back to a system font and show them
		 * something untrue about their own menu, which is the one thing this
		 * panel exists not to do.
		 *
		 * The address is the component's, passed through the localisation
		 * payload rather than rebuilt here, so there is one spelling of it.
		 *
		 * Unticking deliberately leaves the link in place: the request has
		 * already been made, so removing it would buy no privacy back and
		 * would only make the preview wrong if the box were ticked again.
		 */
		function loadHeadingFont() {
			if ( ! data.headingFontUrl || document.getElementById( 'amehp-preview-font-js-css' ) ) {
				return;
			}

			// The same id WordPress would have given it, so the enqueued copy
			// and this one can never both be present.
			if ( document.getElementById( 'amehp-preview-font-css' ) ) {
				return;
			}

			var link = document.createElement( 'link' );

			link.id = 'amehp-preview-font-js-css';
			link.rel = 'stylesheet';
			link.href = data.headingFontUrl;

			document.head.appendChild( link );
		}

		/**
		 * The key the SERVER will know this custom item by.
		 *
		 * Reads the row's stored id off the form and hands it to the key
		 * builder. The rule itself - and what it cost when this script built
		 * the key positionally while the server used the id - is recorded in
		 * preview-logic.js, where it is also pinned by a test.
		 *
		 * @param {Element} row Repeater row.
		 * @param {number} index Row position.
		 * @return {string}
		 */
		function customItemKey( row, index ) {
			return logic.customItemKey( rowField( row, 'uid' ), index );
		}

		/**
		 * Every custom item key currently on the settings screen.
		 *
		 * Used to tell an off-screen item (hidden, or in the other menu, and
		 * whose stored place must be kept) from one that no longer exists.
		 *
		 * @return {Array}
		 */
		function customItemKeys() {
			return logic.customItemKeys(
				repeaterRows( 'hp_amehp_custom_items' ).map( function ( row ) {
					return {
						uid: rowField( row, 'uid' ),
						label: rowField( row, 'label' ),
					};
				} )
			);
		}

		/**
		 * Every account menu item this site actually has, read from the Menu
		 * Item Styling dropdown.
		 *
		 * That dropdown is populated server-side from the same registry the
		 * front-end menus are built from, so it is the real menu rather than a
		 * list this script would otherwise have to keep in step by hand. Its
		 * first option is the empty "Select Menu Item" placeholder and is
		 * skipped.
		 *
		 * @return {Array} Objects of `value` and `label`.
		 */
		function menuCatalogue() {
			var select = document.querySelector( 'select[name^="hp_amehp_icons["][name$="[item]"]' ),
				items = [];

			if ( ! select ) {
				return items;
			}

			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( option.value ) {
					items.push( { value: option.value, label: option.text } );
				}
			} );

			return items;
		}

		/**
		 * The item keys the owner has hidden, as a lookup.
		 *
		 * @return {Object}
		 */
		function hiddenItems() {
			return selectedKeys( 'select[name="hp_amehp_hidden_items[]"]' );
		}

		/**
		 * The item keys the owner has hidden from the WooCommerce menu alone,
		 * as a lookup.
		 *
		 * Read off the select rather than sent from the server, exactly as the
		 * list above is, so the panels follow the owner's choice as they make
		 * it instead of waiting for a save.
		 *
		 * @return {Object}
		 */
		function wcHiddenItems() {
			return selectedKeys( 'select[name="hp_amehp_hidden_wc_items[]"]' );
		}

		/**
		 * The chosen values of one multiple-select, as a lookup.
		 *
		 * @param {string} selector Field selector.
		 * @return {Object}
		 */
		function selectedKeys( selector ) {
			var select = document.querySelector( selector ),
				keys = {};

			if ( select ) {
				Array.prototype.forEach.call( select.selectedOptions, function ( option ) {
					keys[ option.value ] = true;
				} );
			}

			return keys;
		}

		/**
		 * The hidden field the arranged order posts in, and its row.
		 *
		 * The field is registered like any other setting, so it saves and
		 * validates with the rest of the tab, but its own table row is taken
		 * off the screen: the panel below is where the order is arranged, and
		 * a second copy of it as comma-separated keys would be a control
		 * nobody should be editing by hand.
		 */
		var orderField = input( OPTIONS.order );

		if ( orderField ) {
			var orderRow = orderField.closest( 'tr' );

			if ( orderRow ) {
				orderRow.classList.add( 'amehp-order-row' );
			}
		}

		/**
		 * The arranged order as a list of item keys.
		 *
		 * @return {Array}
		 */
		function storedOrder() {
			return logic.parseOrder( orderField ? orderField.value : '' );
		}

		/**
		 * Writes an arrangement back into the hidden field.
		 *
		 * The merge itself is in preview-logic.js: the stored list holds items
		 * the preview is not showing - hidden ones, and WooCommerce rows while
		 * the menus are separate - and their places have to survive a drag of
		 * the ones that ARE showing.
		 *
		 * @param {Array} visible Item keys in their new on-screen order.
		 */
		function writeOrder( visible ) {
			if ( ! orderField ) {
				return;
			}

			orderField.value = logic.mergeOrder( storedOrder(), visible, customItemKeys() ).join( ',' );

			$( orderField ).trigger( 'change' );
		}

		/**
		 * Reads the keys in one panel's list, top to bottom.
		 *
		 * Document order, which walks into each nested list straight after its
		 * parent: that flat sequence - parent, its children, the next parent -
		 * is what the site stores and what apply_menu_order() replays, so a
		 * child dragged among its siblings is saved in its new place and a
		 * parent dragged past another takes its children with it.
		 *
		 * @param {Element} list Panel list.
		 * @return {Array}
		 */
		function visibleKeys( list ) {
			return Array.prototype.map
				.call( list.querySelectorAll( 'li[data-key]' ), function ( li ) {
					return li.getAttribute( 'data-key' ) || '';
				} )
				.filter( Boolean );
		}

		/**
		 * Collects the items one panel should show.
		 *
		 * The base list is the site's real account menu, minus anything hidden,
		 * and minus the WooCommerce entries when the menus are not being
		 * combined - which is what those two settings do on the front end. The
		 * styling rows then dress the matching items, and the custom items are
		 * appended.
		 *
		 * Until 2026-08-30 this drew only the styling rows, the custom items and
		 * two fixed samples, so a site that had styled nothing yet previewed a
		 * two-line menu that looked nothing like its own. The catalogue is the
		 * base list for that reason: the panel should open showing the menu the
		 * owner recognises.
		 *
		 * @param {string} which Which menu: "header", "sidebar" or
		 *                       "woocommerce".
		 * @return {Array}
		 */
		function collectItems( which ) {
			var items = [],
				overrides = {},
				hidden = hiddenItems(),
				wcHidden = wcHiddenItems(),

				/*
				 * Whether the site is MERGING the two menus, which is not the
				 * same question as whether one panel is being drawn. The two
				 * used to be the same and are not since 3.3.12: an item hidden
				 * from the WooCommerce menu alone splits the panels apart while
				 * the merge is still on, and reading the merge off the panel
				 * name would then have dropped every WooCommerce endpoint out of
				 * the HivePress panel that the merge puts in it.
				 */
				combined = !! value( OPTIONS.wcIntegration ),

				// The header and the sidebar are one HivePress menu as far as
				// the catalogue routing is concerned; only the Menus field on a
				// row tells them apart, and that is applied below.
				side = 'woocommerce' === which ? 'woocommerce' : 'hivepress',
				orders = data.itemOrders || {},
				arranged = storedOrder();

			repeaterRows( 'hp_amehp_icons' ).forEach( function ( row ) {
				var select = row.querySelector( 'select[name$="[item]"]' ),
					option = select ? select.options[ select.selectedIndex ] : null;

				if ( ! option || ! option.value ) {
					return;
				}

				// A second row for the same item adds to the first rather than
				// replacing it, the way the front end reads them; the rule is
				// mergeItemRow() in preview-logic.js.
				overrides[ option.value ] = logic.mergeItemRow( overrides[ option.value ], {
					key: option.value,
					label: option.text,

					// The owner's own name for the item, applied after the
					// site's usual name has been worked out below; empty
					// means "keep the usual name", as the field says.
					rename: rowField( row, 'label' ),
					icon: iconName( rowField( row, 'icon' ) ),
					colour: hex( rowField( row, 'colour' ) ),
					textColour: hex( rowField( row, 'text_colour' ) ),
					weight: rowField( row, 'weight' ),
					menus: logic.normaliseMenus( rowValues( row, 'menus' ) ),
					parent: rowField( row, 'parent' ),
				} );
			} );

			/*
			 * Which menu an item belongs to when the two are NOT being
			 * combined: a WooCommerce endpoint appears in the WooCommerce menu
			 * only, and a HivePress item in the HivePress menu only. Combining
			 * is exactly what puts each of them in both. Anything hidden is
			 * out of every panel.
			 *
			 * hpMenuWcKeys is the exception the prefix cannot express: the
			 * lists HivePress core adds to its own menu carry a WooCommerce
			 * name and are in both menus regardless of the setting.
			 */
			logic.catalogueItems( menuCatalogue(), hidden, side, combined, data.hpMenuWcKeys, wcHidden ).forEach( function ( entry ) {
				var item = overrides[ entry.value ] || {
					label: entry.label,
					rename: '',
					icon: '',
					colour: '',
					textColour: '',
					weight: '',
					menus: [],
					parent: '',
				};

				// The row's Menus field, which is what tells the header from
				// the sidebar and can take an item out of the WooCommerce
				// menu on its own.
				if ( ! logic.includesItemInMenu( item.menus, which ) ) {
					return;
				}

				// The order the site really renders this item in, handed over
				// by the component from the same merge the front end runs.
				item.key = entry.value;
				item.order = 'undefined' !== typeof orders[ entry.value ] ? orders[ entry.value ] : 100;

				/*
				 * And the wording the site really renders it with, which is not
				 * the wording in the dropdown these entries came from: that list
				 * suffixes its WooCommerce entries with "(WooCommerce)" to keep
				 * two similar names apart, so the panel was calling a row
				 * "Orders (WooCommerce)" where the site calls it "Placed
				 * Orders". Applied here rather than in menuCatalogue() because
				 * the answer depends on which menu is being drawn, and because
				 * a styling row's own copy of the label comes through
				 * overrides[] and needs it just as much. The owner's rename
				 * then beats it, in every menu, as it does on the site.
				 */
				item.label = item.rename || logic.itemLabel( entry.value, item.label, data.itemLabels, data.wcItemLabels, side );

				items.push( item );
			} );

			/*
			 * Custom items carry their own order. Where it comes from is
			 * explained at the customOrders lookup below; an item with none is
			 * 100 plus the row's position, which is exactly what
			 * get_custom_items() does on the front end - keep the two in step.
			 */
			repeaterRows( 'hp_amehp_custom_items' ).forEach( function ( row, index ) {
				var label = rowField( row, 'label' );

				if ( ! label ) {
					return;
				}

				/*
				 * A custom item goes in the menus its own Menus field names,
				 * so an item limited to one menu appears in that panel only,
				 * the same way it appears in that menu on the site. An empty
				 * value is the "All Menus" placeholder.
				 */
				if ( ! logic.includesItemInMenu( logic.normaliseMenus( rowValues( row, 'menus' ) ), which ) ) {
					return;
				}

				/*
				 * The Order box was retired in 3.2.0 - dragging this panel is
				 * how an item is placed now - but a number stored by an
				 * earlier version is still honoured as the item's position
				 * until it is dragged, which is what keeps an upgraded site
				 * rendering exactly as it did. get_custom_items() reads it the
				 * same way on the front end; keep the two in step.
				 *
				 * IT CANNOT BE READ OFF THE FORM. There is no Order input in
				 * the repeater any more, so the only place the number survives
				 * is the stored row, and the component hands those over in
				 * customOrders. Until 3.3.5 this line first tried
				 * rowField( row, 'order' ), which had been searching for a
				 * field that no longer exists and returning '' every time
				 * since 3.2.0.
				 *
				 * Looked up by key, because the server sends these keyed by
				 * key: a row with no label is skipped there and not here, so
				 * matching by position handed out the wrong numbers.
				 */
				var key = customItemKey( row, index ),
					typed = data.customOrders && 'undefined' !== typeof data.customOrders[ key ]
						? String( data.customOrders[ key ] )
						: '';

				items.push( {
					key: key,
					order: logic.customItemOrder( typed, index ),
					label: label,
					icon: iconName( rowField( row, 'icon' ) ),
					colour: hex( rowField( row, 'colour' ) ),
					textColour: hex( rowField( row, 'text_colour' ) ),
					weight: rowField( row, 'weight' ),
					parent: rowField( row, 'parent' ),
				} );
			} );

			// Sort the way the front end will: the owner's own arrangement
			// first, then anything they have never placed, in the real menu
			// order. The comparator is in preview-logic.js.
			logic.sortItems( items, arranged );

			// Only reached when the catalogue is empty and nothing is
			// configured, which leaves the global settings something to change.
			if ( ! items.length ) {
				items.push( { key: '', order: 0, label: labels.sampleItem || 'Dashboard', icon: '', colour: '', textColour: '', weight: '', parent: '' } );
				items.push( { key: '', order: 1, label: labels.signOut || 'Sign Out', icon: 'sign-out-alt', colour: '', textColour: '', weight: '', parent: '' } );
			}

			return items;
		}

		/**
		 * The keys a collected list would draw, in order, for comparing menus.
		 *
		 * @param {Array} items Collected items.
		 * @return {Array}
		 */
		function keysOf( items ) {
			return items.map( function ( item ) {
				return item.key;
			} );
		}

		/**
		 * Redraws the sample menus from scratch. Cheap enough not to bother
		 * diffing: each list is a couple of dozen nodes at most.
		 *
		 * One panel, two or three. The site has up to three account menus,
		 * and they show the same list until something tells them apart - an
		 * item's Menus field, the WooCommerce integration being off, or the
		 * WooCommerce-only hidden list - at which point a single preview would
		 * be showing the owner a menu their site does not have. So each menu
		 * is collected and the panels split exactly as far as the menus really
		 * differ; the rule is panelPlan() in preview-logic.js.
		 *
		 * @param {Element} [skip] A panel list to leave as it is: the one the
		 *                         owner has just dragged or arrow-moved in, whose
		 *                         nodes carry the drag and the focused button.
		 *                         The other panels share its items and are
		 *                         redrawn so they show the new order too.
		 */
		function paint( skip ) {
			var byMenu = {},
				hasWc = panels.some( function ( item ) {
					return 'woocommerce' === item.menu;
				} );

			byMenu.header = collectItems( 'header' );
			byMenu.sidebar = collectItems( 'sidebar' );

			if ( hasWc ) {
				byMenu.woocommerce = collectItems( 'woocommerce' );
			}

			var plan = logic.panelPlan( keysOf( byMenu.header ), keysOf( byMenu.sidebar ), hasWc ? keysOf( byMenu.woocommerce ) : null );

			panels.forEach( function ( item ) {
				var entry = null;

				plan.forEach( function ( candidate ) {
					if ( candidate.panel === item.menu ) {
						entry = candidate;
					}
				} );

				// A panel the plan does not name is not a menu the site has
				// right now, and is taken off screen.
				item.element.hidden = ! entry;

				if ( ! entry ) {
					return;
				}

				if ( item.title ) {
					item.title.textContent = labels[ entry.title ] || entry.title;
				}

				if ( item.list !== skip ) {
					paintPanel( item, byMenu[ entry.which ] );
				}
			} );
		}

		/**
		 * Makes a nested list sortable among its own siblings.
		 *
		 * Each nested list is its own sortable, contained to itself, so a
		 * child can be reordered among the items under the same parent and
		 * cannot be dragged out to the top level - that is what the row's
		 * Parent field is for, and a drag that silently changed it would be
		 * saving something the owner did not choose. The outer list's own
		 * sortable takes only direct children, so dragging a parent moves its
		 * whole group.
		 *
		 * @param {Element} list The nested list.
		 * @param {Element} root The panel's top-level list.
		 */
		function makeSortable( list, root ) {
			$( list ).sortable( {
				items: '> li[data-key]',
				handle: '> .amehp-preview__handle',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer',
				placeholder: 'amehp-preview__placeholder',
				forcePlaceholderSize: true,

				update: function () {
					writeOrder( visibleKeys( root ) );
					updateReset();

					// The other panels show the same items and would otherwise
					// keep the old order until some unrelated field changed.
					paint( root );
				},
			} );
		}

		/**
		 * Draws one panel's list.
		 *
		 * @param {Object} panelItem Panel record.
		 * @param {Array} items The collected items to draw.
		 */
		function paintPanel( panelItem, items ) {
			var menu = panelItem.list,
				globalColour = hex( value( OPTIONS.iconColour ) ),
				background = hex( value( OPTIONS.background ) ),
				size = logic.iconSize( value( OPTIONS.size ) ),
				globalWeight = value( OPTIONS.weight ),
				spacing = logic.iconSpacing( value( OPTIONS.spacing ) ),
				menuWeight = logic.menuWeight( value( OPTIONS.menuWeight ) ),
				headingFont = '' !== value( OPTIONS.headingFont ) && data.headingFont ? data.headingFont : '',
				hideChevrons = '' !== value( OPTIONS.chevrons );

			// Through jQuery, so the sortables built on the nested lists of
			// the last paint are torn down with their nodes.
			$( menu ).empty();

			// The sidebar font: the theme Heading Font when the toggle is on.
			// The face itself may still need fetching - see loadHeadingFont().
			if ( headingFont ) {
				loadHeadingFont();
			}

			menu.style.fontFamily = headingFont ? '"' + headingFont + '", sans-serif' : '';

			/**
			 * Builds one row, and under it the nested list of its children.
			 *
			 * @param {Object} item Collected item.
			 * @param {boolean} child Whether the row is inside a nested list.
			 * @return {Element}
			 */
			function buildRow( item, child ) {
				var li = document.createElement( 'li' ),
					link = document.createElement( 'a' ),
					chev = document.createElement( 'span' ),
					iconEl = document.createElement( 'i' ),
					name = document.createElement( 'span' ),
					moves = null;

				li.className = 'amehp-preview__item' + ( child ? ' amehp-preview__item--child' : '' ) + ( item.children && item.children.length ? ' amehp-preview__item--parent' : '' );
				chev.className = 'amehp-preview__chevron';
				name.className = 'amehp-preview__label';

				// User text goes through textContent, never markup.
				name.textContent = item.label;

				chev.hidden = hideChevrons;

				// The reorder controls: a drag handle for the mouse and a pair
				// of arrow buttons for the keyboard, because jQuery UI
				// sortable is mouse and touch only and an order that can only
				// be set by dragging cannot be set at all by somebody working
				// from the keyboard.
				if ( item.key ) {
					li.setAttribute( 'data-key', item.key );

					var handle = document.createElement( 'span' ),
						handleIcon = document.createElement( 'span' );

					moves = document.createElement( 'span' );

					handle.className = 'amehp-preview__handle';
					handle.title = labels.drag || '';
					handleIcon.className = 'dashicons dashicons-menu';
					handleIcon.setAttribute( 'aria-hidden', 'true' );
					handle.appendChild( handleIcon );

					moves.className = 'amehp-preview__moves';

					[ 'up', 'down' ].forEach( function ( direction ) {
						var button = document.createElement( 'button' ),
							arrow = document.createElement( 'span' );

						button.type = 'button';
						button.className = 'amehp-preview__move';
						button.setAttribute( 'data-move', direction );

						// Named per item, so a screen reader announces which
						// row the button moves rather than a row of identical
						// "Move up" buttons.
						button.setAttribute( 'aria-label', ( 'up' === direction ? labels.moveUp || 'Move up' : labels.moveDown || 'Move down' ) + ': ' + item.label );
						button.title = 'up' === direction ? labels.moveUp || '' : labels.moveDown || '';

						arrow.className = 'dashicons dashicons-arrow-' + direction + '-alt2';
						arrow.setAttribute( 'aria-hidden', 'true' );

						button.appendChild( arrow );
						moves.appendChild( button );
					} );

					li.appendChild( handle );
				}

				if ( item.icon ) {
					// Family class omitted on purpose - see the same note in backend.js.
					iconEl.className = 'amehp-preview__icon fa-fw fa-solid fa-' + item.icon;

					var colour = item.colour || globalColour;

					if ( colour ) {
						iconEl.style.color = colour;
					}

					if ( size ) {
						iconEl.style.fontSize = size + 'px';
					}

					var stroke = logic.stroke( item.weight || globalWeight );

					if ( stroke ) {
						iconEl.style.webkitTextStroke = stroke + ' currentColor';
						iconEl.style.paintOrder = 'stroke fill';
					}

					if ( background ) {
						iconEl.style.backgroundColor = background;
						iconEl.style.borderRadius = '50%';
						iconEl.style.padding = '0.3em';
						iconEl.style.boxSizing = 'content-box';
					}

					// Null is "the owner has not set one"; zero is a deliberate
					// no gap and still has to be written.
					if ( null !== spacing ) {
						iconEl.style.marginInlineEnd = spacing + 'px';
					}
				} else {
					iconEl.hidden = true;
				}

				if ( item.textColour ) {
					link.style.color = item.textColour;
				}

				if ( menuWeight ) {
					link.style.fontWeight = menuWeight;
				}

				link.appendChild( chev );
				link.appendChild( iconEl );
				link.appendChild( name );
				li.appendChild( link );

				// After the link, so the arrows sit at the end of the row and
				// the tab order runs down the menu rather than across it.
				if ( moves ) {
					li.appendChild( moves );
				}

				// The nested items, as the site draws them: a list inside the
				// parent's own row, always open here so every row stays
				// reachable for dragging.
				if ( item.children && item.children.length ) {
					var caret = document.createElement( 'span' ),
						sub = document.createElement( 'ul' );

					caret.className = 'amehp-preview__caret dashicons dashicons-arrow-down-alt2';
					caret.setAttribute( 'aria-hidden', 'true' );
					link.appendChild( caret );

					sub.className = 'amehp-preview__submenu';

					item.children.forEach( function ( childItem ) {
						sub.appendChild( buildRow( childItem, true ) );
					} );

					li.appendChild( sub );
					makeSortable( sub, menu );
				}

				return li;
			}

			// Nested the way the front end nests them; the rule is
			// nestItems() in preview-logic.js.
			logic.nestItems( items ).forEach( function ( item ) {
				menu.appendChild( buildRow( item, false ) );
			} );
		}

		// Coalesce bursts (colour-map drags, spinners) into one redraw.
		function repaint() {
			window.clearTimeout( repaintTimer );

			repaintTimer = window.setTimeout( paint, 50 );
		}

		/**
		 * Shows the reset button only once there is an arrangement to reset.
		 */
		function updateReset() {
			if ( reset ) {
				reset.hidden = ! storedOrder().length;
			}
		}

		/*
		 * The preview IS the reorder control.
		 *
		 * Deliberately, rather than a second sortable list of item names
		 * somewhere else on the tab: this panel already shows every item in
		 * the order the site renders it, so a separate list would be the same
		 * information twice, would have to be kept in step with this one, and
		 * would leave the owner arranging one list while looking at another.
		 * The order is written into the hidden field on every change and posts
		 * with the form, so nothing is stored until Save Changes - which is
		 * what the panel's description says.
		 */
		// The handle selector is relative to the row ("> .handle"), or a
		// child's handle deeper inside a parent row would start dragging the
		// parent. The nested lists get the same treatment in makeSortable().
		panels.forEach( function ( panelItem ) {
			makeSortable( panelItem.list, panelItem.list );
		} );

		/*
		 * The keyboard route to the same thing. The rows are moved in the DOM
		 * rather than repainted, so focus stays on the button that was pressed
		 * and a run of presses keeps moving the same item - a repaint would
		 * rebuild the list and drop focus back to the top of the page after
		 * every single press. A row moves among its own siblings only, nested
		 * or not, exactly as a drag does.
		 */
		$( panel ).on( 'click', '.amehp-preview__move', function () {
			var button = this,
				li = button.closest( 'li' ),
				list = li ? li.parentElement : null,
				root = li ? li.closest( '.amehp-preview__menu' ) : null,
				up = 'up' === button.getAttribute( 'data-move' );

			if ( ! li || ! list || ! root ) {
				return;
			}

			var sibling = up ? li.previousElementSibling : li.nextElementSibling;

			if ( ! sibling || ! sibling.hasAttribute( 'data-key' ) ) {
				return;
			}

			if ( up ) {
				list.insertBefore( li, sibling );
			} else {
				list.insertBefore( sibling, li );
			}

			writeOrder( visibleKeys( root ) );
			updateReset();
			paint( root );

			// The node moved with the button inside it, so this re-focuses the
			// same element rather than a rebuilt one.
			button.focus();
		} );

		/* --------------------------------------------------------------
		 * Folding a panel away
		 *
		 * Same control as the repeater cards further down the page - the
		 * same chevron, the same direction, the same shared CSS - because an
		 * owner should not have to learn two ways of folding something away
		 * on one screen. The items stay in the DOM while a panel is folded,
		 * so the live restyling keeps working and the stored order is
		 * untouched; only the body is hidden.
		 * ------------------------------------------------------------ */

		var PANEL_STORE = 'amehpPreviewPanels';

		function readPanelStore() {
			try {
				return JSON.parse( window.localStorage.getItem( PANEL_STORE ) ) || {};
			} catch ( error ) {
				return {};
			}
		}

		function setPanelOpen( panelItem, open, remember ) {
			panelItem.element.classList.toggle( 'amehp-preview__panel--collapsed', ! open );

			if ( panelItem.header ) {
				panelItem.header.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

				var chevron = panelItem.header.querySelector( '.dashicons' );

				if ( chevron ) {
					chevron.className = 'dashicons ' + ( open ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' );
				}
			}

			if ( panelItem.body ) {
				panelItem.body.hidden = ! open;
			}

			/*
			 * jQuery UI sortable measures its items when a drag starts, not
			 * when it is created, so a list that was hidden at creation is
			 * measured correctly the first time it is dragged after being
			 * shown. "refresh" is called anyway on expanding, because it is
			 * the documented way to tell it the items changed and it costs
			 * nothing - verified against jQuery UI 1.13, which ships with
			 * WordPress.
			 */
			if ( open ) {
				var $list = $( panelItem.list );

				if ( $list.data( 'ui-sortable' ) ) {
					$list.sortable( 'refresh' );
				}
			}

			if ( remember ) {
				var store = readPanelStore();

				store[ panelItem.menu ] = open ? 1 : 0;

				try {
					window.localStorage.setItem( PANEL_STORE, JSON.stringify( store ) );
				} catch ( error ) {
					// Storage blocked or full; the panel still folds, it just
					// forgets on the next page load.
				}
			}
		}

		panels.forEach( function ( panelItem ) {
			if ( ! panelItem.header ) {
				return;
			}

			panelItem.header.addEventListener( 'click', function () {
				setPanelOpen( panelItem, 'false' === panelItem.header.getAttribute( 'aria-expanded' ), true );
			} );

			/*
			 * The defaults, when nothing has been remembered for this panel.
			 *
			 * Below the sticky-column breakpoint the panels sit at the bottom
			 * of the form, and an open list of thirty items there buries the
			 * end of the page, so they start folded. On a wide screen the
			 * HivePress panel is open - it is the menu most owners came to
			 * look at, and the one shown when the menus agree - and the
			 * others start folded, because two open lists in a 320px column
			 * leave each with a few centimetres of stage and both scrolling.
			 */
			var stored = readPanelStore()[ panelItem.menu ],
				wide = window.matchMedia && window.matchMedia( '(min-width: 1200px)' ).matches,
				open = 'undefined' !== typeof stored ? !! stored : ( wide && 'hivepress' === panelItem.menu );

			setPanelOpen( panelItem, open, false );
		} );

		// Clearing the field hands every item back to its native order, which
		// is what apply_menu_order() does when nothing is stored.
		if ( reset ) {
			$( reset ).on( 'click', function () {

				// Asked before it happens, because the arrangement can be a
				// good deal of work and there is no undo for it. The question
				// says what goes and that nothing is written until Save
				// Changes, so a reset pressed by mistake is still recoverable
				// by leaving the page without saving.
				if ( ! window.confirm( labels.resetConfirm || 'Put every menu item back in its default order? Your arrangement will be discarded. Nothing is saved until you press Save Changes.' ) ) {
					return;
				}

				if ( orderField ) {
					orderField.value = '';
				}

				updateReset();
				paint();
			} );
		}

		/*
		 * Bindings are delegated, unlike the per-input bindings in the
		 * Notifications preview this panel is modelled on, because most of
		 * the inputs here live in repeater rows that are added, removed,
		 * cloned and dragged after load. jQuery rather than addEventListener,
		 * for the same two traps recorded there: Select2 announces a chosen
		 * value only as a jQuery "change", and the Iris colour picker writes
		 * values with .val() and announces them only as the jQuery-only
		 * "irischange" - both invisible to native listeners.
		 */
		/*
		 * The hidden order field is excluded: this script is what writes it,
		 * and repainting on its own change would rebuild the list underneath
		 * the drag or the button press that had just moved a row. Excluded BY
		 * NAME: until 3.5.0 this looked for the amehp-menu-order class, which
		 * core's hidden field never carries (it renders its own classes and
		 * drops the attribute), so every drag was followed by a full repaint
		 * that threw keyboard focus back to the top of the page. Measured on
		 * the rendered field on 2026-09-18. The other panels are repainted by
		 * paint( root ) from the two handlers instead.
		 */
		$( document ).on( 'input change irischange', '[name^="hp_amehp_"]:not([name="' + OPTIONS.order + '"])', repaint );

		// The picker's Clear button changes a colour with no event at all;
		// the zero-delay timer lets its own handler finish writing first.
		$( document ).on( 'click', '.wp-picker-clear, div[data-component="repeater"] [data-remove], div[data-component="repeater"] [data-add]', function () {
			window.setTimeout( repaint, 0 );
		} );

		// Dragging a REPEATER row changes the order its items will save in.
		// The preview's own sortable is handled above and must not repaint,
		// for the same reason the order field is excluded above.
		$( document ).on( 'sortupdate', 'div[data-component="repeater"] tbody', repaint );

		updateReset();
		paint();
	} );
}() );
