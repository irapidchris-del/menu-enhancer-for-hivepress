/* Account Menu Enhancer for HivePress: settings screen behaviour. */
( function ( $ ) {
	'use strict';

	/*
	 * The repeater rows inside a container, INCLUDING the container itself.
	 *
	 * Core hands `hivepress:init` the newly added row, not the page, so a
	 * selector written as "the rows inside this" finds nothing on a new row:
	 * it is looking for a repeater underneath a single <tr>. Everything that
	 * decorates a row has to go through here, or it works on the rows that
	 * were on the page at load and silently skips every row the owner adds -
	 * which is exactly what happened to the ids and the drag handles, caught
	 * by adding a row on 2026-08-30 and finding it still had its handle.
	 *
	 * @param {Object} container jQuery container.
	 * @return {Object} jQuery collection of rows.
	 */
	function repeaterRows( container ) {
		var selector = 'div[data-component="repeater"] table.hp-table > tbody > tr';

		return container.find( selector ).addBack( 'tr' );
	}

	function labelFields( container ) {
		repeaterRows( container ).children( 'td' ).each( function () {
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

	/*
	 * Empties the colour fields of a newly added repeater row.
	 *
	 * THE TRAP, because a "simplification" here silently colours people's
	 * icons black. HivePress's repeater does not build a new row from the row
	 * on screen: it clones a SAMPLE taken once, at its own init
	 * (hivepress/assets/js/common.js, `sampleItem = firstItem.clone()`), and
	 * that sample keeps the first row's server-rendered `value` attribute for
	 * ever. Adding a row then does three things in order: clone the sample,
	 * call `field.val('')` on every input, and fire `hivepress:init`.
	 *
	 * `field.val('')` looks like it clears the colour, and does not. While the
	 * field is still `type="color"` - which it is in a sample captured before
	 * the pickers were built - a browser cannot hold an empty colour input and
	 * normalises the empty string to `#000000`. The old code then declined to
	 * blank it, because it decided emptiness from `getAttribute( 'value' )`,
	 * and on a clone that attribute is the FIRST ROW'S colour, not this row's.
	 * So a new row arrived holding `#000000`, the owner never opened the
	 * picker, and the save stored black - which the front-end CSS then forced
	 * onto that item's icon, overriding both the global Icon Colour setting
	 * and the theme's `currentColor`. Measured on 2026-08-30: a new row's
	 * `[colour]` read `#000000` with its value attribute still `#8224e3`.
	 *
	 * Text Colour escaped it only because the first row happened to have none,
	 * so its cloned attribute was empty and the old guard blanked it. That is
	 * luck, not correctness, and it would break the moment somebody saved a
	 * text colour on their first row.
	 *
	 * The fix is to state the fact rather than infer it: a row that has just
	 * been added has no colours, so both fields are emptied outright - the
	 * attribute as well as the value, so nothing downstream can read the stale
	 * one - before any picker is built over them. Rows already on the page are
	 * never touched, so a colour the owner deliberately saved is untouched.
	 *
	 * Any picker markup that came with the clone is unwrapped as well. A
	 * cloned `.wp-picker-container` is dead markup - jQuery's clone copies no
	 * handlers or data - and leaving it there would make initColourPickers
	 * take its "already initialised" early return and hand the owner a picker
	 * that does not open.
	 *
	 * @param {Object} container jQuery container, usually the new row.
	 */
	function resetClonedColours( container ) {

		/*
		 * ONLY a freshly added row, and the container is what proves it.
		 *
		 * Core fires `hivepress:init` twice over: once for the page when it
		 * initialises the screen, and once per row added afterwards, handing
		 * the new `<tr>` itself. Without this test the reset also ran on the
		 * page pass and emptied the colours of every row already saved -
		 * measured on 2026-08-30, a row holding #8224e3 came back blank on
		 * load, which would have written the loss to the database on the next
		 * save. A row is the unit that gets cloned, so a row is the only
		 * container this may touch.
		 */
		if ( ! container || ! container.is || ! container.is( 'tr' ) ) {
			return;
		}

		repeaterRows( container ).find( 'input.amehp-colour' ).each( function () {
			var input = $( this ),
				wrapper = input.closest( '.wp-picker-container' );

			// Put a cloned picker's input back where core rendered it, so the
			// real initialiser below sees a bare field and builds a live one.
			if ( wrapper.length ) {
				wrapper.replaceWith( input );
			}

			// A colour input cannot hold an empty string, so it has to stop
			// being one before it can be emptied.
			if ( 'color' === this.type ) {
				this.type = 'text';
			}

			this.removeAttribute( 'value' );
			this.value = '';
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

	/* ======================================================================
	 * SHARED SETTINGS CHROME
	 *
	 * Three pieces of furniture for a long settings tab: the quick-links
	 * anchor nav, a floating Save control and a back-to-top button. Written
	 * to be copied verbatim into the other plugins, so everything below is
	 * self-contained and the only plugin-specific values are the two
	 * constants in CHROME.
	 *
	 * THE HOUSE RULE THIS IMPLEMENTS (resources/hivepress-settings.md, "The
	 * settings anchor nav: one shared marker class", 2026-08-30). Several of
	 * these plugins can decorate one settings screen, so each piece carries
	 * TWO classes: a shared marker that is never styled and exists only so
	 * siblings can find it (`hp-settings-nav`, `hp-settings-save`,
	 * `hp-settings-top`), plus the plugin's own prefixed class carrying all
	 * the CSS. Before rendering a piece, test for its marker with an EXACT
	 * class selector and stand down if a sibling got there first, so the
	 * owner sees one of each however many extensions are active.
	 *
	 * The exact test is the point. The old convention was the substring
	 * `nav[class*="settings-nav"]`, which was blind to three of the plugins
	 * it was meant to see - including this one, whose nav was called
	 * `amehp-section-nav` - and it failed silently.
	 * ================================================================== */

	var CHROME = {
		// This plugin's own class prefix and the field prefix that says the
		// rendered tab belongs to it. The only two lines to change on a copy.
		prefix: 'amehp',
		fieldPrefix: 'hp_amehp_',
	};

	/*
	 * Yes, this is the same three lines as cardLabels() further down, and the
	 * duplication is deliberate. This block is copied verbatim into fifteen
	 * sibling plugins, and it is only safe to copy because nothing in it
	 * reaches outside itself. Fold the two together and the next copy either
	 * drags the card code along with it or lands with a missing function,
	 * which is a breakage nobody sees until they open that plugin's settings
	 * screen. Leave both.
	 */
	function chromeLabels() {
		return ( window.amehpBackendData && window.amehpBackendData.labels ) || {};
	}

	/**
	 * The settings form, but only when this plugin's tab is the one rendered.
	 *
	 * Gating on our own fields rather than on heading count, because a count
	 * is true of every HivePress tab: Geolocation Plus 1.1.0 gated that way
	 * and decorated other plugins' tabs until 1.1.1.
	 *
	 * @return {Element|null}
	 */
	function chromeForm() {
		var form = document.querySelector( '.hp-page form.hp-form--table' );

		if ( ! form || ! form.querySelector( '[name^="' + CHROME.fieldPrefix + '"]' ) ) {
			return null;
		}

		return form;
	}

	/**
	 * The quick-links anchor nav.
	 *
	 * WordPress renders settings sections as bare <h2>s through
	 * do_settings_sections(), with no hook to add anchors, so the ids and the
	 * nav have to be added here.
	 *
	 * @param {Element} form Settings form.
	 */
	function addSectionNav( form ) {
		if ( document.querySelector( 'nav.hp-settings-nav' ) ) {
			return;
		}

		// Direct children only: the live preview panel carries an h2 of its
		// own inside its box, and that one is neither a section nor a target.
		var headings = form.querySelectorAll( ':scope > h2' );

		if ( headings.length < 2 ) {
			return;
		}

		var nav = document.createElement( 'nav' ),
			navLabel = chromeLabels().jumpTo || 'Jump to a section:';

		nav.className = 'hp-settings-nav ' + CHROME.prefix + '-settings-nav';

		/*
		 * The bar opens with its own wording, not just an aria-label.
		 *
		 * A row of pills with nothing in front of it reads as decoration, and
		 * the one audience that was told what it is - a screen reader, through
		 * the aria-label - is the one audience that could not see the pills
		 * anyway. The visible text is part of the house chrome spec
		 * (resources/hivepress-settings.md, "The settings anchor nav"), so it
		 * carries its own class for the sibling plugins to copy, and the
		 * aria-label is dropped: the text now names the nav for everybody, and
		 * leaving both would have a screen reader announce the name twice.
		 */
		var label = document.createElement( 'span' );

		label.className = CHROME.prefix + '-settings-nav__label';
		label.textContent = navLabel;

		nav.appendChild( label );

		headings.forEach( function ( heading, index ) {

			/*
			 * Reuse the id WordPress already put on the heading and mint one
			 * only where there is none. Overwriting it breaks every link,
			 * bookmark and sibling script pointing at the real
			 * `wp-settings-section-{name}` id, which is exactly what this
			 * function did before 3.2.0.
			 */
			if ( ! heading.id ) {
				heading.id = CHROME.prefix + '-section-' + index;
			}

			heading.classList.add( CHROME.prefix + '-section-heading' );

			if ( 0 === index ) {
				heading.classList.add( CHROME.prefix + '-section-heading--first' );
			}

			var link = document.createElement( 'a' );

			link.href = '#' + heading.id;

			// textContent on both ends, so heading markup can never become
			// link markup.
			link.textContent = heading.textContent;

			nav.appendChild( link );
		} );

		/*
		 * A last link to the live preview panel, for the narrow layout only.
		 *
		 * Below the sticky-column breakpoint the panel is moved to the bottom
		 * of the form (see backend.css), so on a phone it is a long way from
		 * the controls that change it. Above the breakpoint it is a column
		 * beside the form and always in view, so the link is redundant and
		 * the stylesheet hides it there rather than this script removing it -
		 * a layout that depended on the script having run would be wrong at
		 * the first paint and on any screen where the script fails.
		 */
		var panel = form.querySelector( '.' + CHROME.prefix + '-preview' );

		if ( panel ) {
			var title = panel.querySelector( '.' + CHROME.prefix + '-preview__title' );

			if ( title ) {
				if ( ! title.id ) {
					title.id = CHROME.prefix + '-preview-title';
				}

				var previewLink = document.createElement( 'a' );

				previewLink.href = '#' + title.id;
				previewLink.className = CHROME.prefix + '-settings-nav__preview';
				previewLink.textContent = title.textContent;

				nav.appendChild( previewLink );
			}
		}

		form.insertBefore( nav, headings[ 0 ] );
	}

	/**
	 * The floating Save control.
	 *
	 * It submits the real form rather than carrying any save logic of its
	 * own: requestSubmit() runs the same validation and the same submit
	 * handlers as pressing the button at the bottom of the page, so there is
	 * only ever one way to save. The real button stays exactly where it was.
	 *
	 * @param {Element} form Settings form.
	 */
	function addFloatingSave( form ) {
		if ( document.querySelector( '.hp-settings-save' ) ) {
			return;
		}

		var submit = form.querySelector( 'input[type="submit"], button[type="submit"]' );

		if ( ! submit ) {
			return;
		}

		var button = document.createElement( 'button' ),
			icon = document.createElement( 'span' ),
			text = document.createElement( 'span' ),
			label = chromeLabels().save || 'Save Changes';

		button.type = 'button';

		/*
		 * Core's own button classes, so WordPress paints it.
		 *
		 * This control IS the form's Save button, moved somewhere reachable,
		 * so it has to look like it - and "looks like it" is not one colour.
		 * Every user can pick an Admin Colour Scheme under Users > Profile,
		 * and each scheme repaints .wp-core-ui .button-primary. Painting our
		 * own #2271b1 matched the default scheme and nothing else: measured on
		 * 2026-08-30 under Modern, the real button was rgb(56,88,233) and this
		 * tab rgb(34,113,177), side by side on the same screen. The prefixed
		 * class is kept for layout only.
		 */
		button.className = 'hp-settings-save ' + CHROME.prefix + '-settings-save button button-primary';
		button.setAttribute( 'aria-label', label );

		icon.className = 'dashicons dashicons-saved';
		icon.setAttribute( 'aria-hidden', 'true' );

		text.className = CHROME.prefix + '-settings-save__text';
		text.textContent = label;

		button.appendChild( icon );
		button.appendChild( text );

		button.addEventListener( 'click', function () {

			// requestSubmit() fires the submit event and the browser's own
			// validation; form.submit() would skip both. Older browsers
			// without it get the real button pressed instead, which is the
			// same thing by a longer route.
			if ( form.requestSubmit ) {
				form.requestSubmit( submit );
			} else {
				submit.click();
			}
		} );

		document.body.appendChild( button );
	}

	/**
	 * The back-to-top button.
	 *
	 * Hidden until the page has actually scrolled, so it never covers
	 * anything on a tab short enough not to need it.
	 */
	function addBackToTop() {
		if ( document.querySelector( '.hp-settings-top' ) ) {
			return;
		}

		var button = document.createElement( 'button' ),
			icon = document.createElement( 'span' ),
			label = chromeLabels().backToTop || 'Back to top';

		button.type = 'button';

		// Core's secondary button, for the same reason as the Save tab above:
		// its blue is the scheme's blue, not a hex of ours.
		button.className = 'hp-settings-top ' + CHROME.prefix + '-settings-top button';
		button.setAttribute( 'aria-label', label );
		button.title = label;
		button.hidden = true;

		icon.className = 'dashicons dashicons-arrow-up-alt2';
		icon.setAttribute( 'aria-hidden', 'true' );

		button.appendChild( icon );

		button.addEventListener( 'click', function () {

			// A reader who has asked for reduced motion is asking not to be
			// moved through a long page; "auto" jumps instead of animating.
			var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			window.scrollTo( {
				top: 0,
				behavior: reduced ? 'auto' : 'smooth',
			} );

			// Focus follows the scroll, so a keyboard user carries on from the
			// top of the page rather than from a button that is now off screen.
			var heading = document.querySelector( '.hp-page__title' );

			if ( heading ) {
				heading.setAttribute( 'tabindex', '-1' );
				heading.focus( { preventScroll: true } );
			}
		} );

		document.body.appendChild( button );

		/*
		 * The show/hide runs straight off the scroll event.
		 *
		 * It used to be deferred into requestAnimationFrame, which is the
		 * usual advice for scroll handlers - and it meant the button never
		 * appeared at all whenever the page was not being painted, because a
		 * browser pauses rAF on a hidden page and the callback simply never
		 * ran. Caught by measurement on 2026-08-30: document.hidden was true,
		 * the page was scrolled to 1500px, and the button stayed hidden.
		 * Nobody is looking at a page in that state, so the symptom was
		 * invisible rather than harmless - it would equally have hidden a
		 * genuine failure. The work here is two property reads and a boolean
		 * write, which is cheap enough to do on the event itself, so the
		 * optimisation bought nothing and cost correctness.
		 */
		function update() {
			button.hidden = ( window.pageYOffset || document.documentElement.scrollTop ) < 300;
		}

		window.addEventListener( 'scroll', update, { passive: true } );

		update();
	}

	/**
	 * Adds every piece of chrome, one tick after ready.
	 *
	 * The delay is deliberate: load order between plugins is not something
	 * any of them controls, so a sibling whose hook registered first may
	 * still be placing its own nav when this runs. One tick lets it finish,
	 * and the stand-down guards then see it.
	 */
	function addSettingsChrome() {
		window.setTimeout( function () {
			var form = chromeForm();

			if ( ! form ) {
				return;
			}

			addSectionNav( form );
			addFloatingSave( form );
			addBackToTop();
		}, 0 );
	}

	/*
	 * Collapsible repeater cards.
	 *
	 * Each card in the Menu Item Styling and Custom Items repeaters gets a
	 * header bar showing the chosen item's name and icon, with a toggle that
	 * collapses the card to just that bar. Collapse state is remembered in
	 * localStorage by field name and row position - the rows have no stable
	 * ids (HivePress keys them with uniqid() on every render), but positions
	 * survive a reload unless the rows themselves were edited, which is the
	 * honest limit of what can be remembered. The default is collapsed for a
	 * filled row when the repeater has more than one, so a long list opens
	 * compact while a fresh empty row stays open for editing.
	 */
	var CARD_STORE = 'amehpCards';

	function cardLabels() {
		return ( window.amehpBackendData && window.amehpBackendData.labels ) || {};
	}

	function brandIcons() {
		return ( window.amehpBackendData && window.amehpBackendData.brandIcons ) || [];
	}

	function readCardStore() {
		try {
			return JSON.parse( window.localStorage.getItem( CARD_STORE ) ) || {};
		} catch ( error ) {
			return {};
		}
	}

	function writeCardStore( store ) {
		try {
			window.localStorage.setItem( CARD_STORE, JSON.stringify( store ) );
		} catch ( error ) {
			// Storage can be full or blocked; the cards still work, they just
			// forget their state on reload.
		}
	}

	// The repeater's field key ("hp_amehp_icons" or "hp_amehp_custom_items"),
	// read off any input name in the row.
	function cardFieldKey( row ) {
		var input = row.querySelector( ':scope input[name], :scope select[name]' ),
			match = input && input.name ? input.name.match( /^(hp_[a-z0-9_]+)\[/ ) : null;

		return match ? match[ 1 ] : '';
	}

	// What the collapsed header should say: the chosen menu item for styling
	// rows, the typed label for custom items.
	function cardTitle( row ) {
		var select = row.querySelector( 'select[name$="[item]"]' ),
			text   = '';

		if ( select ) {
			var option = select.options[ select.selectedIndex ];

			text = option && option.value ? option.text : '';
		} else {
			var label = row.querySelector( 'input[name$="[label]"]' );

			text = label ? label.value : '';
		}

		text = ( text || '' ).trim();

		return text || cardLabels().newItem || '';
	}

	function cardIconName( row ) {
		var select = row.querySelector( 'select[name$="[icon]"]' ),
			value  = select ? ( select.value || '' ).trim() : '';

		return /^[a-z0-9-]+$/.test( value ) ? value : '';
	}

	function updateCardHead( row ) {
		var head = row.querySelector( ':scope > td.amehp-card-head' );

		if ( ! head ) {
			return;
		}

		head.querySelector( '.amehp-card-name' ).textContent = cardTitle( row );

		var icon = head.querySelector( '.amehp-card-icon' ),
			name = cardIconName( row );

		// className written whole, never from user text: the name is gated to
		// slug characters in cardIconName().
		icon.className = name ? 'amehp-card-icon fa-fw ' + ( -1 !== brandIcons().indexOf( name ) ? 'fa-brands' : 'fas fa-solid' ) + ' fa-' + name : 'amehp-card-icon';
	}

	function setCardCollapsed( row, collapsed, remember ) {
		row.classList.toggle( 'amehp-card--collapsed', collapsed );

		var toggle = row.querySelector( '.amehp-card-toggle' );

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			toggle.title = collapsed ? cardLabels().expand || '' : cardLabels().collapse || '';
			toggle.querySelector( '.dashicons' ).className = 'dashicons ' + ( collapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2' );
		}

		if ( remember ) {
			var key = cardFieldKey( row );

			if ( key ) {
				var store    = readCardStore(),
					rows     = row.parentElement ? Array.prototype.slice.call( row.parentElement.children ) : [],
					position = rows.indexOf( row );

				store[ key ]              = store[ key ] || {};
				store[ key ][ position ] = collapsed ? 1 : 0;

				writeCardStore( store );
			}
		}
	}

	function addCardHeads( container ) {

		// Read once for the whole pass: every row wants the same remembered
		// state, and nothing below writes it back (setCardCollapsed is called
		// with remember = false), so re-parsing the stored JSON per row was
		// work with no possible effect on the answer.
		var store = readCardStore();

		repeaterRows( container ).each( function () {
			var row = this;

			if ( row.querySelector( ':scope > td.amehp-card-head' ) ) {
				return;
			}

			var key = cardFieldKey( row );

			// Only this plugin's repeaters get the card treatment.
			if ( ! key || 0 !== key.indexOf( 'hp_amehp_' ) ) {
				return;
			}

			var head   = document.createElement( 'td' ),
				toggle = document.createElement( 'button' ),
				chev   = document.createElement( 'span' ),
				icon   = document.createElement( 'i' ),
				name   = document.createElement( 'span' );

			head.className   = 'amehp-card-head';
			toggle.type      = 'button';
			toggle.className = 'amehp-card-toggle';
			chev.className   = 'dashicons dashicons-arrow-up-alt2';
			icon.className   = 'amehp-card-icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			name.className   = 'amehp-card-name';

			toggle.appendChild( chev );
			head.appendChild( toggle );
			head.appendChild( icon );
			head.appendChild( name );

			/*
			 * Inserted at position 1, after core's drag-handle cell.
			 *
			 * Not for that cell's sake - it is hidden, and removeDragHandles()
			 * empties it a moment later - but for the REMOVE button's. The
			 * stylesheet pins the remove button with `td:last-child`, so this
			 * bar has to go anywhere except the end of the row. Position 1 is
			 * also what keeps the collapsed-card rule, which hides every cell
			 * that is neither the head nor the last, describing the right set.
			 */
			row.insertBefore( head, row.children[ 1 ] || null );

			// The whole bar toggles, not just the chevron button - it is a
			// bigger target and the bar contains no other controls.
			head.addEventListener( 'click', function () {
				setCardCollapsed( row, ! row.classList.contains( 'amehp-card--collapsed' ), true );
			} );

			updateCardHead( row );

			// Initial state: what was remembered for this position, else
			// collapsed when the row is filled and has company.
			var rows       = row.parentElement ? Array.prototype.slice.call( row.parentElement.children ) : [],
				position   = rows.indexOf( row ),
				remembered = store[ key ] ? store[ key ][ position ] : undefined,
				filled     = cardTitle( row ) !== ( cardLabels().newItem || '' ),
				collapsed  = 'undefined' !== typeof remembered ? !! remembered : ( filled && rows.length > 1 );

			setCardCollapsed( row, collapsed, false );
		} );
	}

	/*
	 * Takes the drag handle off this plugin's repeater cards.
	 *
	 * The menu is ordered by dragging the live preview, which shows the menu
	 * being reordered while it is reordered. Leaving a second set of handles
	 * on the cards offered a way to order the same menu that no longer
	 * ordered it - a row's position now only decides where a brand new item
	 * STARTS, before it is placed - and two controls that appear to do one
	 * job is the confusion the Order box was removed for in 3.2.0.
	 *
	 * The cell is emptied rather than removed: core's repeater clones the
	 * first row to make a new one (hivepress/assets/js/common.js, the
	 * repeater block), so a row with one cell fewer would produce new rows
	 * that no longer line up with the ones already on screen. The remove
	 * button and the collapsible header both stay.
	 */
	function removeDragHandles( container ) {
		repeaterRows( container ).find( '[data-sort]' ).each( function () {
			var key = cardFieldKey( this.closest( 'tr' ) );

			// Ours only. Another plugin's repeater on this screen keeps the
			// handle core gave it.
			if ( key && 0 === key.indexOf( 'hp_amehp_' ) ) {
				var cell = this.closest( 'td' );

				this.remove();

				// The now-empty cell is marked so the stylesheet can keep it
				// hidden: its other rule matches the handle itself, which has
				// just gone, and an unhidden empty cell holds a gap open at
				// the top of the card.
				if ( cell ) {
					cell.classList.add( 'amehp-card-sort' );
				}
			}
		} );
	}

	/*
	 * Stamps an id onto a custom item row that has none.
	 *
	 * The stored menu order refers to a custom item by this id. Core's
	 * repeater builds a new row by cloning the first one and blanking every
	 * input, so a row added on screen arrives with an empty id and would fall
	 * back to being identified by its position - which is the identity
	 * problem 3.3.0 exists to end. Generated here so the id is in the form
	 * before it is ever saved.
	 */
	function fillRowIds( container ) {
		// Found by name, not by a class: HivePress's hidden field drops the
		// class passed to it (see the field's own note in configs/settings.php).
		repeaterRows( container ).find( 'input[name^="hp_amehp_"][name$="[uid]"]' ).each( function () {
			if ( ! this.value ) {
				this.value = ( Math.random().toString( 36 ).slice( 2 ) + Math.random().toString( 36 ).slice( 2 ) ).slice( 0, 12 );
			}
		} );
	}

	/*
	 * Folds the Placeholder Pages section into one group per page.
	 *
	 * The section holds four settings for each of a dozen or so pages, which
	 * as a flat table is a very long list of rows whose labels all begin with
	 * the same page name. HivePress renders settings as plain table rows with
	 * no grouping of its own, so the grouping is added here: each page's rows
	 * are collected under a header carrying the same chevron as the repeater
	 * cards, and folded away by default.
	 *
	 * The page a row belongs to is read from its input's name, because that
	 * is the only thing on the row that names it unambiguously - the visible
	 * label is translated, and matching on translated text would work in
	 * English and quietly stop grouping in every other language.
	 */
	var PAGE_STORE = 'amehpPlaceholderPages';
	var PAGE_FIELDS = [ 'hp_amehp_page_icon_', 'hp_amehp_page_text_', 'hp_amehp_button_label_', 'hp_amehp_button_url_' ];

	function readPageStore() {
		try {
			return JSON.parse( window.localStorage.getItem( PAGE_STORE ) ) || {};
		} catch ( error ) {
			return {};
		}
	}

	function writePageStore( store ) {
		try {
			window.localStorage.setItem( PAGE_STORE, JSON.stringify( store ) );
		} catch ( error ) {
			// Storage blocked or full; the groups still fold, they just
			// forget on the next page load.
		}
	}

	// The page key a settings row belongs to, or '' when it is not one of
	// this section's rows.
	function rowPageKey( row ) {
		var control = row.querySelector( '[name^="hp_amehp_"]' ),
			name = control ? control.name : '',
			key = '';

		PAGE_FIELDS.forEach( function ( prefix ) {
			if ( ! key && 0 === name.indexOf( prefix ) ) {
				key = name.slice( prefix.length );
			}
		} );

		return key;
	}

	function setGroupOpen( group, open, remember ) {
		group.header.classList.toggle( 'amehp-page-group--collapsed', ! open );
		group.header.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		var chevron = group.header.querySelector( '.dashicons' );

		if ( chevron ) {
			chevron.className = 'dashicons ' + ( open ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' );
		}

		// The fields live inside the card, so folding it hides one element
		// rather than a list of rows sitting outside it.
		group.body.hidden = ! open;

		if ( remember ) {
			var store = readPageStore();

			store[ group.key ] = open ? 1 : 0;

			writePageStore( store );
		}
	}

	/*
	 * Takes the repeater cards' own width limit and hands it to the
	 * placeholder cards.
	 *
	 * A repeater card is 500px wide because HivePress core sets
	 * `table.hp-table { max-width: 500px }` on the table it lives in - not
	 * because anything here says so. A placeholder card had no such limit, so
	 * it filled its cell instead: 827px against the repeater cards' 500px in
	 * the same window, measured on 2026-08-30, and wider still as the window
	 * grew while the repeater cards never moved. Two families of card on one
	 * screen disagreeing about their width, and only one of them responding
	 * to the window.
	 *
	 * The value is READ from core's own rule rather than copied, so if core
	 * ever changes it the two stay together with nothing to remember. The
	 * max-width is read rather than the width, deliberately: max-width is the
	 * same string at every viewport, whereas the rendered width is whatever
	 * the window happened to be when this ran, which would pin the cards to a
	 * load-time measurement and be wrong after a resize. The CSS carries the
	 * same number as its fallback for the case where no repeater is on screen.
	 */
	function shareCardWidth() {
		var table = document.querySelector( 'div[data-component="repeater"] table.hp-table' );

		if ( ! table ) {
			return;
		}

		var limit = window.getComputedStyle( table ).maxWidth;

		if ( /^\d+(\.\d+)?px$/.test( limit ) ) {
			document.documentElement.style.setProperty( '--amehp-card-max-width', limit );
		}
	}

	function groupPlaceholderPages() {
		var pages = ( window.amehpBackendData && window.amehpBackendData.placeholderPages ) || {};

		if ( ! Object.keys( pages ).length ) {
			return;
		}

		shareCardWidth();

		var groups = {};

		document.querySelectorAll( '.hp-page form.hp-form--table tr' ).forEach( function ( row ) {
			var key = rowPageKey( row );

			if ( ! key || ! pages[ key ] ) {
				return;
			}

			if ( ! groups[ key ] ) {
				groups[ key ] = { key: key, rows: [], header: null };
			}

			groups[ key ].rows.push( row );
		} );

		var store = readPageStore();

		Object.keys( groups ).forEach( function ( key ) {
			var group = groups[ key ],
				first = group.rows[ 0 ];

			if ( ! first || ! first.parentElement ) {
				return;
			}

			/*
			 * ONE ROW PER PAGE, HOLDING A CARD THAT CONTAINS ITS OWN FIELDS.
			 *
			 * HivePress registers each setting separately and core renders
			 * each one as its own <tr>, so declaring these four as belonging
			 * to a page cannot nest them: until 3.3.1 the card was a header
			 * strip with its four fields rendered underneath it as ordinary
			 * two-column rows, out on the grey page background. Folding
			 * worked, so the wiring was right, but the panel visibly
			 * contained nothing and the fields read as the section's rather
			 * than the page's. Reported from the screen on 2026-08-30.
			 *
			 * So the rows are moved: each field's label block and its control
			 * are lifted out of its <tr> into the card body, and the emptied
			 * <tr> is dropped. The inputs themselves are never touched or
			 * rebuilt - they keep their registered names and stay inside the
			 * form, which is the whole of what makes a field post - so the
			 * options save exactly as before.
			 *
			 * The header is a settings row of the same shape as every other
			 * one on the tab: an empty label cell, then the card in the field
			 * cell. That is what lines the cards up with the controls above
			 * and below them, rather than a left margin measured off the
			 * label column by eye - core sizes that column itself, and at
			 * 782px it stops existing entirely and stacks the two cells, at
			 * which point a hardcoded indent would strand the cards inset on
			 * a phone. Structure inherits all of that for free, and it is
			 * right in RTL for the same reason.
			 */
			var headerRow = document.createElement( 'tr' ),
				labelCell = document.createElement( 'th' ),
				cell = document.createElement( 'td' ),
				card = document.createElement( 'div' ),
				button = document.createElement( 'button' ),
				chevron = document.createElement( 'span' ),
				title = document.createElement( 'span' ),
				body = document.createElement( 'div' );

			headerRow.className = 'amehp-page-group';
			labelCell.className = 'amehp-page-group__label';

			card.className = 'amehp-page-group__card amehp-card-surface';

			button.type = 'button';
			button.className = 'amehp-page-group__toggle amehp-card-toggle-bar';
			button.setAttribute( 'aria-expanded', 'true' );

			chevron.className = 'dashicons dashicons-arrow-up-alt2';
			chevron.setAttribute( 'aria-hidden', 'true' );

			title.className = 'amehp-page-group__title';
			title.textContent = pages[ key ];

			body.className = 'amehp-page-group__body';

			button.appendChild( chevron );
			button.appendChild( title );
			card.appendChild( button );
			card.appendChild( body );
			cell.appendChild( card );
			headerRow.appendChild( labelCell );
			headerRow.appendChild( cell );

			// Placed before the rows it is about to absorb, so the section
			// keeps the order the fields were registered in.
			first.parentElement.insertBefore( headerRow, first );

			group.rows.forEach( function ( row ) {
				var field = document.createElement( 'div' ),
					rowLabel = row.querySelector( 'th > div' ) || row.querySelector( 'th' ),
					control = row.querySelector( 'td' );

				field.className = 'amehp-page-group__field';

				/*
				 * The label block is MOVED, not rebuilt from its text: it
				 * carries the <label> element core associated with the field
				 * and the tooltip beside it, and re-creating it from
				 * textContent would drop both.
				 */
				if ( rowLabel ) {
					rowLabel.classList.add( 'amehp-field-label' );

					field.appendChild( rowLabel );
				}

				// The control keeps its own wrapper, since HivePress hangs
				// the "_parent" show/hide behaviour off that element.
				if ( control ) {
					while ( control.firstChild ) {
						field.appendChild( control.firstChild );
					}
				}

				body.appendChild( field );

				row.remove();
			} );

			group.header = button;
			group.body = body;

			button.addEventListener( 'click', function () {
				setGroupOpen( group, 'false' === button.getAttribute( 'aria-expanded' ), true );
			} );

			// Folded by default: the section is a list of pages to choose
			// from, and a dozen pages times four settings is not a list.
			setGroupOpen( group, 'undefined' !== typeof store[ key ] ? !! store[ key ] : false, false );
		} );
	}

	function init( container ) {
		// Label the fields before the colour pickers wrap their inputs.
		labelFields( container );
		initColourPickers( container );
		addCardHeads( container );
		removeDragHandles( container );
		fillRowIds( container );
	}

	$( document ).ready( function () {
		init( $( 'body' ) );
		groupPlaceholderPages();
		addSettingsChrome();
	} );

	// Keep the card headers in step with the fields that name them.
	$( document ).on( 'change input', 'select[name$="[item]"], select[name$="[icon]"], input[name$="[label]"]', function () {
		var row = $( this ).closest( 'tr' ).get( 0 );

		if ( row ) {
			updateCardHead( row );
		}
	} );

	// Initialise newly added repeater rows. The colour fields are reset FIRST,
	// before the pickers are built over them - see resetClonedColours().
	$( document ).on( 'hivepress:init', function ( event, container ) {
		resetClonedColours( container );
		init( container );
	} );
} )( jQuery );
