<?php
/**
 * Account menu enhancer component.
 *
 * @package AccountMenuEnhancer\Components
 */

namespace HivePress\Components;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Enhances the HivePress and WooCommerce account menus.
 */
final class Amehp_Menu_Enhancer extends Component {

	/**
	 * Suppresses the plugin menu filters while fetching the base menus.
	 *
	 * @var bool
	 */
	protected $suppressed = false;

	/**
	 * Whether a base HivePress menu is being built right now.
	 *
	 * Breaks the recursion described in get_base_hp_items(). Separate from $suppressed, which
	 * answers a different question: that one stops this component altering a menu, this one stops
	 * it starting a second build inside the first.
	 *
	 * @var bool
	 */
	protected $building_hp_items = false;

	/**
	 * Base HivePress menu items cache.
	 *
	 * Null until a menu has been built under ordinary conditions. A menu built inside another
	 * plugin's menu pass is deliberately never stored here - see get_base_hp_items().
	 *
	 * @var array|null
	 */
	protected $hp_items;

	/**
	 * Base WooCommerce menu items cache.
	 *
	 * @var array|null
	 */
	protected $wc_items;

	/**
	 * Registered WooCommerce account endpoint names cache.
	 *
	 * @var array|null
	 */
	protected $wc_endpoints;

	/**
	 * URLs of the items injected into the WooCommerce menu.
	 *
	 * @var array
	 */
	protected $wc_urls = [];

	/**
	 * The cleaned record of items this site renders, for this request.
	 *
	 * Null until read. Set back to null by record_seen_items() whenever it
	 * writes, so a caller after a write never sees the pre-write list - which
	 * is what get_seen_items() returned before this cache existed, and what
	 * logic test P6 checks.
	 *
	 * @var array|null
	 */
	protected $seen_items;

	/**
	 * Whether the last read dropped a custom item's record that no longer
	 * belongs to anything.
	 *
	 * Set by get_seen_items() alongside the cache above, and read by
	 * record_seen_items() so the shrunken list is written back rather than
	 * waiting for some unrelated change to carry it. See the pruning note in
	 * get_seen_items() for why those records exist at all.
	 *
	 * @var bool
	 */
	protected $seen_items_compacted = false;

	/**
	 * Class constructor.
	 *
	 * @param array $args Component arguments.
	 */
	public function __construct( $args = [] ) {
		if ( ! is_admin() ) {

			// Alter the HivePress account menu.
			add_filter( 'hivepress/v1/menus/user_account', [ $this, 'alter_hp_menu' ], 1000 );

			/*
			 * The menu is filtered at TWO stages, and both matter. The filter above runs in
			 * Menu::__construct() (hivepress/includes/menus/class-menu.php:94), BEFORE boot()
			 * applies the second documented extension point, `.../user_account/items`
			 * (class-menu.php:125). Stage ordering is structural: no priority on the constructor
			 * filter can ever see an item another extension adds on the /items filter - and real
			 * extensions register that way (Vendor Analytics adds its item there). Working only at
			 * the constructor stage, this plugin could neither record such items nor hide them:
			 * the hidden-key unset ran before the item existed, so "hiding" it did nothing.
			 */
			add_filter( 'hivepress/v1/menus/user_account/items', [ $this, 'alter_hp_menu_items' ], 1000 );

			// Keep the account page's own redirect on this site.
			add_filter( 'hivepress/v1/routes', [ $this, 'alter_account_route' ], 1000 );

			// Enqueue the front-end assets.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ], 20 );
		} else {

			// Enqueue the settings screen assets.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_assets' ] );

			// Add the live preview panel to the settings tab. Priority 20
			// because HivePress registers its own sections at 10 and this has
			// to see them.
			add_action( 'admin_init', [ $this, 'register_preview_section' ], 20 );
		}

		if ( hp\is_plugin_active( 'woocommerce' ) ) {
			if ( ! is_admin() ) {

				// Alter the WooCommerce account menu.
				add_filter( 'woocommerce_account_menu_items', [ $this, 'alter_wc_menu' ], 999 );

				// Set the WooCommerce account template.
				add_filter( 'wc_get_template', [ $this, 'set_account_template' ], 20, 2 );

				// Alter the HivePress account page template.
				add_filter( 'hivepress/v1/templates/user_account_page', [ $this, 'alter_account_page' ] );

				// Hide the theme page header on the unified account pages.
				add_filter( 'hivetheme/v1/areas/site_hero', [ $this, 'hide_theme_page_header' ], 110 );

				// Render the endpoint title on the unified account pages.
				add_action( 'woocommerce_account_content', [ $this, 'render_page_title' ], 1 );
			}

			// Alter the WooCommerce endpoint URLs.
			add_filter( 'woocommerce_get_endpoint_url', [ $this, 'alter_wc_endpoint_url' ], 1000, 2 );
		}

		parent::__construct( $args );
	}

	/*
	-------------------------------------------------------------------------
	Options
	-------------------------------------------------------------------------
	*/

	/**
	 * Checks if the WooCommerce integration is enabled.
	 *
	 * One switch since version 3.0.0: it merges the menu items into every
	 * account menu AND renders the WooCommerce pages inside the HivePress
	 * layout, replacing the separate "unify" and "merge" checkboxes that
	 * confused owners. The migration in amehp_maybe_migrate() runs on
	 * admin_init, so until an admin has visited wp-admin the new option is
	 * absent on upgraded sites; the legacy pair is honoured in that window
	 * (either one on counts as on, matching the migration).
	 *
	 * CALL THIS ONE EVERYWHERE. Until 3.3.5 there were also is_merge_enabled()
	 * and is_unify_enabled(), named after the two checkboxes 3.0.0 replaced,
	 * and both had been one-line passthroughs to this since that merge. Two
	 * spare names for one switch is how a later reader concludes there must be
	 * two switches and gives them different answers; the menu merge and the
	 * account layout are one setting and are meant to move together. Logic
	 * tests E6/E7 (the merge) and E11/E12 (the layout) fail together if they
	 * ever stop doing so.
	 *
	 * @return bool
	 */
	protected function is_wc_integration_enabled() {
		$value = get_option( 'hp_amehp_wc_integration', null );

		if ( null === $value ) {
			return (bool) get_option( 'hp_amehp_merge_menus', true ) || (bool) get_option( 'hp_amehp_unify_account', true );
		}

		return (bool) $value;
	}

	/**
	 * Checks if the WooCommerce menu counters are enabled.
	 *
	 * @return bool
	 */
	protected function is_badges_enabled() {
		return $this->is_wc_integration_enabled() && (bool) get_option( 'hp_amehp_wc_badges', true );
	}

	/**
	 * Gets the hidden item keys.
	 *
	 * Hidden from BOTH account menus, which is what this list has always meant
	 * and what every site that has ever saved it holds. The WooCommerce-only
	 * list below is an addition to it, never a replacement, so no stored value
	 * changes meaning and nothing has to be migrated.
	 *
	 * @return array
	 */
	protected function get_hidden_keys() {
		return $this->read_item_keys( 'hp_amehp_hidden_items' );
	}

	/**
	 * Gets the item keys hidden from the WooCommerce account menu alone.
	 *
	 * Read by alter_wc_menu() and by nothing else. alter_hp_menu() must never
	 * consult it: the whole point of the setting is an item that stays in the
	 * HivePress menu while leaving the WooCommerce one, so a second reader on
	 * the HivePress side would turn it into a duplicate of the list above.
	 *
	 * @return array
	 */
	protected function get_wc_hidden_keys() {
		return $this->read_item_keys( 'hp_amehp_hidden_wc_items' );
	}

	/**
	 * Reads a stored list of menu item keys.
	 *
	 * One reader for both hidden lists, because they are read in the same
	 * places and have to be cleaned the same way. Two copies of the guard
	 * below is how one of them stops being applied.
	 *
	 * @param string $option Option name.
	 * @return array
	 */
	protected function read_item_keys( $option ) {
		$keys = get_option( $option );

		/*
		 * Strings only, because every caller hands these to strpos() and a
		 * non-string there is a TypeError on PHP 8 - which, on a filter that
		 * runs for every account menu, takes the whole front end down with
		 * "There has been a critical error". The settings screen cannot
		 * produce such a value (core's select field flattens what it stores),
		 * so this guards the ways an option can be written that are not the
		 * settings screen: WP-CLI, a migration, another plugin, a restored
		 * database. get_menu_order() re-checks its own stored keys for the
		 * same reason; this one was trusting the array as it came out.
		 */
		return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : [];
	}

	/**
	 * Gets the owner's chosen menu order, as settings keys.
	 *
	 * Stored as one comma-separated string by the drag-to-reorder control in
	 * the live preview panel, because a settings field holds a scalar and a
	 * hidden input is what can post from inside that panel. Read back through
	 * a strict pattern rather than trusted: the value arrives from a form
	 * field, and every key is used to look up a menu item.
	 *
	 * @return array
	 */
	protected function get_menu_order() {
		$stored = get_option( 'hp_amehp_menu_order' );

		if ( ! is_string( $stored ) || '' === $stored ) {
			return [];
		}

		$keys = [];

		foreach ( explode( ',', $stored ) as $key ) {
			$key = trim( $key );

			// The three shapes the settings screen uses: a HivePress item, a
			// WooCommerce endpoint, or one of this plugin's custom items.
			if ( $key && preg_match( '/^(hp:|wc:)?[A-Za-z0-9_-]+$/', $key ) ) {
				$keys[] = $key;
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Gets the menu item names one settings key stands for.
	 *
	 * The inverse of get_settings_key(), and the single place the mapping is
	 * written. The stored keys are the settings screen's own
	 * (`hp:listings_edit`, `wc:downloads`) while the menus are keyed by the bare
	 * item name or endpoint, so the prefix is mapped off; and the names
	 * HivePress core gives the WooCommerce order lists are added alongside the
	 * endpoint, so one key reaches the row whichever of the two names the menu
	 * happens to use.
	 *
	 * IT LIVED IN TWO PLACES UNTIL 3.3.10 - get_menu_order_positions() and
	 * get_item_selectors() each carried their own copy of the same six lines,
	 * with a comment in each pointing at the other and asking that they be kept
	 * in step. Two copies of a mapping that has to agree is how they stop
	 * agreeing, and the cost of a disagreement here is an item that can be
	 * styled but not dragged, or the reverse.
	 *
	 * @param string $key Settings item key.
	 * @return array Menu item names.
	 */
	protected function get_key_menu_names( $key ) {
		if ( 0 === strpos( $key, 'hp:' ) ) {
			return [ substr( $key, strlen( 'hp:' ) ) ];
		}

		if ( 0 === strpos( $key, 'wc:' ) ) {
			$endpoint = substr( $key, strlen( 'wc:' ) );
			$names    = [ $endpoint ];

			// Cover the item names HivePress core uses for these two lists.
			foreach ( $this->get_core_wc_items() as $name => $core_endpoint ) {
				if ( $core_endpoint === $endpoint ) {
					$names[] = $name;
				}
			}

			return $names;
		}

		return [ $key ];
	}

	/**
	 * Gets the account menu items HivePress core adds for WooCommerce.
	 *
	 * These are the reason this plugin has to think about the two menus
	 * separately at all. HivePress core puts them in ITS OWN account menu,
	 * conditionally and with no route of their own - "Orders" as soon as the
	 * member has placed one, "Subscriptions" once they hold one - and it does so
	 * whether or not this plugin's WooCommerce integration is switched on
	 * (hivepress/includes/components/class-woocommerce.php:447-464, core 1.7.31,
	 * verified 2026-08-30). So they are NOT endpoints this plugin merges in:
	 * they belong to the HivePress menu on their own account, and the settings
	 * screen lists them under their WooCommerce names so an owner sees one
	 * option per real destination rather than two that behave differently.
	 *
	 * Named rather than derived because the naming is core's own choice and
	 * nothing in the menu records it: the item is `orders_view` in one menu and
	 * the `orders` endpoint in the other, and there is no property on either
	 * that says so. Every other place that needs the pairing asks this method,
	 * so a third name core adds later is one line here.
	 *
	 * @return array Menu item name mapped to its WooCommerce endpoint.
	 */
	protected function get_core_wc_items() {
		return [
			'orders_view'        => 'orders',
			'subscriptions_view' => 'subscriptions',
		];
	}

	/**
	 * Gets the chosen position of each menu item, keyed by MENU item name.
	 *
	 * @return array Menu item name mapped to its position.
	 */
	protected function get_menu_order_positions() {
		$positions = [];
		$position  = 0;

		foreach ( $this->get_menu_order() as $key ) {
			foreach ( $this->get_key_menu_names( $key ) as $name ) {
				if ( ! isset( $positions[ $name ] ) ) {
					$positions[ $name ] = $position;
				}
			}

			++$position;
		}

		return $positions;
	}

	/**
	 * Applies the owner's chosen order to a set of menu items.
	 *
	 * Every item present is given a fresh `_order`, so the whole menu follows
	 * the stored list rather than half of it: an item the owner has placed
	 * takes its stored position, and an item they have never placed is slotted
	 * in beside the placed item its own native `_order` puts it next to.
	 *
	 * UNPLACED ITEMS WERE APPENDED AFTER EVERYTHING ELSE UNTIL 3.3.10, and that
	 * is the whole of the bug this method was changed for. HivePress core adds
	 * "Placed Orders" to the account menu the moment a member has an order, with
	 * a native `_order` of 40 that puts it in the middle of the menu - but an
	 * owner who had ever dragged the menu had no stored position for an item
	 * they had never been shown, so it fell into the appended block and rendered
	 * BELOW Sign Out. Reported from a live site on 2026-08-30. The old comment
	 * here argued that appending was the honest answer because the alternative
	 * was "inventing a position for a page nobody has ever put anywhere". It is
	 * not invented: the extension that registered the item chose that number, it
	 * is the position the same menu uses on a site that has never been dragged,
	 * and the settings screen's preview shows the item either way, so nothing is
	 * lost by putting it where it belongs.
	 *
	 * The owner's own arrangement is not touched. Each unplaced item is inserted
	 * after the LAST item already in the sequence whose native `_order` is no
	 * higher than its own, so the placed items keep their stored order and their
	 * relative positions exactly, whatever the arrangement looks like - pinned
	 * in the migration tests, which compare the placed items alone before and
	 * after. Unplaced items are taken in ascending native order, so they keep
	 * their own relative order too.
	 *
	 * Items merely absent from this menu are untouched, which is what keeps a
	 * hidden item, or a WooCommerce row on a site not combining the menus,
	 * from disturbing the order of everything else.
	 *
	 * @param array $items Menu items keyed by name.
	 * @return array
	 */
	protected function apply_menu_order( $items ) {
		$positions = $this->get_menu_order_positions();

		if ( ! $positions || ! is_array( $items ) ) {
			return $items;
		}

		$placed   = [];
		$unplaced = [];
		$native   = [];

		foreach ( $items as $name => $item ) {
			$native[ $name ] = is_array( $item ) && isset( $item['_order'] ) ? (int) $item['_order'] : 100;

			if ( isset( $positions[ $name ] ) ) {
				$placed[ $name ] = $positions[ $name ];
			} else {
				$unplaced[ $name ] = $native[ $name ];
			}
		}

		asort( $placed );
		asort( $unplaced );

		$sequence = array_keys( $placed );

		foreach ( $unplaced as $name => $item_order ) {
			$index = 0;

			/*
			 * The LAST match wins, not the first. An owner is free to drag a
			 * late item to the top, and anchoring on the first item with a lower
			 * native order would let that one stray row capture every unplaced
			 * item behind it.
			 */
			foreach ( $sequence as $offset => $placed_name ) {
				if ( $native[ $placed_name ] <= $item_order ) {
					$index = $offset + 1;
				}
			}

			array_splice( $sequence, $index, 0, [ $name ] );
		}

		$order = 10;

		foreach ( $sequence as $name ) {
			if ( is_array( $items[ $name ] ) ) {
				$items[ $name ]['_order'] = $order;
			}

			$order += 10;
		}

		return $items;
	}

	/**
	 * Gets every menu item key a saved styling row refers to.
	 *
	 * Deliberately looser than get_icon_rules(): a row counts here as soon as it names an item,
	 * whether or not it also sets an icon. Used to keep saved keys selectable on the settings
	 * screen, where dropping one costs the owner the whole row. See get_menu_item_options().
	 *
	 * @return array
	 */
	protected function get_styled_keys() {
		$keys = [];
		$rows = get_option( 'hp_amehp_icons' );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && ! empty( $row['item'] ) && is_string( $row['item'] ) ) {
					$keys[] = (string) $row['item'];
				}
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Gets the icon assignments keyed by menu item.
	 *
	 * @return array
	 */
	protected function get_icon_rules() {
		$rules = [];
		$rows  = get_option( 'hp_amehp_icons' );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && ! empty( $row['item'] ) && ! empty( $row['icon'] ) ) {
					$rules[ (string) $row['item'] ] = [
						'icon'   => (string) $row['icon'],
						'colour' => isset( $row['colour'] ) && is_string( $row['colour'] ) ? $row['colour'] : '',
						'weight' => isset( $row['weight'] ) && is_string( $row['weight'] ) ? $row['weight'] : '',
					];
				}
			}
		}

		return $rules;
	}

	/**
	 * Gets the custom menu items.
	 *
	 * @return array
	 */
	protected function get_custom_items() {
		$items = [];
		$rows  = get_option( 'hp_amehp_custom_items' );

		if ( is_array( $rows ) ) {
			foreach ( array_values( $rows ) as $index => $row ) {
				if ( ! is_array( $row ) || ! isset( $row['label'] ) || '' === (string) $row['label'] ) {
					continue;
				}

				/*
				 * The row's own id, and why it exists.
				 *
				 * This key is what the stored menu order, the emitted CSS and
				 * the WooCommerce URL filter all refer to an item by. Until
				 * 3.3.0 it was the row's POSITION, which meant the identity of
				 * every custom item changed the moment the rows moved:
				 * deleting the first of three handed its saved place in the
				 * menu to the second, and the third quietly lost its own. The
				 * ordering feature added in 3.2.0 is what made that
				 * load-bearing, so 3.3.0 gives each row an id of its own and
				 * amehp_migrate_v330_settings() stamps one onto every row that
				 * predates this, rewriting the stored order to match in the
				 * same pass.
				 *
				 * The positional key remains the fallback, for a row saved
				 * while the migration had not yet run and for one whose id
				 * somehow collides, so an id that never arrives degrades to
				 * exactly the old behaviour rather than to no item at all.
				 */
				$uid = isset( $row['uid'] ) && is_string( $row['uid'] ) && preg_match( '/^[A-Za-z0-9]{6,32}$/', $row['uid'] ) ? $row['uid'] : '';
				$key = 'amehp_item_' . ( $index + 1 );

				if ( $uid && ! isset( $items[ 'amehp_item_' . $uid ] ) ) {
					$key = 'amehp_item_' . $uid;
				}

				$items[ $key ] = [
					'label'       => (string) $row['label'],
					'link'        => isset( $row['link'] ) ? (string) $row['link'] : '',
					'url'         => isset( $row['url'] ) ? (string) $row['url'] : '',
					'icon'        => isset( $row['icon'] ) ? (string) $row['icon'] : '',
					'colour'      => isset( $row['colour'] ) && is_string( $row['colour'] ) ? $row['colour'] : '',
					'weight'      => isset( $row['weight'] ) && is_string( $row['weight'] ) ? $row['weight'] : '',
					'text_colour' => isset( $row['text_colour'] ) && is_string( $row['text_colour'] ) ? $row['text_colour'] : '',
					'menus'       => isset( $row['menus'] ) && in_array( $row['menus'], [ 'hivepress', 'woocommerce' ], true ) ? $row['menus'] : 'both',

					/*
					 * Where the item sits when the owner has not placed it in
					 * the live preview.
					 *
					 * A number stored by the Order box, which was retired in
					 * 3.2.0, still wins - that is what keeps an upgraded site
					 * rendering as it did. Otherwise the row's position gives
					 * it a defined spot near the end of the menu, below the
					 * built-in items, from which it can be dragged. Once it IS
					 * dragged, apply_menu_order() overrides this outright, so
					 * this value only ever decides where an item STARTS.
					 */
					'order'       => isset( $row['order'] ) && is_numeric( $row['order'] ) ? (int) $row['order'] : 100 + $index,
					'roles'       => isset( $row['roles'] ) && is_array( $row['roles'] ) ? $row['roles'] : [],
				];
			}
		}

		return $items;
	}

	/*
	-------------------------------------------------------------------------
	Base menus
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets the HivePress account menu items without the plugin additions.
	 *
	 * MEMOISED, BUT ONLY WHEN THE MENU WAS BUILT UNDER ORDINARY CONDITIONS.
	 *
	 * This used to cache whatever the account menu looked like the first moment anything asked,
	 * and "anything" includes another plugin. Building a `User_Account` menu fires
	 * `hivepress/v1/menus/user_account/items`, this component answers on it, and answering calls
	 * back in here - so a neighbour constructing a menu of its own for its own purposes filled
	 * this cache from inside its callback, under whatever conditions that callback had set up.
	 * The result stood for the rest of the request, including for the settings screen, which needs
	 * the complete list. Caught in a control run on 2026-08-24: the cached menu came back
	 * poisoned. Persistent Account Menu 1.6.6 works around it from its own side by standing this
	 * component's callbacks down during its probe, but the fragility is ours, and the next plugin
	 * to build a `User_Account` menu will not know to do that.
	 *
	 * So: a menu built while that filter is already running is used for the call that asked, and
	 * NOT remembered. The next caller in an ordinary context gets a fresh, clean build and that
	 * one is cached. The cost is at most a rebuild or two per request on a page where a neighbour
	 * probes the menu; the alternative is a wrong menu for the whole request.
	 *
	 * The suppression flag is a different guard and still needed: it stops THIS component's own
	 * additions and removals re-entering the menu it is building.
	 *
	 * @return array
	 */
	protected function get_base_hp_items() {
		if ( isset( $this->hp_items ) ) {
			return $this->hp_items;
		}

		/*
		 * Re-entrancy guard, and it has to be explicit.
		 *
		 * Building the menu below fires `hivepress/v1/menus/user_account/items`, and a callback on
		 * that filter asking us for the base menu would start another build, for ever. The old
		 * code survived that by accident: it assigned `$this->hp_items = []` BEFORE building, so a
		 * re-entrant call hit the isset() and returned the empty array. That is a recursion guard
		 * disguised as an initialiser, and removing the eager assignment - as the caching fix
		 * above does - silently removed the guard with it. Proved by hanging a test process on
		 * 2026-08-24. Keep this flag; the empty return is what breaks the cycle.
		 */
		if ( $this->building_hp_items ) {
			return [];
		}

		$items = [];

		if ( class_exists( '\HivePress\Menus\User_Account' ) ) {
			$this->building_hp_items = true;
			$this->suppressed        = true;

			try {
				$items = ( new \HivePress\Menus\User_Account() )->get_items();
			} catch ( \Throwable $throwable ) {

				// Third-party menu items can resolve their labels via
				// route title callbacks that expect the front-end query
				// context, so fall back to an empty base menu if the
				// menu cannot be built in the current context.
				$items = [];
			}

			$this->suppressed        = false;
			$this->building_hp_items = false;
		}

		if ( ! $this->is_menu_context_borrowed() ) {
			$this->hp_items = $items;
		}

		return $items;
	}

	/**
	 * Whether the account menu is being built inside somebody else's menu pass.
	 *
	 * `doing_filter()` answers for a specific hook and is true for the whole time that hook is
	 * running, which is exactly the window in which a menu built here describes somebody else's
	 * conditions rather than the site's. Both filters are named because either can be the outer
	 * one: a neighbour probing the HivePress menu, or this component's own WooCommerce merge
	 * running at priority 999 inside WooCommerce's list.
	 *
	 * @return bool
	 */
	protected function is_menu_context_borrowed() {
		foreach ( [ 'hivepress/v1/menus/user_account/items', 'hivepress/v1/menus/user_account', 'woocommerce_account_menu_items' ] as $hook ) {
			if ( doing_filter( $hook ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the WooCommerce account endpoints that can sensibly appear as menu items.
	 *
	 * Read from the registered query vars rather than from the menu, because those are declared by
	 * whoever owns the endpoint and do not change with who is signed in.
	 *
	 * Most of what WooCommerce registers is not a menu item at all - the page behind one order, a
	 * password reset, the actions on a saved card - so those are named and excluded. Anything else,
	 * including endpoints added by other plugins, is offered.
	 *
	 * @return array Endpoint slugs mapped to a readable label.
	 */
	protected function get_registered_wc_endpoints() {
		if ( ! function_exists( 'WC' ) || ! WC()->query || ! method_exists( WC()->query, 'get_query_vars' ) ) {
			return [];
		}

		/**
		 * Filters the account endpoints never offered as hideable menu items.
		 *
		 * @hook amehp/hidden_endpoint_exclusions
		 * @param {array} $excluded Endpoint slugs.
		 * @return {array} Endpoint slugs.
		 */
		$excluded = (array) apply_filters(
			'amehp/hidden_endpoint_exclusions',
			[
				'order-pay',
				'order-received',
				'view-order',
				'lost-password',
				'add-payment-method',
				'delete-payment-method',
				'set-default-payment-method',
				'view-subscription',
				'subscription-payment-method',
			]
		);

		$endpoints = [];

		foreach ( array_keys( (array) WC()->query->get_query_vars() ) as $endpoint ) {
			$endpoint = (string) $endpoint;

			if ( ! $endpoint || in_array( $endpoint, $excluded, true ) ) {
				continue;
			}

			$endpoints[ $endpoint ] = ucwords( str_replace( [ '-', '_' ], ' ', $endpoint ) );
		}

		return $endpoints;
	}

	/**
	 * Gets the gap between a menu icon and its wording.
	 *
	 * Returned as a CSS length so the default can stay relative - the gap then grows with the
	 * theme's text size, which suits most sites - while a number typed into the setting is honoured
	 * exactly, in pixels, for an owner who wants it tighter than that.
	 *
	 * @return string
	 */
	protected function get_chosen_icon_spacing() {
		$spacing = get_option( 'hp_amehp_icon_spacing' );

		// A cleared box means "leave it alone"; a stored 0 means "no gap". Only a number counts.
		if ( ! is_numeric( $spacing ) ) {
			return '';
		}

		return max( 0, min( 60, (int) $spacing ) ) . 'px';
	}

	/**
	 * Gets the gap between a menu item's icon and its text.
	 *
	 * @return string A CSS length, for use inside the icon rule.
	 */
	protected function get_icon_spacing() {
		$spacing = $this->get_chosen_icon_spacing();

		if ( '' === $spacing ) {
			return '0.5em';
		}

		/*
		 * Marked important, and only when the owner has actually typed a number.
		 *
		 * Themes and site customisers style these icons themselves, and they do it with selectors
		 * that outrank ours: a real site was found carrying
		 * `.hp-widget.hp-menu--user-account .hp-menu__item > a::before { margin-inline-end: 10px }`,
		 * which is three classes to our two and therefore wins however we order the stylesheets. Our
		 * rule was emitted correctly and simply had no effect, which reads to an owner as a setting
		 * that does nothing - and it did nothing only on the account sidebar, where that rule
		 * applied, so it appeared to work in one menu and not the other.
		 *
		 * Left empty, the default stays polite and lets the theme win, which is why the important is
		 * on this branch alone: a number in this box is an owner overruling their theme on purpose.
		 */
		return $spacing . ' !important';
	}

	/**
	 * Gets the WooCommerce account menu items without the plugin additions.
	 *
	 * @return array
	 */
	protected function get_base_wc_items() {
		if ( ! isset( $this->wc_items ) ) {
			$this->wc_items = [];

			if ( function_exists( 'wc_get_account_menu_items' ) ) {
				$this->suppressed = true;

				try {
					$this->wc_items = wc_get_account_menu_items();
				} catch ( \Throwable $throwable ) {

					// Third-party menu filters can expect the front-end query
					// context, so fall back to an empty base menu if the menu
					// cannot be built in the current context.
					$this->wc_items = [];
				}

				$this->suppressed = false;
			}
		}

		return $this->wc_items;
	}

	/**
	 * Gets the account endpoints WooCommerce actually has registered.
	 *
	 * The keys of `wc_get_account_menu_items()` are endpoint names by convention only - the filter
	 * behind it is also how a plugin adds a plain link to the WooCommerce account menu, and such a
	 * link has no endpoint anywhere. `is_wc_endpoint_item()` below is what tells the two apart.
	 *
	 * @return array Endpoint names.
	 */
	protected function get_wc_endpoints() {
		if ( ! isset( $this->wc_endpoints ) ) {
			$this->wc_endpoints = [];

			if ( function_exists( 'WC' ) && is_object( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {

				// Keyed by endpoint name, valued by the (translatable) slug in the URL. The names are
				// what the menu is keyed by, so it is the keys that are wanted here.
				$this->wc_endpoints = array_keys( (array) WC()->query->get_query_vars() );
			}
		}

		return $this->wc_endpoints;
	}

	/**
	 * Checks whether a WooCommerce menu key is a real account page.
	 *
	 * A plugin may add whatever it likes to the WooCommerce account menu, and not every addition is
	 * an account page: User Switching adds a "Switch back to ..." action, which is a link that acts
	 * and returns rather than a page a member can visit. Merging one of those into the HivePress
	 * account menu puts it somewhere its author never intended - reported by Chris on 2026-09-02,
	 * with the action showing up in the HivePress account dropdown mid-switch - and the row does not
	 * even work, because the URL the merge builds for it is `wc_get_account_endpoint_url()` on a name
	 * WooCommerce has no endpoint for, which resolves to a 404 under /my-account/.
	 *
	 * So only genuine endpoints are merged. Dashboard is the one exception worth spelling out: it is
	 * the my-account page itself rather than an endpoint on it, so it is never a query var.
	 *
	 * If the endpoint list cannot be read at all, every item is treated as an endpoint. An empty list
	 * means "WooCommerce could not be asked", not "WooCommerce has no account pages", and dropping
	 * the whole WooCommerce section on the strength of a failed question would be a far worse outcome
	 * than the stray row this is here to prevent.
	 *
	 * @param string $endpoint Menu item key.
	 * @return bool
	 */
	protected function is_wc_endpoint_item( $endpoint ) {
		$endpoints = $this->get_wc_endpoints();

		if ( ! $endpoints ) {
			return true;
		}

		return 'dashboard' === $endpoint || in_array( $endpoint, $endpoints, true );
	}

	/*
	-------------------------------------------------------------------------
	HivePress menu
	-------------------------------------------------------------------------
	*/

	/**
	 * Alters the HivePress account menu.
	 *
	 * @param array $menu Menu arguments.
	 * @return array
	 */
	public function alter_hp_menu( $menu ) {
		if ( $this->suppressed ) {
			return $menu;
		}

		if ( ! isset( $menu['items'] ) || ! is_array( $menu['items'] ) ) {
			$menu['items'] = [];
		}

		// Recording moved to alter_hp_menu_items(): the /items stage sees the COMPLETE set,
		// including items other extensions add after this constructor-stage filter has run.

		$hidden = $this->get_hidden_keys();

		/*
		 * Remove the hidden items.
		 *
		 * Every name the key stands for, so hiding "Orders (WooCommerce)" also
		 * removes the list HivePress core adds to this menu under its own name.
		 * A custom item's key is left alone here: those are not in this menu
		 * until this method puts them there, and it skips a hidden one below.
		 */
		foreach ( $hidden as $key ) {
			if ( 0 !== strpos( $key, 'hp:' ) && 0 !== strpos( $key, 'wc:' ) ) {
				continue;
			}

			foreach ( $this->get_key_menu_names( $key ) as $name ) {
				unset( $menu['items'][ $name ] );
			}
		}

		// Add the WooCommerce items.
		if ( $this->is_wc_integration_enabled() && function_exists( 'wc_get_account_endpoint_url' ) ) {

			// Get the existing item URLs.
			$urls = [];

			foreach ( $menu['items'] as $item ) {
				$url = $this->get_item_url( $item );

				if ( $url ) {
					$urls[] = untrailingslashit( $url );
				}
			}

			$order = 60;

			// The endpoints HivePress core puts in this menu itself, so the
			// merge below leaves them to core rather than adding a second row
			// pointing at the same page.
			$core = $this->get_core_wc_items();

			foreach ( $this->get_base_wc_items() as $endpoint => $label ) {

				// Skip the endpoints managed by HivePress core, the sign-out
				// duplicate and the hidden items.
				if ( in_array( $endpoint, $core, true ) || 'customer-logout' === $endpoint || in_array( 'wc:' . $endpoint, $hidden, true ) ) {
					continue;
				}

				// Skip the rows that are not account pages at all. See is_wc_endpoint_item().
				if ( ! $this->is_wc_endpoint_item( $endpoint ) ) {
					continue;
				}

				$url = wc_get_account_endpoint_url( $endpoint );

				// Skip the items that are already in the menu.
				if ( ! $url || in_array( untrailingslashit( $url ), $urls, true ) ) {
					continue;
				}

				$menu['items'][ $endpoint ] = [
					'label'  => $label,
					'url'    => $url,
					'_order' => $order,
				];

				$order += 3;
			}
		}

		// Add the custom items.
		foreach ( $this->get_custom_items() as $name => $item ) {
			if ( 'woocommerce' === $item['menus'] || ! $this->is_item_visible( $item ) ) {
				continue;
			}

			$url = $this->get_custom_item_url( $item );

			if ( ! $url ) {
				continue;
			}

			$menu['items'][ $name ] = [
				'label'  => $item['label'],
				'url'    => $url,
				'_order' => $item['order'],
			];
		}

		return $menu;
	}

	/*
	-------------------------------------------------------------------------
	WooCommerce menu
	-------------------------------------------------------------------------
	*/

	/**
	 * Alters the WooCommerce account menu.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function alter_wc_menu( $items ) {
		if ( $this->suppressed || ! is_array( $items ) ) {
			return $items;
		}

		/*
		 * BOTH hidden lists, and this is the only method that reads the second
		 * one.
		 *
		 * "Hidden Items" hides from both menus; "Also Hidden from the
		 * WooCommerce Menu" hides from this one alone, so an owner can drop a
		 * row from the WooCommerce account area while their members keep it in
		 * the HivePress account menu. Merging the two here rather than testing
		 * them separately is what makes every check below - the endpoint rows,
		 * the merged HivePress items, and the core mirror test - honour the new
		 * list without a second copy of each test.
		 *
		 * The mirror test matters most. Hiding "Orders (WooCommerce)" from this
		 * menu alone has to stop the HivePress "Placed Orders" item being
		 * merged in behind it, or hiding the row would simply swap it for
		 * another row leading to the same page.
		 */
		$hidden = array_merge( $this->get_hidden_keys(), $this->get_wc_hidden_keys() );
		$rows   = [];

		// Add the WooCommerce items.
		$order = 500;

		foreach ( $items as $endpoint => $label ) {
			if ( in_array( 'wc:' . $endpoint, $hidden, true ) ) {
				continue;
			}

			$rows[ $endpoint ] = [
				'label'  => $label,
				'_order' => 'customer-logout' === $endpoint ? 1000 : $order,
			];

			++$order;
		}

		// Get the WooCommerce endpoint URLs for duplicate checks.
		$urls = [];

		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			foreach ( array_keys( $rows ) as $endpoint ) {
				$url = wc_get_account_endpoint_url( $endpoint );

				if ( $url ) {
					$urls[] = untrailingslashit( $url );
				}
			}
		}

		// Add the HivePress items.
		if ( $this->is_wc_integration_enabled() ) {
			foreach ( $this->get_base_hp_items() as $name => $item ) {
				if ( in_array( 'hp:' . $name, $hidden, true ) ) {
					continue;
				}

				// Skip the HivePress items that mirror the WooCommerce Orders
				// and Subscriptions endpoints whenever the native row is
				// present or hidden by the settings. The duplicate URL check
				// alone cannot catch these, since the native URL can differ
				// (for example WooCommerce Subscriptions links a single
				// subscription straight to its details page).
				$core_endpoint = hp\get_array_value( $this->get_core_wc_items(), $name );

				if ( $core_endpoint && ( isset( $rows[ $core_endpoint ] ) || in_array( 'wc:' . $core_endpoint, $hidden, true ) ) ) {
					continue;
				}

				// Skip the sign-out duplicate.
				if ( 'user_logout' === $name && isset( $rows['customer-logout'] ) ) {
					continue;
				}

				$url = $this->get_item_url( $item );

				// Skip the items that are already in the menu.
				if ( ! $url || in_array( untrailingslashit( $url ), $urls, true ) ) {
					continue;
				}

				$rows[ $name ] = [
					'label'  => isset( $item['label'] ) ? wp_strip_all_tags( (string) $item['label'] ) : $name,
					'_order' => isset( $item['_order'] ) ? (int) $item['_order'] : 200,
				];

				$this->wc_urls[ $name ] = $url;
			}
		}

		// Add the custom items.
		foreach ( $this->get_custom_items() as $name => $item ) {
			if ( 'hivepress' === $item['menus'] || ! $this->is_item_visible( $item ) ) {
				continue;
			}

			$url = $this->get_custom_item_url( $item );

			if ( ! $url ) {
				continue;
			}

			$rows[ $name ] = [
				'label'  => $item['label'],
				'_order' => $item['order'],
			];

			$this->wc_urls[ $name ] = $url;
		}

		// Apply the owner's chosen order, then sort and flatten the items.
		return array_map(
			function ( $row ) {
				return $row['label'];
			},
			hp\sort_array( $this->apply_menu_order( $rows ) )
		);
	}

	/**
	 * Alters the WooCommerce endpoint URLs for the injected items.
	 *
	 * @param string $url Endpoint URL.
	 * @param string $endpoint Endpoint name.
	 * @return string
	 */
	public function alter_wc_endpoint_url( $url, $endpoint ) {
		if ( $this->suppressed ) {
			return $url;
		}

		// Check the URLs cached while building the menu.
		if ( isset( $this->wc_urls[ $endpoint ] ) ) {
			return $this->wc_urls[ $endpoint ];
		}

		// Resolve the custom item URLs.
		if ( 0 === strpos( (string) $endpoint, 'amehp_item_' ) ) {
			$items = $this->get_custom_items();

			if ( isset( $items[ $endpoint ] ) ) {
				$custom_url = $this->get_custom_item_url( $items[ $endpoint ] );

				if ( $custom_url ) {
					return $custom_url;
				}
			}
		}

		return $url;
	}

	/*
	-------------------------------------------------------------------------
	Account layout unification
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets the WooCommerce endpoints rendered inside the HivePress layout.
	 *
	 * HivePress core already renders the "orders", "view-order",
	 * "subscriptions" and "view-subscription" endpoints inside its account
	 * template, so those are deliberately not listed here.
	 *
	 * @return array
	 */
	protected function get_unified_endpoints() {
		/**
		 * Filters the WooCommerce account endpoints that are rendered inside
		 * the HivePress account layout when the unification setting is on. Use
		 * "dashboard" for the account page itself.
		 *
		 * Hook name: "amehp_unified_wc_endpoints".
		 *
		 * @param array $endpoints Endpoint names.
		 */
		return apply_filters(
			'amehp_unified_wc_endpoints',
			[
				'dashboard',
				'edit-account',
				'edit-address',
				'payment-methods',
				'add-payment-method',
				'downloads',
			]
		);
	}

	/**
	 * Gets the current WooCommerce endpoint key.
	 *
	 * @return string
	 */
	protected function get_current_endpoint_key() {
		$key = '';

		if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'WC' ) ) {
			$endpoint = WC()->query->get_current_endpoint();

			$key = $endpoint ? $endpoint : 'dashboard';
		}

		return $key;
	}

	/**
	 * Checks if the current page should use the HivePress account layout.
	 *
	 * @return bool
	 */
	protected function is_unified_page() {
		if ( ! $this->is_wc_integration_enabled() || ! is_user_logged_in() ) {
			return false;
		}

		$key = $this->get_current_endpoint_key();

		return $key && in_array( $key, $this->get_unified_endpoints(), true );
	}

	/**
	 * Checks if the current page is a unified endpoint with its own title.
	 *
	 * The "dashboard" endpoint keeps the theme page header, since "My account"
	 * is the right title for it, so only the other unified endpoints hide the
	 * theme header and render their endpoint titles instead.
	 *
	 * @return bool
	 */
	protected function is_titled_unified_page() {
		return $this->is_unified_page() && 'dashboard' !== $this->get_current_endpoint_key();
	}

	/**
	 * Hides the theme page header on the unified account pages.
	 *
	 * The official HivePress themes hide their page header and render the
	 * endpoint title inside the account content for the Orders endpoints
	 * only, so the same is done here for the endpoints that the plugin
	 * renders inside the HivePress account layout.
	 *
	 * @param string $output Header HTML.
	 * @return string
	 */
	public function hide_theme_page_header( $output ) {
		if ( $this->is_titled_unified_page() ) {
			$output = '';
		}

		return $output;
	}

	/**
	 * Renders the endpoint title on the unified account pages.
	 *
	 * Mirrors the way the official HivePress themes render the Orders page
	 * title, so the unified pages match the rest of the account area.
	 */
	public function render_page_title() {
		if ( ! $this->is_titled_unified_page() || ! function_exists( 'WC' ) ) {
			return;
		}

		$title = WC()->query->get_endpoint_title( $this->get_current_endpoint_key() );

		if ( $title ) {
			$output = ( new \HivePress\Blocks\Part(
				[
					'path'    => 'page/page-title',

					'context' => [
						'page_title' => $title,
					],
				]
			) )->render();

			// The template part returns rendered HTML and escapes the title
			// itself, so escaping here would print the markup as text.
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Sets the WooCommerce account page template.
	 *
	 * @param string $path Template filepath.
	 * @param string $name Template name.
	 * @return string
	 */
	public function set_account_template( $path, $name ) {
		if ( 'myaccount/my-account.php' === $name && $this->is_unified_page() ) {
			$path = hivepress()->get_path() . '/templates/woocommerce/myaccount/my-account.php';
		}

		return $path;
	}

	/**
	 * Alters the HivePress account page template.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function alter_account_page( $template ) {
		if ( $this->is_unified_page() ) {

			// Set the page title. The filter removes itself after the first
			// swap, so it is re-added here the same way HivePress core does
			// when rendering the Orders endpoints in its account template.
			if ( 'dashboard' !== $this->get_current_endpoint_key() ) {
				add_filter( 'the_title', 'wc_page_endpoint_title' );
			}

			// Alter the page template. The container and the content are merged
			// separately because a matched block's children are not visited in
			// the same pass, and "page_content" sits inside "page_container".
			$this->merge_blocks(
				$template,
				[
					'page_container' => [
						'type' => 'container',
					],
				]
			);

			$this->merge_blocks(
				$template,
				[
					'page_content' => [
						'blocks' => [
							'amehp_woocommerce_content' => [
								'type'     => 'callback',
								'callback' => 'do_action',
								'params'   => [ 'woocommerce_account_content' ],
								'_order'   => 10,
							],
						],
					],
				]
			);
		}

		return $template;
	}

	/**
	 * Merges blocks into a template.
	 *
	 * Uses the template component, which HivePress prefers over the
	 * "hp\merge_trees" helper, falling back to the helper on cores that
	 * predate it.
	 *
	 * @param array $template Template arguments.
	 * @param array $blocks Blocks to merge.
	 */
	protected function merge_blocks( &$template, $blocks ) {
		if ( hivepress()->template && method_exists( hivepress()->template, 'merge_blocks' ) ) {
			hivepress()->template->merge_blocks( $template, $blocks );
		} else {
			$template = hp\merge_trees( $template, [ 'blocks' => $blocks ] );
		}
	}

	/*
	-------------------------------------------------------------------------
	URLs and visibility
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets a menu item URL.
	 *
	 * @param array $item Menu item.
	 * @return string
	 */
	protected function get_item_url( $item ) {
		$url = '';

		if ( is_array( $item ) ) {
			if ( ! empty( $item['url'] ) ) {
				$url = (string) $item['url'];
			} elseif ( ! empty( $item['route'] ) ) {
				$url = (string) hivepress()->router->get_url( $item['route'] );
			}
		}

		return $url;
	}

	/**
	 * Gets a custom menu item URL.
	 *
	 * @param array $item Custom item arguments.
	 * @return string
	 */
	protected function get_custom_item_url( $item ) {

		// Check the custom URL first so that it overrides the link.
		$url = trim( $item['url'] );

		if ( '' !== $url ) {
			if ( 0 === strpos( $url, '/' ) ) {
				return home_url( $url );
			}

			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				return '';
			}

			// Allow web URLs only.
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

			return in_array( $scheme, [ 'http', 'https' ], true ) ? $url : '';
		}

		$link = $item['link'];

		if ( 0 === strpos( $link, 'page:' ) ) {
			$page_id = absint( substr( $link, strlen( 'page:' ) ) );
			$page    = $page_id ? get_post( $page_id ) : null;

			// Skip the pages that are missing or no longer published.
			if ( ! $page || 'publish' !== $page->post_status ) {
				return '';
			}

			$permalink = get_permalink( $page );

			return $permalink ? $permalink : '';
		}

		if ( 0 === strpos( $link, 'wc:' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
			return (string) wc_get_account_endpoint_url( substr( $link, strlen( 'wc:' ) ) );
		}

		if ( 0 === strpos( $link, 'route:' ) ) {
			return $this->get_route_url( substr( $link, strlen( 'route:' ) ) );
		}

		return '';
	}

	/**
	 * Gets a HivePress route URL, including the viewer-specific routes.
	 *
	 * @param string $route Route name.
	 * @return string
	 */
	protected function get_route_url( $route ) {

		// Resolve the current user profile URL. The route expects the
		// username, the same way HivePress core builds this URL.
		if ( 'user_view_page' === $route ) {
			$user = wp_get_current_user();

			return $user->exists() ? (string) hivepress()->router->get_url( $route, [ 'username' => $user->user_login ] ) : '';
		}

		// Resolve the current vendor profile URL.
		if ( 'vendor_view_page' === $route ) {
			$user_id = get_current_user_id();

			if ( ! $user_id || ! class_exists( '\HivePress\Models\Vendor' ) ) {
				return '';
			}

			$vendor_id = \HivePress\Models\Vendor::query()->filter(
				[
					'status' => 'publish',
					'user'   => $user_id,
				]
			)->get_first_id();

			return $vendor_id ? (string) hivepress()->router->get_url( $route, [ 'vendor_id' => $vendor_id ] ) : '';
		}

		return (string) hivepress()->router->get_url( $route );
	}

	/**
	 * Checks if a custom item is visible to the current user.
	 *
	 * @param array $item Custom item arguments.
	 * @return bool
	 */
	protected function is_item_visible( $item ) {
		if ( empty( $item['roles'] ) ) {
			return true;
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return false;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}

		return (bool) array_intersect( $item['roles'], (array) $user->roles );
	}

	/*
	-------------------------------------------------------------------------
	Icons and counters
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets a cache-busting asset version.
	 *
	 * WordPress appends the version as "?ver={version}", so a constant version
	 * string would keep serving stale files from the browser cache after an
	 * update. The file modification time is appended so that every asset
	 * change gets a fresh URL.
	 *
	 * @param string $file Asset path relative to the plugin directory.
	 * @return string
	 */
	protected function get_asset_version( $file ) {
		$filepath = AMEHP_DIR . '/' . $file;

		$time = file_exists( $filepath ) ? filemtime( $filepath ) : false;

		return $time ? AMEHP_VERSION . '.' . $time : AMEHP_VERSION;
	}

	/**
	 * Enqueues the front-end assets.
	 */
	public function enqueue_frontend_assets() {

		// The appearance CSS goes FIRST: its global icon weight rule ties on
		// specificity with the per-item weight rules in the icon CSS on the
		// HivePress menu selectors, so the per-item rules must come later in
		// the stylesheet for a per-item choice to win.
		$css    = $this->get_appearance_css() . $this->get_icon_css() . $this->get_text_colour_css();
		$badges = [];

		// The counters are only needed where the WooCommerce menu renders.
		if ( is_user_logged_in() && function_exists( 'is_account_page' ) && is_account_page() ) {
			$badges = $this->get_badge_counts();
		}

		if ( ! $css && ! $badges ) {
			return;
		}

		// Enqueue the stylesheet.
		wp_enqueue_style(
			'amehp-frontend',
			plugins_url( 'assets/css/frontend.css', AMEHP_FILE ),
			[],
			$this->get_asset_version( 'assets/css/frontend.css' )
		);

		if ( $css ) {
			wp_add_inline_style( 'amehp-frontend', $css );
		}

		// Enqueue the counters script.
		if ( $badges ) {
			wp_enqueue_script(
				'amehp-frontend',
				plugins_url( 'assets/js/frontend.js', AMEHP_FILE ),
				[],
				$this->get_asset_version( 'assets/js/frontend.js' ),
				true
			);

			/**
			 * Filters the CSS classes of the counters mirrored into the
			 * WooCommerce account menu, so a theme styling its badges by a
			 * class convention can pick these up with its existing rules.
			 * The "amehp-badge" class is always kept, since the plugin's own
			 * styling and duplicate checks rely on it.
			 *
			 * Hook name: "amehp_wc_badge_classes".
			 *
			 * @param array $classes Badge CSS classes.
			 */
			$badge_classes = (array) apply_filters( 'amehp_wc_badge_classes', [ 'hp-badge' ] );

			wp_localize_script(
				'amehp-frontend',
				'amehpFrontendData',
				[
					'badges'       => $badges,
					'badgeClasses' => implode( ' ', array_unique( array_merge( [ 'amehp-badge' ], array_filter( array_map( 'sanitize_html_class', $badge_classes ) ) ) ) ),
				]
			);
		}
	}

	/**
	 * Enqueues the Font Awesome webfont, in wp-admin only.
	 *
	 * The webfont exists for one job now: the icon picker's own preview markup, which is `<i>`
	 * elements the shared library's admin script swaps for inline SVG. The font is what those
	 * elements fall back to for the instant before the swap, and on any screen the script has not
	 * reached. Nothing on the front end needs it - menu icons there are drawn from path data, see
	 * get_icon_mask() - and the library enforces the admin-only rule itself rather than trusting
	 * callers to.
	 *
	 * The plugin's own assets/vendor/fontawesome/ copy is gone; one copy now lives in
	 * includes/fafh/ and every one of this author's plugins shares it, so however many are active
	 * only one library ever loads. It is still BUNDLED and must never be pointed at a CDN: that is
	 * the exact case the offloaded-assets rule exists to catch, Plugin Check reports
	 * EnqueuedResourceOffloading for it, and cache partitioning means a CDN copy is a cold download
	 * for every site anyway.
	 *
	 * Public because the persistent menu component calls it too.
	 */
	public function enqueue_fontawesome() {
		if ( class_exists( 'FAFH' ) ) {
			\FAFH::enqueue_admin();
		}
	}

	/**
	 * Whether the settings tab currently being rendered is this plugin's own.
	 *
	 * Answered from the fields HivePress has actually registered for this
	 * request, never from $_GET['tab']. The address cannot be trusted:
	 * get_settings_tab() falls back to the FIRST tab whenever "tab" is absent
	 * (reference/hivepress/includes/components/class-admin.php:607-622), and
	 * the bare admin.php?page=hp_settings link in the HivePress menu is
	 * exactly that case, so reading the address misses this plugin's own tab
	 * on any site where it sorts first.
	 *
	 * register_settings() builds the sections and fields for one tab only and
	 * calls add_settings_field() with the prefixed option name
	 * (class-admin.php:287-325), so $wp_settings_fields['hp_settings'] holds
	 * hp_amehp_* keys on this tab and on no other. It is the server-side twin
	 * of the [name^="hp_amehp_"] gate the scripts use - PHP decides whether a
	 * file loads, the script decides whether it acts - and it is populated in
	 * time because HivePress registers on admin_init while this runs on
	 * admin_enqueue_scripts, which wp-admin fires later.
	 *
	 * @return bool
	 */
	protected function is_settings_tab() {
		if ( ! isset( $GLOBALS['wp_settings_fields']['hp_settings'] ) || ! is_array( $GLOBALS['wp_settings_fields']['hp_settings'] ) ) {
			return false;
		}

		foreach ( $GLOBALS['wp_settings_fields']['hp_settings'] as $amehp_section ) {
			foreach ( array_keys( (array) $amehp_section ) as $amehp_field ) {
				if ( 0 === strpos( (string) $amehp_field, 'hp_amehp_' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Enqueues the settings screen assets.
	 *
	 * @param string $hook Page hook suffix.
	 */
	public function enqueue_backend_assets( $hook ) {
		/*
		 * The tab is decided by the REGISTERED FIELDS, never by $_GET['tab'].
		 *
		 * This used to test `'account_menu' !== $_GET['tab']`, which looks
		 * right and is wrong in one real case: the bare
		 * admin.php?page=hp_settings link in the HivePress menu carries no
		 * tab at all, and get_settings_tab() then falls back to the FIRST
		 * tab. On a site where Account Menu sorts first, this plugin's own
		 * fields are on screen while the test says they are not - so no nav,
		 * no live preview, no colour pickers, and nothing in the console to
		 * say why. A sibling hit exactly this. See is_settings_tab() for how
		 * the question is answered properly.
		 */
		if ( 'toplevel_page_hp_settings' !== $hook || ! $this->is_settings_tab() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		// Icon previews on the settings screen use the full library, so the
		// Font Awesome 6/7 and brand choices preview the same as they render.
		$this->enqueue_fontawesome();

		wp_enqueue_style(
			'amehp-backend',
			plugins_url( 'assets/css/backend.css', AMEHP_FILE ),
			[],
			$this->get_asset_version( 'assets/css/backend.css' )
		);

		wp_enqueue_script(
			'amehp-backend',
			plugins_url( 'assets/js/backend.js', AMEHP_FILE ),
			[ 'jquery', 'wp-color-picker', 'hivepress-core' ],
			$this->get_asset_version( 'assets/js/backend.js' ),
			true
		);

		/*
		 * The preview's pure logic - key construction, the order merge, the menu
		 * filtering and the small validators - lives in its own file so that
		 * tests/js/ can load it in Node and walk it across its cases.
		 * admin-preview.js is an IIFE, so nothing inside it is reachable from
		 * outside a browser, which is how the 3.3.1 drag bug got past both the
		 * PHP harness and a browser check: the harness cannot see the file, and
		 * a browser confirms one page in one state, not a function across its
		 * cases.
		 *
		 * It has no dependencies of its own, because it touches nothing but its
		 * own arguments.
		 */
		wp_enqueue_script(
			'amehp-preview-logic',
			plugins_url( 'assets/js/preview-logic.js', AMEHP_FILE ),
			[],
			$this->get_asset_version( 'assets/js/preview-logic.js' ),
			true
		);

		// amehp-backend is a dependency of the preview script only to fix the
		// load order, so the localized data below is on the page first, and
		// amehp-preview-logic for the same reason: the preview script reads that
		// global as it loads.
		// jquery-ui-sortable ships with wp-admin and is what makes the preview
		// itself the drag-to-reorder control.
		wp_enqueue_script(
			'amehp-preview',
			plugins_url( 'assets/js/admin-preview.js', AMEHP_FILE ),
			[ 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'amehp-backend', 'amehp-preview-logic' ],
			$this->get_asset_version( 'assets/js/admin-preview.js' ),
			true
		);

		/*
		 * The theme's Heading Font, and the request for it - which are two
		 * different things, deliberately.
		 *
		 * The NAME is always sent to the preview script, so the panel can set
		 * the right font-family and let the browser fall back if that face is
		 * not loaded. The FONT ITSELF comes from fonts.googleapis.com, which
		 * is a third-party request that nothing else in wp-admin makes:
		 * measured on 2026-08-30, this stylesheet was the only Google Fonts
		 * request on the whole settings screen, so enqueueing it
		 * unconditionally sent every admin's IP address to Google on every
		 * view of this tab, whether or not the owner uses the feature.
		 *
		 * So it is enqueued only when the Heading Font option is actually
		 * switched on. Ticking the box mid-session does not reload the page,
		 * so the URL is handed to the script as well and it injects the
		 * stylesheet at that moment - otherwise the preview would quietly
		 * show a system font and tell the owner something untrue about their
		 * own menu.
		 */
		$family         = $this->get_heading_font_family();
		$preview_labels = $this->get_preview_labels();

		if ( $family && get_option( 'hp_amehp_sidebar_heading_font' ) ) {
			wp_enqueue_style(
				'amehp-preview-font',
				$this->get_heading_font_url( $family ),
				[],
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs are versionless by design, matching hivetheme's own enqueue.
			);
		}

		wp_localize_script(
			'amehp-backend',
			'amehpBackendData',
			[
				// The family NAME, always: the preview needs it to set the
				// right font-family whether or not the face has been loaded.
				'headingFont'      => $family,

				// The URL, for the script to inject if the owner switches the
				// option on without reloading. Built here rather than in the
				// script so the address exists in one place only.
				//
				// THIS STRING IS PRESENT EVEN WHEN THE HEADING FONT OPTION IS
				// OFF, AND THAT IS DELIBERATE. Reading "fonts.googleapis.com"
				// in the page source of a screen that is supposed to make no
				// Google request looks exactly like a privacy bug, and the
				// obvious "fix" is to drop it. Do not: it is an inert string in
				// a JS data blob, not a request. Nothing fetches it until the
				// owner ticks the box, and it has to be here in the OFF case
				// precisely so that ticking can inject the stylesheet and keep
				// the preview honest. Verified 2026-08-30 on the rendered page:
				// option off, zero <link> elements and zero requests to Google;
				// option on, exactly one. Chris was shown this trade and chose
				// to keep the string (2026-08-30). The alternative is rebuilding
				// the address in JavaScript, which splits it across two files
				// and lets them drift when the theme's font changes.
				'headingFontUrl'   => $family ? $this->get_heading_font_url( $family ) : '',

				// The real front-end order, so the preview can list the items the
				// way the site does.
				//
				// The owner's own arrangement is deliberately NOT sent here. It
				// used to be, as 'menuOrder', and nothing ever read it: the
				// preview takes the arrangement from the hidden form field
				// instead (admin-preview.js, storedOrder()), which is the only
				// correct source, because that value changes as the owner drags
				// while a localised copy is frozen at page build. Removed in
				// 3.3.11. Do not add it back without a reader that needs the
				// saved value specifically rather than the live one.
				'itemOrders'       => $this->get_preview_orders(),

				/*
				 * The label each item is really rendered with, so the panel can
				 * name items the way the site names them.
				 *
				 * The catalogue the panel reads its items from is the Menu Item
				 * Styling dropdown, and that dropdown suffixes its WooCommerce
				 * entries with "(WooCommerce)" to keep two similar names apart -
				 * correct in a dropdown, and a lie in a preview, which said
				 * "Orders (WooCommerce)" where the site says "Placed Orders".
				 * Two maps because the two menus genuinely disagree about that
				 * row; see get_preview_labels() for where each label comes from.
				 */
				'itemLabels'       => $preview_labels['hp'],
				'wcItemLabels'     => $preview_labels['wc'],

				// The WooCommerce-named items that are in the HivePress menu
				// whether or not the integration is on, so the HivePress panel
				// shows them and the owner can drag them. See
				// get_hp_menu_wc_keys().
				'hpMenuWcKeys'     => $this->get_hp_menu_wc_keys(),

				// The placeholder pages, so the script can fold that section's
				// fields into one group per page under the page's own name.
				'placeholderPages' => hivepress()->amehp_persistent_menu ? hivepress()->amehp_persistent_menu->get_placeholder_pages() : [],

				/*
				 * The Order numbers stored by a version before 3.2.0, by row
				 * position. The box itself is gone from the screen, so the
				 * script cannot read these from the form, but the front end
				 * still honours them until the item is dragged - and a
				 * preview that ignored them would show a different order from
				 * the one the site renders, which is the whole failure this
				 * panel exists to prevent.
				 *
				 * Keyed by the item's own key, NOT by position.
				 *
				 * This was a plain list that the script read by DOM row
				 * number - and get_custom_items() skips a row with no label
				 * while the DOM does not, so a single unlabelled row above a
				 * labelled one handed the wrong legacy Order number to every
				 * item below it. Keying by the thing both sides already agree
				 * on removes the alignment question instead of answering it.
				 */
				'customOrders'     => array_map(
					function ( $item ) {
						return $item['order'];
					},
					$this->get_custom_items()
				),

				'labels'           => [
					'newItem'      => esc_html__( 'New item', 'account-menu-enhancer-for-hivepress' ),
					'collapse'     => esc_html__( 'Collapse', 'account-menu-enhancer-for-hivepress' ),
					'expand'       => esc_html__( 'Expand', 'account-menu-enhancer-for-hivepress' ),
					'drag'         => esc_html__( 'Drag to reorder', 'account-menu-enhancer-for-hivepress' ),
					'sampleItem'   => esc_html__( 'Dashboard', 'account-menu-enhancer-for-hivepress' ),
					'signOut'      => esc_html__( 'Sign Out', 'account-menu-enhancer-for-hivepress' ),
					'moveUp'       => esc_html__( 'Move up', 'account-menu-enhancer-for-hivepress' ),
					'moveDown'     => esc_html__( 'Move down', 'account-menu-enhancer-for-hivepress' ),
					// The colon is part of the wording: it reads as a lead-in to
					// the links that follow it, not as a heading over them.
					'jumpTo'       => esc_html__( 'Jump to a section:', 'account-menu-enhancer-for-hivepress' ),
					'combined'     => esc_html__( 'Account menu', 'account-menu-enhancer-for-hivepress' ),
					'hpMenu'       => esc_html__( 'HivePress account menu', 'account-menu-enhancer-for-hivepress' ),
					'wcMenu'       => esc_html__( 'WooCommerce account menu', 'account-menu-enhancer-for-hivepress' ),
					'save'         => esc_html__( 'Save Changes', 'account-menu-enhancer-for-hivepress' ),
					'backToTop'    => esc_html__( 'Back to top', 'account-menu-enhancer-for-hivepress' ),

					'resetConfirm' => esc_html__( 'Put every menu item back in its default order? Your arrangement will be discarded. Nothing is saved until you press Save Changes.', 'account-menu-enhancer-for-hivepress' ),
				],
			]
		);
	}

	/**
	 * Gets the front-end order of every account menu item, keyed by the
	 * settings screen's own item keys.
	 *
	 * Built by running the real merge path - the same alter_hp_menu() the
	 * front end runs, over the same base menu - rather than by describing it
	 * again in the preview script. That is the point: the preview listed
	 * items in the dropdown's alphabetical order until 3.2.0, which is not an
	 * order the site has ever rendered, and any second description of the
	 * ordering would drift from the first the next time the merge changed.
	 *
	 * The menu keys are mapped back to settings keys the way
	 * get_menu_item_options() names them, so the script can match an entry to
	 * its dropdown option, its styling row and its stored position.
	 *
	 * @return array Settings key mapped to its numeric order.
	 */
	protected function get_preview_orders() {
		$orders = [];

		// The endpoints that are WooCommerce's rather than HivePress's, so a
		// merged row is keyed the way the settings screen lists it.
		$endpoints = array_merge( $this->get_base_wc_items(), $this->get_registered_wc_endpoints() );

		$menu = $this->alter_hp_menu( [ 'items' => $this->get_base_hp_items() ] );

		if ( isset( $menu['items'] ) && is_array( $menu['items'] ) ) {
			foreach ( $menu['items'] as $name => $item ) {
				$key = $this->get_settings_key( (string) $name, $endpoints );

				// The first mention wins, so a HivePress item that mirrors a
				// WooCommerce endpoint cannot overwrite the endpoint's place.
				if ( ! isset( $orders[ $key ] ) ) {
					$orders[ $key ] = is_array( $item ) && isset( $item['_order'] ) ? (int) $item['_order'] : 100;
				}
			}
		}

		/*
		 * Then the items only the front end ever sees.
		 *
		 * The account menu built here in wp-admin is a fraction of the real
		 * one, because an extension may register its item inside
		 * `if ( ! is_admin() )` and many do, so the loop above knows the
		 * position of only a handful. The positions recorded by
		 * record_seen_items() on the front end fill in the rest, which is what
		 * makes the preview list the whole menu in the site's own order rather
		 * than alphabetically.
		 */
		foreach ( $this->get_seen_items() as $name => $item ) {
			if ( null === $item['order'] ) {
				continue;
			}

			$key = $this->get_settings_key( (string) $name, $endpoints );

			if ( ! isset( $orders[ $key ] ) ) {
				$orders[ $key ] = $item['order'];
			}
		}

		return $orders;
	}

	/**
	 * Gets the label each account menu item is really rendered with, keyed by
	 * the settings screen's own item keys.
	 *
	 * WHY THE PREVIEW CANNOT USE THE CATALOGUE'S LABELS. The panel reads its
	 * items from the Menu Item Styling dropdown, whose labels come from
	 * get_menu_item_options() - and that list decorates every WooCommerce entry
	 * with a "(WooCommerce)" suffix so an owner scanning a dropdown of similar
	 * names can tell two destinations apart. That is right for a dropdown and
	 * wrong for the preview, whose entire purpose is to show what the site
	 * renders: the panel was drawing "Orders (WooCommerce)" for a row the site
	 * renders as "Placed Orders". Reported from a live site on 2026-08-30.
	 *
	 * STRIPPING THE SUFFIX WOULD NOT HAVE FIXED IT. The real label is not the
	 * catalogue's label minus a suffix. HivePress core takes it from
	 * wc_get_account_menu_items()['orders'], which is "Orders", and HivePress
	 * Marketplace then relabels the same item to "Placed Orders" for a member
	 * who has both their own orders and vendor orders
	 * (hivepress-marketplace/includes/components/class-marketplace.php:2637).
	 * A label rebuilt on the settings screen can only ever be a guess at that.
	 *
	 * SO THE SOURCE IS THE MENU AS IT WAS ACTUALLY RENDERED. get_seen_items()
	 * records the label of every item the account menu really drew, on the
	 * front end, where every extension has had its say and the member is a
	 * member rather than an administrator - which is exactly the honest answer,
	 * and the only one that carries the Marketplace relabel, since the menu
	 * built here in wp-admin describes an administrator who may have placed no
	 * orders at all. It therefore wins over the admin-built menu, which is kept
	 * as the fallback for a site whose members have not yet loaded an account
	 * page.
	 *
	 * TWO MAPS, BECAUSE THE TWO MENUS REALLY DO DISAGREE. The same destination
	 * is "Placed Orders" in the HivePress menu and "Orders" in the WooCommerce
	 * one, and the preview draws a panel for each. Handing one label to both
	 * panels would fix the reported row and break its neighbour.
	 *
	 * @return array Two maps of settings key to label: "hp" for the HivePress
	 *               account menu, "wc" for the WooCommerce one.
	 */
	protected function get_preview_labels() {
		$endpoints = $this->get_registered_wc_endpoints();
		$wc_items  = $this->get_base_wc_items();
		$catalogue = array_merge( $wc_items, $endpoints );

		$labels    = [];
		$wc_labels = [];

		/*
		 * The WooCommerce endpoints first, weakest to strongest: an endpoint
		 * that is registered but not in the menu has no rendered label at all,
		 * so its name is turned into one, and the menu's own label replaces it
		 * wherever there is one. These are also the labels alter_hp_menu()
		 * merges into the HivePress menu, so they seed both maps.
		 */
		foreach ( [ $endpoints, $wc_items ] as $source ) {
			foreach ( $source as $endpoint => $label ) {
				$label = wp_strip_all_tags( (string) $label );

				if ( ! $label ) {
					continue;
				}

				$labels[ 'wc:' . $endpoint ]    = $label;
				$wc_labels[ 'wc:' . $endpoint ] = $label;
			}
		}

		// Then the HivePress menu as it can be built here, which is a fraction
		// of the real one but is all a fresh site has.
		foreach ( $this->get_base_hp_items() as $name => $item ) {
			$label = is_array( $item ) && isset( $item['label'] ) ? wp_strip_all_tags( (string) $item['label'] ) : '';

			if ( $label ) {
				$labels[ $this->get_settings_key( (string) $name, $catalogue ) ] = $label;
			}
		}

		// Then the menu as the front end really drew it, which is the answer
		// wherever there is one.
		foreach ( $this->get_seen_items() as $name => $item ) {
			$labels[ $this->get_settings_key( (string) $name, $catalogue ) ] = $item['label'];
		}

		return [
			'hp' => $labels,
			'wc' => $wc_labels,
		];
	}

	/**
	 * Gets the WooCommerce-named items that belong to the HivePress account menu
	 * in their own right, as settings keys.
	 *
	 * THE PREVIEW NEEDS THIS TO AGREE WITH THE FRONT END. Its two panels split
	 * the catalogue by key prefix when the menus are not combined: a `wc:` key
	 * is a WooCommerce row and a `hp:` key a HivePress one. That is right for
	 * every endpoint this plugin merges in itself, and wrong for the lists
	 * HivePress core adds - "Placed Orders" is in the HivePress menu whether or
	 * not the integration is on, and is listed under a `wc:` key because that is
	 * the same destination as the WooCommerce row. So the panel dropped it, the
	 * owner could not drag it, and the item it could not place rendered at the
	 * bottom of the real menu. Reported from a live site on 2026-08-30.
	 *
	 * Worked out structurally rather than named: an item is the HivePress menu's
	 * own if that menu carries it while this plugin is NOT merging anything into
	 * it. get_core_wc_items() is the list of names core uses, and the endpoints
	 * beside them are exactly the ones alter_hp_menu() refuses to merge, so the
	 * two answers cannot drift apart.
	 *
	 * The menu is asked twice because neither source is complete on its own: the
	 * menu built here in wp-admin describes the administrator (who may have
	 * placed no orders and would therefore not see the item at all), while the
	 * records kept by record_seen_items() describe every member who has loaded
	 * an account page.
	 *
	 * @return array Settings keys.
	 */
	protected function get_hp_menu_wc_keys() {
		$core  = $this->get_core_wc_items();
		$names = array_merge( array_keys( $this->get_base_hp_items() ), array_keys( $this->get_seen_items() ) );
		$keys  = [];

		foreach ( $names as $name ) {
			if ( ! isset( $core[ $name ] ) ) {
				continue;
			}

			$keys[ 'wc:' . $core[ $name ] ] = true;
		}

		return array_keys( $keys );
	}

	/**
	 * Maps a menu item's own name to the key the settings screen knows it by.
	 *
	 * The inverse of get_item_selectors(), and it has to agree with
	 * get_menu_item_options(): the two names HivePress core gives the
	 * WooCommerce order lists are listed there under their WooCommerce names,
	 * so they are mapped to those here too.
	 *
	 * @param string $name Menu item name.
	 * @param array  $endpoints WooCommerce endpoints, keyed by slug.
	 * @return string
	 */
	protected function get_settings_key( $name, $endpoints ) {
		if ( 0 === strpos( $name, 'amehp_item_' ) ) {
			return $name;
		}

		$core = $this->get_core_wc_items();

		if ( isset( $core[ $name ] ) ) {
			return 'wc:' . $core[ $name ];
		}

		return isset( $endpoints[ $name ] ) ? 'wc:' . $name : 'hp:' . $name;
	}

	/*
	-------------------------------------------------------------------------
	Account page redirect
	-------------------------------------------------------------------------
	*/

	/**
	 * Keeps the account page's redirect pointing at this site.
	 *
	 * WHAT GOES WRONG WITHOUT THIS. The account page has no content of its
	 * own: core redirects it to the first item of the account menu
	 * (hivepress/includes/controllers/class-user.php:788-803, verified in
	 * 1.7.31, the version this site runs) with `wp_safe_redirect`. This plugin
	 * lets an owner add a custom item with an address anywhere, and putting
	 * "our blog" at the top of the menu is an ordinary thing to do. But
	 * `wp_safe_redirect` refuses a host it does not allow and falls back to
	 * `admin_url()`, so the visitor arriving at /account/ lands in wp-admin -
	 * a subscriber, in the dashboard, with no idea why. Measured by review on
	 * 2026-08-30.
	 *
	 * WHY THIS SEAM. The redirect target is the only thing that is wrong, so
	 * the redirect target is the only thing changed: the item keeps its place
	 * in the menu and still links off-site, exactly as the owner set it up.
	 * Filtering the menu instead would have fixed the redirect by breaking the
	 * feature. Core builds this target inside a route redirect callback, and
	 * wrapping that callback is a pattern this plugin already uses for the
	 * placeholder pages (Amehp_Persistent_Menu::alter_routes), which wraps
	 * different routes and cannot collide with this one.
	 *
	 * @param array $routes Route arguments.
	 * @return array
	 */
	public function alter_account_route( $routes ) {
		if ( ! isset( $routes['user_account_page']['redirect'] ) ) {
			return $routes;
		}

		$callbacks = $routes['user_account_page']['redirect'];

		// Normalise the callbacks the same way core does.
		if ( count( $callbacks ) === 2 && is_object( hp\get_first_array_value( $callbacks ) ) ) {
			$callbacks = [
				[
					'callback' => $callbacks,
					'_order'   => 5,
				],
			];
		}

		$callbacks = array_filter(
			array_map(
				function ( $args ) {
					return hp\get_array_value( $args, 'callback' );
				},
				hp\sort_array( $callbacks )
			)
		);

		$routes['user_account_page']['redirect'] = [
			[
				'callback' => function () use ( $callbacks ) {
					return $this->filter_account_redirect( $callbacks );
				},

				'_order'   => 5,
			],
		];

		return $routes;
	}

	/**
	 * Runs the account page's redirect callbacks, keeping the target on-site.
	 *
	 * Only an off-site target is changed, and only to the first menu item that
	 * is genuinely on this site. With no such item the site's front page is
	 * the fallback: it is somewhere the visitor can use, which wp-admin is
	 * not.
	 *
	 * @param array $callbacks Original redirect callbacks.
	 * @return mixed
	 */
	protected function filter_account_redirect( $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$redirect = call_user_func( $callback );

			// Falsy results mean no redirect, the same as in core.
			if ( ! $redirect ) {
				continue;
			}

			// A boolean is a feature gate rather than a destination.
			if ( is_bool( $redirect ) || $this->is_internal_url( (string) $redirect ) ) {
				return $redirect;
			}

			$internal = $this->get_first_internal_item_url();

			return $internal ? $internal : home_url( '/' );
		}

		return false;
	}

	/**
	 * Gets the URL of the first account menu item that is on this site.
	 *
	 * The menu is built exactly as core builds it for the redirect, so the
	 * order is the order the owner arranged and the items are the ones this
	 * visitor actually has.
	 *
	 * @return string
	 */
	protected function get_first_internal_item_url() {
		if ( ! class_exists( '\HivePress\Menus\User_Account' ) ) {
			return '';
		}

		try {
			$items = ( new \HivePress\Menus\User_Account() )->get_items();
		} catch ( \Throwable $throwable ) {

			// A third-party item can resolve its label through a callback that
			// expects a context this request does not have. The front page is
			// a safe answer; guessing is not.
			return '';
		}

		foreach ( (array) $items as $item ) {
			$url = hp\get_array_value( (array) $item, 'url' );

			if ( $url && $this->is_internal_url( (string) $url ) ) {
				return (string) $url;
			}
		}

		return '';
	}

	/**
	 * Checks whether a URL points at somewhere this site may redirect to.
	 *
	 * Asked of WordPress rather than answered here, because
	 * `wp_validate_redirect()` is what `wp_safe_redirect()` itself consults:
	 * it allows the site's own host plus anything on the `allowed_redirect_hosts`
	 * filter, so a multisite, or a site whose admin lives on another domain,
	 * decides this for itself instead of being caught by a hand-rolled string
	 * test. A URL with no host at all is a path on this site.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	protected function is_internal_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			return true;
		}

		return (bool) wp_validate_redirect( $url, '' );
	}

	/**
	 * Gets the theme's Heading Font family name.
	 *
	 * Read the way hivetheme's own style builder reads it
	 * (themes/listinghive/vendor/hivepress/hivetheme/includes/components
	 * /class-customizer.php:85-99): the stored mod can carry a ":weights"
	 * suffix, so only the part before the colon is the family. Gated to safe
	 * characters because the value ends up inside a CSS declaration and a URL.
	 *
	 * @return string Family name, or an empty string if there is none usable.
	 */
	protected function get_heading_font_family() {
		$font   = (string) get_theme_mod( 'heading_font' );
		$family = trim( (string) hp\get_first_array_value( explode( ':', $font ) ) );

		return $family && preg_match( '/^[A-Za-z0-9 \-]+$/', $family ) ? $family : '';
	}

	/**
	 * Gets the Google Fonts URL for a family.
	 *
	 * ONE PLACE, on purpose. The settings screen needs this address twice -
	 * once to enqueue the stylesheet when the option is already on, and once
	 * to hand to the preview script for the case where the owner switches the
	 * option on without reloading. Built in the script as well, the two
	 * spellings would drift the first time the theme's font changed.
	 *
	 * The classic Google Fonts API is the one hivetheme itself uses
	 * (get_fonts_url in the customizer component above), so a font that works
	 * on the front end works here.
	 *
	 * @param string $family Font family name.
	 * @return string
	 */
	protected function get_heading_font_url( $family ) {
		return 'https://fonts.googleapis.com/css?family=' . rawurlencode( $family ) . '&display=swap';
	}

	/**
	 * Adds the live preview panel to the settings tab.
	 *
	 * Runs at admin_init priority 20, after HivePress has registered the
	 * tab's own sections at 10, and mirrors the Notifications extension's
	 * panel mechanics: the section is registered last and then moved to the
	 * front of the section list, because sections render in registration
	 * order and the preview belongs above the controls on narrow screens
	 * (on wide screens the stylesheet lifts it into its own column).
	 */
	public function register_preview_section() {
		global $pagenow;

		// HivePress registers its settings on options.php as well, so that a
		// save has the field list to validate against. Nothing is rendered on
		// that request, so a panel registered there is pure waste.
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'hp_settings' !== sanitize_key( (string) hp\get_array_value( $_GET, 'page' ) ) ) {
			return;
		}

		/*
		 * Whether the tab being registered is ours, asked of the registered
		 * fields rather than of $_GET['tab'] - see is_settings_tab() for why
		 * the address cannot answer it.
		 *
		 * This used to name one section and one field outright
		 * (['display']['hp_amehp_icons']), which answered the same question
		 * but broke the moment either was renamed - and the section list on
		 * this tab has already been rebuilt twice. The helper asks about the
		 * prefix instead, so it survives that.
		 */
		if ( ! $this->is_settings_tab() ) {
			return;
		}

		add_settings_section( 'amehp_preview', '', [ $this, 'render_preview_section' ], 'hp_settings' );

		if ( ! isset( $GLOBALS['wp_settings_sections']['hp_settings']['amehp_preview'] ) ) {
			return;
		}

		// Move the panel to the front of the list. A plain reorder of a data
		// array WordPress reads later in the same request: no callbacks run
		// and no section changes, so there is nothing here to fire twice.
		$sections = $GLOBALS['wp_settings_sections']['hp_settings'];
		$preview  = $sections['amehp_preview'];

		unset( $sections['amehp_preview'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering our own entry in the settings section list, which is the documented way sections are held and has no setter.
		$GLOBALS['wp_settings_sections']['hp_settings'] = array_merge( [ 'amehp_preview' => $preview ], $sections );
	}

	/**
	 * Renders the live preview panel.
	 *
	 * Only the shell is rendered here; admin-preview.js fills the menu in
	 * from the settings on screen and repaints it on every change.
	 *
	 * The panel is also the reorder control, which is why it is no longer
	 * hidden from screen readers as it was in 3.1.1: it now holds real
	 * buttons, and aria-hidden over a focusable control leaves a reader
	 * tabbing onto something their software has been told is not there. The
	 * sample rows are anchors with no href, so they are not links to a
	 * screen reader and there is nothing to follow; the icons are marked
	 * decorative individually instead.
	 */
	public function render_preview_section() {
		echo '<div class="amehp-preview"><div class="amehp-preview__inner">';

		echo '<h2 class="amehp-preview__title">' . esc_html__( 'Live preview', 'account-menu-enhancer-for-hivepress' ) . '</h2>';

		/*
		 * Two panels, and the script shows one or both.
		 *
		 * With the WooCommerce integration on, the site renders ONE list of
		 * items in every account menu, so one panel is the truth. With it off
		 * the two account areas stay separate and show different items, and a
		 * single combined preview was then showing owners a menu their site
		 * does not have. Both panels are rendered here and the script hides
		 * the one that does not apply, so the switch is live on the checkbox
		 * and needs no save; the WooCommerce panel is not rendered at all
		 * where WooCommerce is inactive, since there is no second menu.
		 */
		$this->render_preview_panel( 'hivepress', esc_html__( 'Account menu', 'account-menu-enhancer-for-hivepress' ) );

		if ( hp\is_plugin_active( 'woocommerce' ) ) {
			$this->render_preview_panel( 'woocommerce', esc_html__( 'WooCommerce account menu', 'account-menu-enhancer-for-hivepress' ) );
		}

		echo '<p class="description amehp-preview__description">' . esc_html__( 'Your account menu in the sidebar style, following every change as you make it. Drag an item by its handle to reorder the menu, or use the arrow buttons. Nothing is stored until you press Save Changes.', 'account-menu-enhancer-for-hivepress' ) . '</p>';

		// Shown by the script only once an order has actually been arranged,
		// so nobody is offered a reset for something they have not done.
		echo '<button type="button" class="button amehp-preview__reset" hidden>' . esc_html__( 'Reset to default order', 'account-menu-enhancer-for-hivepress' ) . '</button>';

		echo '</div></div>';
	}

	/**
	 * Renders one preview panel.
	 *
	 * The header is a button rather than a heading with a control in it, so
	 * the whole bar is one target and screen readers announce the collapse
	 * state on the thing that carries it. It is the same affordance as the
	 * repeater cards further down the page - same chevron, same direction,
	 * same shared stylesheet rules - because an owner should not have to
	 * learn two ways of folding something away on one screen.
	 *
	 * @param string $menu Which menu the panel previews.
	 * @param string $title Panel title.
	 */
	protected function render_preview_panel( $menu, $title ) {
		$id = 'amehp-preview-panel-' . $menu;

		echo '<div class="amehp-preview__panel" data-menu="' . esc_attr( $menu ) . '">';

		echo '<button type="button" class="amehp-preview__header amehp-card-toggle-bar" aria-expanded="true" aria-controls="' . esc_attr( $id ) . '">';
		echo '<span class="amehp-card-toggle" aria-hidden="true"><span class="dashicons dashicons-arrow-up-alt2"></span></span>';
		echo '<span class="amehp-preview__panel-title">' . esc_html( $title ) . '</span>';
		echo '</button>';

		echo '<div class="amehp-preview__body" id="' . esc_attr( $id ) . '">';
		echo '<div class="amehp-preview__stage"><ul class="amehp-preview__menu" aria-label="' . esc_attr( $title ) . '"></ul></div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Sanitizes a hex colour value.
	 *
	 * Public alongside the two icon helpers above, so the placeholder pages
	 * accept exactly the colours the menu icons accept.
	 *
	 * @param string $colour Colour value.
	 * @return string
	 */
	public function sanitize_colour( $colour ) {
		return is_string( $colour ) && preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $colour ) ? $colour : '';
	}

	/**
	 * Gets the CSS selectors for a menu item key.
	 *
	 * @param string $key Item key.
	 * @return array
	 */
	protected function get_item_selectors( $key ) {
		$names     = $this->get_key_menu_names( $key );
		$selectors = [];

		foreach ( $names as $name ) {

			// Match the class generation of each menu.
			$hp_class = hp\sanitize_slug( $name );
			$wc_class = preg_replace( '/[^A-Za-z0-9_-]/', '', $name );

			if ( $hp_class ) {
				$selectors[] = '.hp-menu--user-account .hp-menu__item--' . $hp_class . ' > a';
			}

			if ( $wc_class ) {
				$selectors[] = '.woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--' . $wc_class . ' > a';
			}
		}

		return array_unique( $selectors );
	}

	/**
	 * Builds an icon weight rule for a set of selectors.
	 *
	 * This is the weight for icons this plugin does NOT draw. The account menus carry icons from the
	 * theme and from other extensions, and the global weight setting has always thickened those too;
	 * sizing and spacing do the same, for the same reason - a setting that visibly skipped half the
	 * menu would read as broken. Those icons are still webfont glyphs, so a text stroke is still the
	 * technique for them.
	 *
	 * The icons this plugin draws are masks now and carry their weight inside the image instead, see
	 * get_icon_mask(). The stroke below reaches them as well and does nothing, harmlessly: their
	 * `content` is an empty string, and a stroke on no text paints nothing.
	 *
	 * @param array  $selectors Item selectors.
	 * @param string $weight Weight choice.
	 * @return string
	 */
	protected function get_weight_rule( $selectors, $weight ) {
		$width = $this->get_stroke_width( $weight );

		if ( ! $width || ! $selectors ) {
			return '';
		}

		return implode( '::before,', $selectors ) . '::before{-webkit-text-stroke:' . $width . ' currentColor;paint-order:stroke fill;}';
	}

	/**
	 * Builds the CSS mask image that draws an icon.
	 *
	 * WHY A MASK. This plugin cannot put markup where its icons go: they are drawn on HivePress's
	 * and WooCommerce's own menu anchors, which belong to those plugins, so the only thing this one
	 * gets to add is a `::before` on somebody else's element. Until now that meant a webfont - a
	 * codepoint in `content`, a `font-family`, and a 320KB copy of Font Awesome bundled with the
	 * plugin to supply the glyph. A mask does the same job from the icon's path data: the
	 * pseudo-element is painted with the icon colour and the SVG is used as its stencil, so colour,
	 * size and spacing all keep working exactly as they did, no font loads on the front end at all,
	 * and every icon in the library is reachable instead of the 1,465 the codes config listed.
	 *
	 * Both the prefixed and unprefixed properties are emitted. Safari has only ever supported
	 * `-webkit-mask-image`; Chrome and Firefox take the standard one.
	 *
	 * WEIGHT IS BAKED IN rather than applied in CSS. A mask is an alpha channel, so
	 * `-webkit-text-stroke` has nothing to act on and the setting would silently stop working. The
	 * stroke goes on the path instead, where the union of stroke and fill thickens the glyph the
	 * same way `paint-order: stroke fill` did. `vector-effect="non-scaling-stroke"` is what keeps
	 * the width honest: without it, "1px" would be one unit of a 512-unit viewBox and invisible.
	 *
	 * Public for the same reason its predecessor was: the persistent menu component draws the
	 * placeholder-page icon with it, and the two must not drift apart.
	 *
	 * @param string $icon Icon name, in any Font Awesome version's spelling.
	 * @param string $weight Weight choice, or an empty string for the normal weight.
	 * @return string A CSS url() value, or an empty string if the icon is unknown.
	 */
	public function get_icon_mask( $icon, $weight = '' ) {
		$svg = $this->get_icon_svg( $icon, $weight, '' );

		return $svg ? 'url("' . self::encode_svg( $svg ) . '")' : '';
	}

	/**
	 * Builds the CSS background image that draws an icon in a fixed colour.
	 *
	 * The second of the two drawing modes, used only when the owner has set an icon background - the
	 * round colour chip behind each icon. A mask cannot coexist with a chip: masking applies to
	 * everything the element paints, so the chip would be cut into the shape of the icon and
	 * disappear. Painting the icon as an ordinary background image leaves the chip free to be the
	 * element's background colour, exactly as it was under the webfont.
	 *
	 * The cost is that the colour has to be baked into the image, because an SVG loaded as an image
	 * cannot see the page's `currentColor`. It only applies to sites that have set a chip, and a
	 * site with a chip has almost certainly set an icon colour as well - a glyph left on the menu's
	 * own text colour over a coloured chip is a combination an owner has to correct by hand under
	 * the webfont too. Where there is genuinely no colour to use, white is the fallback, being the
	 * legible choice over the mid-tone chips a colour picker tends to produce.
	 *
	 * @param string $icon Icon name.
	 * @param string $weight Weight choice.
	 * @param string $colour Icon colour, already sanitised.
	 * @return string A CSS url() value, or an empty string if the icon is unknown.
	 */
	public function get_icon_image( $icon, $weight = '', $colour = '' ) {
		$svg = $this->get_icon_svg( $icon, $weight, $colour ? $colour : '#ffffff' );

		return $svg ? 'url("' . self::encode_svg( $svg ) . '")' : '';
	}

	/**
	 * Builds the SVG markup for an icon.
	 *
	 * Single quotes throughout, so the double quotes wrapping the url() cannot be closed early.
	 *
	 * @param string $icon Icon name, in any Font Awesome version's spelling.
	 * @param string $weight Weight choice, or an empty string for the normal weight.
	 * @param string $colour Colour to paint the glyph, or an empty string to leave it opaque black
	 *                       for use as a mask, where only the alpha channel is read.
	 * @return string SVG markup, or an empty string if the icon is unknown.
	 */
	protected function get_icon_svg( $icon, $weight, $colour ) {
		if ( ! class_exists( 'FAFH' ) ) {
			return '';
		}

		// No style is passed on purpose. Asking for a named style makes a brand icon fail, because
		// it does not exist in the solid set; leaving it to the library lets it read the icon's real
		// style out of its own index, which is how brands work here without a list of brand names.
		$pair = \FAFH::pair( (string) $icon );

		if ( ! $pair ) {
			return '';
		}

		list( $view_box, $path ) = explode( '|', $pair, 2 );

		$paint  = $colour ? $colour : '#000';
		$stroke = '';
		$width  = $this->get_stroke_width( (string) $weight );

		if ( $width ) {
			$stroke = " stroke='" . $paint . "' stroke-width='" . (float) $width . "' stroke-linejoin='round' vector-effect='non-scaling-stroke'";
		}

		return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='" . $view_box . "'><path fill='" . $paint . "'" . $stroke . " d='" . $path . "'/></svg>";
	}

	/**
	 * Packs an SVG into a data URI small enough to sit in a stylesheet.
	 *
	 * Escaping is deliberately narrow. rawurlencode() would be correct, and is what a first draft
	 * used, but it escapes every space and comma too - and Font Awesome path data is mostly spaces
	 * and commas, so it added roughly 60% to a string that is already 1-2KB and appears once per
	 * menu item. Only the characters that would actually end the URL or confuse the CSS parser are
	 * escaped instead; path data is only digits, letters, spaces, commas, dots and minus signs, so
	 * nothing else can appear. The SVG is written with single quotes so the double quotes wrapping
	 * the url() cannot be closed early.
	 *
	 * @param string $svg SVG markup.
	 * @return string A data: URI.
	 */
	protected static function encode_svg( $svg ) {
		return 'data:image/svg+xml,' . str_replace(
			[ '%', '#', '<', '>', '"', '{', '}' ],
			[ '%25', '%23', '%3C', '%3E', '%22', '%7B', '%7D' ],
			$svg
		);
	}

	/**
	 * Gets the stroke width for an icon weight choice.
	 *
	 * The weight is faked with a text stroke in the icon's own colour, the
	 * technique site owners were applying by hand: Font Awesome Free ships a
	 * single solid weight, so a real font-weight change does nothing.
	 *
	 * Public for the same reason as get_icon_mask(): the placeholder pages
	 * thicken their icon with the identical technique, and the two must not
	 * drift into using different widths for the same setting.
	 *
	 * @param string $weight Weight choice.
	 * @return string A CSS length, or an empty string for the normal weight.
	 */
	public function get_stroke_width( $weight ) {
		if ( 'semibold' === $weight ) {
			return '0.5px';
		}

		if ( 'bold' === $weight ) {
			return '1px';
		}

		return '';
	}

	/**
	 * Builds the icon CSS.
	 *
	 * @return string
	 */
	protected function get_icon_css() {

		// Get the icon assignments.
		$rules = $this->get_icon_rules();

		foreach ( $this->get_custom_items() as $name => $item ) {
			if ( $item['icon'] ) {
				$rules[ $name ] = [
					'icon'   => $item['icon'],
					'colour' => $item['colour'],
					'weight' => $item['weight'],
				];
			}
		}

		if ( ! $rules ) {
			return '';
		}

		/*
		 * The global weight is the default for every item, so an item that has not chosen one of its
		 * own is drawn with it. Under the webfont this was a separate CSS rule that cascaded; the
		 * weight now lives inside each image, where nothing can cascade, so the fallback has to
		 * happen here while each one is built.
		 */
		$default_weight = (string) get_option( 'hp_amehp_icon_weight' );
		$default_colour = $this->sanitize_colour( (string) get_option( 'hp_amehp_icon_colour' ) );

		// Which of the two drawing modes to use. See get_icon_image() for why a chip rules out a
		// mask. It is one decision for the whole stylesheet because the chip is a global setting.
		$chip = $this->sanitize_colour( (string) get_option( 'hp_amehp_icon_background' ) );

		$css       = '';
		$selectors = [];

		foreach ( $rules as $key => $rule ) {
			$weight = '' !== $rule['weight'] ? (string) $rule['weight'] : $default_weight;
			$colour = $this->sanitize_colour( $rule['colour'] );

			if ( $chip ) {
				$image = $this->get_icon_image( $rule['icon'], $weight, $colour ? $colour : $default_colour );
			} else {
				$image = $this->get_icon_mask( $rule['icon'], $weight );
			}

			if ( ! $image ) {
				continue;
			}

			$item_selectors = $this->get_item_selectors( (string) $key );

			if ( ! $item_selectors ) {
				continue;
			}

			$selectors = array_merge( $selectors, $item_selectors );

			// Add the icon rule.
			if ( $chip ) {
				$css .= implode( '::before,', $item_selectors ) . '::before{background-image:' . $image . ';}';
			} else {
				// Held in a custom property and referenced twice, rather than written out twice. A
				// data URI for one glyph is 1-2KB and this stylesheet is inline on every page, so
				// emitting each one once instead of once per vendor prefix halves the whole block.
				$css .= implode( '::before,', $item_selectors ) . '::before{--amehp-icon-image:' . $image . ';-webkit-mask-image:var(--amehp-icon-image);mask-image:var(--amehp-icon-image);}';
			}

			// Add the colour rule. Nothing to set in chip mode: the colour is already inside the
			// image, and the pseudo-element's own background is the chip.
			if ( $colour && ! $chip ) {
				$css .= implode( ',', $item_selectors ) . '{--amehp-icon-colour:' . $colour . ';}';
			}
		}

		if ( ! $css ) {
			return '';
		}

		/*
		 * The base rule, shared by both modes.
		 *
		 * `content` is an empty string rather than a codepoint, because the glyph is no longer a
		 * character. A ::before holding a character was sized by the font; an empty one has no
		 * intrinsic height at all and would collapse, so 1em is set explicitly and the box is nudged
		 * down by an eighth of an em to sit on the text baseline the way a glyph did.
		 *
		 * `contain` rather than `cover`, because Font Awesome viewBoxes are not square - they run
		 * from 128 to 640 units wide against a fixed 512 tall - and `cover` would crop the wide ones.
		 */
		$base = implode( '::before,', array_unique( $selectors ) ) . '::before{content:"";display:inline-block;width:1.25em;height:1em;vertical-align:-0.125em;margin-inline-end:' . $this->get_icon_spacing() . ';';

		if ( $chip ) {
			$base .= 'background-repeat:no-repeat;background-position:center;background-size:contain;}';
		} else {
			/*
			 * The icon colour is the pseudo-element's own background, and the mask decides which of
			 * that paint survives. `currentColor` still resolves to the anchor's colour, so an owner
			 * who has set no colour gets an icon that matches the menu text, exactly as before.
			 *
			 * Both the prefixed and unprefixed properties are emitted: Safari has only ever supported
			 * `-webkit-mask-*`, while Chrome and Firefox take the standard ones.
			 */
			$base .= 'background-color:var(--amehp-icon-colour,currentColor);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}';

			// Add the default colour.
			if ( $default_colour ) {
				$base .= ':root{--amehp-icon-colour:' . $default_colour . ';}';
			}
		}

		return $base . $css;
	}

	/**
	 * Builds the account menu appearance CSS.
	 *
	 * Covers the optional menu item weight, the theme chevron hiding and the
	 * WooCommerce account page header hiding.
	 *
	 * @return string
	 */
	protected function get_appearance_css() {
		$css = '';

		/*
		 * Keep every account menu item left-aligned, icon or no icon.
		 *
		 * This used to be emitted only for the items this plugin gave an
		 * icon to, which broke alignment wherever an icon came from anywhere
		 * else: a theme or customiser drawing its own CSS icon leaves the
		 * link a flex row, and without flex-start the wording is flung to the
		 * far side of the menu. So the rule covers EVERY item in the account
		 * menus, always, and is inert where the link is not a flex container.
		 */
		$css .= '.hp-menu--user-account .hp-menu__item > a,.woocommerce-MyAccount-navigation ul li > a{justify-content:flex-start;}';

		/*
		 * A small, constant gap between the wording and its counter.
		 *
		 * This was `margin-inline-start:auto` until 3.3.13, meant to push the
		 * counter to the menu's edge in themes that lay the link out as a flex
		 * row. One value cannot do both jobs, and `auto` gave the WORST of each:
		 * in a flex row it flung the counter to the far right, away from the
		 * wording it belongs to, and in a link the theme lays out as a block it
		 * collapses to nothing, so the number sat flush against the last letter
		 * with no gap at all. Chris reported both on the same page, in the
		 * sidebar and the header dropdown respectively (2026-08-31).
		 *
		 * 0.5rem is core's own value for this element
		 * (`.hp-menu__item small` in hivepress/assets/css/frontend.min.css), so
		 * the counter now sits where HivePress puts it, in every menu, however
		 * the theme lays the link out.
		 */
		$css .= '.hp-menu--user-account .hp-menu__item > a > small,.woocommerce-MyAccount-navigation ul li > a > small{margin-inline-start:0.5rem;}';

		/*
		 * The icon gap, applied to every item in the account menu.
		 *
		 * The icon rule further up only names the items this plugin has been given an icon for, which
		 * is the right scope for drawing an icon but the wrong scope for the gap. Plenty of sites draw
		 * most of their menu icons themselves, in a theme or a customiser stylesheet, and on one of
		 * those the setting appeared broken: it moved the two items this plugin owned and left the
		 * other thirteen at the theme's spacing, which reads as "the setting does nothing".
		 *
		 * So the gap is also emitted once for the whole menu. Only the gap - no icon, no colour, no
		 * width - so an icon somebody else drew keeps its own appearance and only moves closer to or
		 * further from its label. An item with no icon at all has no ::before box to shift, so the
		 * rule costs it nothing.
		 *
		 * Both are needed. This one is broad and would be overridden by the per-item rule's own
		 * spacing; the per-item rule carries it too so the two always agree.
		 */
		$spacing = $this->get_chosen_icon_spacing();

		if ( '' !== $spacing ) {
			$css .= '.hp-menu--user-account .hp-menu__item > a::before,.woocommerce-MyAccount-navigation ul li > a::before{margin-inline-end:' . $spacing . ' !important;}';
		}

		/*
		 * The icon size, applied to every account menu icon for the same
		 * reason as the gap above: sizing only the icons this plugin drew
		 * would read as a broken setting on a site whose theme draws the
		 * rest. Marked important on the same principle as the gap - a number
		 * in this box is an owner overruling their theme on purpose.
		 */
		$size = get_option( 'hp_amehp_icon_size' );

		if ( is_numeric( $size ) ) {
			$css .= '.hp-menu--user-account .hp-menu__item > a::before,.woocommerce-MyAccount-navigation ul li > a::before{font-size:' . max( 8, min( 48, (int) $size ) ) . 'px !important;}';
		}

		// The global icon weight, thickening every account menu icon. A
		// per-item weight in the styling rows overrides it there: the
		// per-item rules tie on specificity but are emitted after this one
		// (the appearance CSS is concatenated first - see
		// enqueue_frontend_assets), so they win the cascade.
		$weight_rule = $this->get_weight_rule(
			[
				'.hp-menu--user-account .hp-menu__item > a',
				'.woocommerce-MyAccount-navigation ul li > a',
			],
			(string) get_option( 'hp_amehp_icon_weight' )
		);

		if ( $weight_rule ) {
			$css .= $weight_rule;
		}

		// The icon background chip: a round colour swatch behind every icon.
		// The base icon rule already fixes the width at 1.25em, and the
		// padding tops it up to a near circle whatever the icon shape.
		$background = $this->sanitize_colour( (string) get_option( 'hp_amehp_icon_background' ) );

		if ( $background ) {
			$css .= '.hp-menu--user-account .hp-menu__item > a::before,.woocommerce-MyAccount-navigation ul li > a::before{background-color:' . $background . ';border-radius:50%;padding:0.3em;box-sizing:content-box;}';
		}

		/*
		 * The theme Heading Font on the sidebar account menus.
		 *
		 * The official themes apply the Customiser's Heading Font to the
		 * header account dropdown but leave the sidebar menus on the Body
		 * Font. The mod is read the same way hivetheme's own style builder
		 * reads it (themes/listinghive/vendor/hivepress/hivetheme/includes
		 * /components/class-customizer.php:85-99): the stored value can carry
		 * a ":weights" suffix, so only the part before the colon is the
		 * family. hivetheme registers a default filter for the mod, so on an
		 * official theme this resolves even before the owner customises
		 * anything; on other themes the mod is absent and nothing is emitted.
		 * The family is quoted and gated to safe characters so a corrupted
		 * mod value can never break out of the declaration.
		 */
		if ( get_option( 'hp_amehp_sidebar_heading_font' ) ) {
			$font   = (string) get_theme_mod( 'heading_font' );
			$family = trim( (string) hp\get_first_array_value( explode( ':', $font ) ) );

			if ( $family && preg_match( '/^[A-Za-z0-9 \'\-]+$/', $family ) ) {
				$css .= '.widget_nav_menu .hp-menu__item > a,.woocommerce-MyAccount-navigation ul li > a{font-family:"' . str_replace( "'", '', $family ) . '", sans-serif;}';
			}
		}

		// Set the menu item font weight.
		$weight = (string) get_option( 'hp_amehp_menu_weight' );

		if ( preg_match( '/^[1-9]00$/', $weight ) ) {
			$css .= '.hp-menu--user-account .hp-menu__item > a,.woocommerce-MyAccount-navigation ul li > a{font-weight:' . $weight . ';}';
		}

		// Hide the theme navigation chevrons on the sidebar account menus
		// only. HivePress renders the sidebar menu inside a "widget_nav_menu"
		// widget, so scoping to it leaves the header account dropdown (the
		// same menu without that class) and other navigation menus (for
		// example in the footer, which have no "hp-menu__item") untouched. The
		// inline-start padding the theme reserved for the chevron is also
		// removed so the items sit flush.
		if ( get_option( 'hp_amehp_hide_chevrons' ) ) {

			// `content:none` is what actually removes the marker: real sites
			// carry theme and customiser rules that re-set `display` on this
			// pseudo-element with more specific selectors, and a display:none
			// here lost that fight even marked important (confirmed by a live
			// debugging session on 2026-08-29). A pseudo-element with
			// `content:none` is never generated at all, so there is nothing
			// left for a display rule to switch back on; display:none is kept
			// as the belt for engines that treat content:none loosely.
			$css .= '.widget_nav_menu .hp-menu__item::before,.woocommerce-MyAccount-navigation ul li::before{content:none !important;display:none !important;}';
			$css .= '.widget_nav_menu .hp-menu__item,.woocommerce-MyAccount-navigation ul li{padding-inline-start:0;}';
		}

		// Hide the WooCommerce account page header.
		if ( hp\is_plugin_active( 'woocommerce' ) && get_option( 'hp_amehp_hide_wc_header' ) ) {
			$css .= 'body.woocommerce-account .header-hero--title{display:none;}';
		}

		return $css;
	}

	/**
	 * Builds the menu item text colour CSS.
	 *
	 * Each styling row can set a text colour for its menu item, applied to the
	 * link independently of the icon colour.
	 *
	 * @return string
	 */
	protected function get_text_colour_css() {
		$css  = '';
		$rows = get_option( 'hp_amehp_icons' );

		// Add the menu item styling text colours.
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['item'] ) ) {
					continue;
				}

				$colour = $this->sanitize_colour( isset( $row['text_colour'] ) && is_string( $row['text_colour'] ) ? $row['text_colour'] : '' );

				if ( ! $colour ) {
					continue;
				}

				$selectors = $this->get_item_selectors( (string) $row['item'] );

				if ( $selectors ) {
					$css .= implode( ',', $selectors ) . '{color:' . $colour . ';}';
				}
			}
		}

		// Add the custom item text colours.
		foreach ( $this->get_custom_items() as $name => $item ) {
			$colour = $this->sanitize_colour( $item['text_colour'] );

			if ( ! $colour ) {
				continue;
			}

			$selectors = $this->get_item_selectors( $name );

			if ( $selectors ) {
				$css .= implode( ',', $selectors ) . '{color:' . $colour . ';}';
			}
		}

		return $css;
	}

	/**
	 * Gets the HivePress counter values keyed by menu item name.
	 *
	 * @return array
	 */
	protected function get_badge_counts() {
		if ( ! $this->is_badges_enabled() || ! hp\is_plugin_active( 'woocommerce' ) ) {
			return [];
		}

		$badges = [];

		foreach ( $this->get_base_hp_items() as $name => $item ) {
			if ( isset( $item['meta'] ) && '' !== (string) $item['meta'] ) {
				$key = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $name );

				if ( $key ) {
					$badges[ $key ] = (string) $item['meta'];
				}
			}
		}

		return $badges;
	}

	/*
	-------------------------------------------------------------------------
	Settings options
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets the pinned HivePress menu items keyed by item name.
	 *
	 * These cover the account pages added by the official extensions, so that
	 * they can be configured even when the current admin does not see them in
	 * their own account menu.
	 *
	 * @return array
	 */
	protected function get_pinned_items() {
		return [
			'user_edit_settings' => [
				'route' => 'user_edit_settings_page',
				'label' => esc_html__( 'Settings', 'account-menu-enhancer-for-hivepress' ),
			],

			'listings_edit'      => [
				'route' => 'listings_edit_page',
				'label' => esc_html__( 'Listings', 'account-menu-enhancer-for-hivepress' ),
			],

			'listings_favorite'  => [
				'route' => 'listings_favorite_page',
				'label' => esc_html__( 'Favorites', 'account-menu-enhancer-for-hivepress' ),
			],

			'listing_import'     => [
				'route' => 'listing_import_page',
				'label' => esc_html__( 'Import Listings', 'account-menu-enhancer-for-hivepress' ),
			],

			'messages_thread'    => [
				'route' => 'messages_thread_page',
				'label' => esc_html__( 'Messages', 'account-menu-enhancer-for-hivepress' ),
			],

			'vendor_dashboard'   => [
				'route' => 'vendor_dashboard_page',
				'label' => esc_html__( 'Dashboard', 'account-menu-enhancer-for-hivepress' ),
			],

			'orders_edit'        => [
				'route' => 'orders_edit_page',
				'label' => esc_html__( 'Received Orders', 'account-menu-enhancer-for-hivepress' ),
			],

			'payouts_view'       => [
				'route' => 'payouts_view_page',
				'label' => esc_html__( 'Payouts', 'account-menu-enhancer-for-hivepress' ),
			],

			'bookings_view'      => [
				'route' => 'bookings_view_page',
				'label' => esc_html__( 'Bookings', 'account-menu-enhancer-for-hivepress' ),
			],

			'vendor_calendar'    => [
				'route' => 'vendor_calendar_page',
				'label' => esc_html__( 'Calendar', 'account-menu-enhancer-for-hivepress' ),
			],

			'search_alerts_view' => [
				'route' => 'search_alerts_view_page',
				'label' => esc_html__( 'Searches', 'account-menu-enhancer-for-hivepress' ),
			],

			'memberships_view'   => [
				'route' => 'memberships_view_page',
				'label' => esc_html__( 'Memberships', 'account-menu-enhancer-for-hivepress' ),
			],

			'requests_edit'      => [
				'route' => 'requests_edit_page',
				'label' => esc_html__( 'Requests', 'account-menu-enhancer-for-hivepress' ),
			],

			'offers_view'        => [
				'route' => 'offers_view_page',
				'label' => esc_html__( 'Offers', 'account-menu-enhancer-for-hivepress' ),
			],

			'user_logout'        => [
				'route' => 'user_logout_page',
				'label' => esc_html__( 'Sign Out', 'account-menu-enhancer-for-hivepress' ),
			],
		];
	}

	/**
	 * Gets a HivePress route title.
	 *
	 * Route titles can be callables that expect the front-end query context
	 * (for example, the vendor profile title calls the_post()), so only plain
	 * string titles are returned here.
	 *
	 * @param string $route Route name.
	 * @return string
	 */
	protected function get_route_title( $route ) {
		$args = hivepress()->router->get_route( $route );

		if ( ! is_array( $args ) ) {
			return '';
		}

		$title = hp\get_array_value( $args, 'title' );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * Records and hides on the /items stage, where late-registered items really exist.
	 *
	 * The boot() pass starts from the constructor-stage items, so by the time this filter runs the set is
	 * complete: core, the constructor-stage additions, and everything other extensions add on this
	 * same filter at lower priorities. Recording HERE is what makes an /items-registered item
	 * appear on the settings screen, and unsetting HERE is what makes hiding it actually work -
	 * the constructor-stage unset in alter_hp_menu() still runs, but an item added after it needs
	 * this second pass.
	 *
	 * The suppression flag matters: get_base_hp_items() constructs a User_Account menu of its own
	 * while building the settings screen, and this callback firing during that construction must
	 * neither record (wrong context) nor hide (the settings screen needs the full list).
	 *
	 * @param array $items Menu items, complete.
	 * @return array
	 */
	public function alter_hp_menu_items( $items ) {
		if ( $this->suppressed || ! is_array( $items ) ) {
			return $items;
		}

		$this->record_seen_items( $items );

		foreach ( $this->get_hidden_keys() as $key ) {
			if ( 0 === strpos( $key, 'hp:' ) ) {
				unset( $items[ substr( $key, strlen( 'hp:' ) ) ] );
			}
		}

		/*
		 * The owner's chosen order is applied HERE rather than at the
		 * constructor stage, and that is the whole reason this menu is
		 * reordered on the /items filter at all: core sorts the items in
		 * set_items() (hivepress/includes/menus/class-menu.php:151), which
		 * runs AFTER this filter, and an item another extension registers on
		 * this same filter does not exist yet at the constructor stage. Both
		 * the header account dropdown and the account page sidebar are this
		 * one menu, so ordering it once covers them both.
		 */
		return $this->apply_menu_order( $items );
	}

	/**
	 * Remembers the account menu items this site actually renders.
	 *
	 * The settings screen builds its list of hideable items by constructing the account menu in the
	 * admin, and that list is incomplete through no fault of the extensions in it. An extension is
	 * entitled to register its menu item inside `if ( ! is_admin() )` - the menu is a front-end thing
	 * - and this plugin's own Notifications extension does exactly that. The item then renders
	 * perfectly on the account page and is simply absent from the list of things an owner may hide,
	 * which reads as this plugin having missed it.
	 *
	 * Guessing does not work either: the pinned list below covers the extensions known when it was
	 * written, and an item name cannot be derived from a route name (the Gallery item is `gallery`
	 * while its route is `gallery_edit_page`). The only reliable source is the menu as it is really
	 * built, so it is recorded here, on the front end, where every extension has had its say.
	 *
	 * Items accumulate rather than replace, because the menu differs from one visitor to the next - a
	 * vendor sees pages a buyer never does - and a buyer's page load must not erase the vendor-only
	 * items from the owner's settings screen. Anything whose route has since stopped resolving is
	 * dropped when the list is read, so deactivating an extension still clears its item.
	 *
	 * @param array $items Menu items, as registered and before any are hidden.
	 */
	protected function record_seen_items( $items ) {
		$seen = $this->get_seen_items();

		/*
		 * A record the read just pruned counts as a change.
		 *
		 * $seen is the CLEANED list, so a custom item deleted by the owner has
		 * already gone from it, and the shrunken list would otherwise sit in
		 * memory while the stored option kept the dead record - written back
		 * only if something else happened to change on some later page view,
		 * which on a settled site is never. Treating the prune as a change is
		 * what makes the option actually get smaller, and it is self-limiting:
		 * once written there is nothing left to prune, so the next build reads
		 * a clean list and writes nothing.
		 */
		$changed = $this->seen_items_compacted;

		foreach ( $items as $name => $item ) {
			if ( ! is_string( $name ) || ! is_array( $item ) ) {
				continue;
			}

			$route = hp\get_array_value( $item, 'route' );
			$route = is_string( $route ) ? $route : '';

			// The label is usually still unset at this point, because core fills it in from the route
			// after this filter has run. Resolve it the same way core will.
			$label = hp\get_array_value( $item, 'label' );
			$label = is_string( $label ) ? wp_strip_all_tags( $label ) : '';

			if ( ! $label && $route ) {
				$label = $this->get_route_title( $route );
			}

			if ( ! $label ) {
				continue;
			}

			/*
			 * The item's own position in the menu is recorded alongside its
			 * label from 3.2.0, because the settings screen has no other way
			 * to know it. The account menu built in wp-admin carries only a
			 * handful of items - an extension is entitled to register its item
			 * inside `if ( ! is_admin() )`, and many do - so the live preview
			 * had every one of those at the same fallback position and listed
			 * them alphabetically, which is not an order the site has ever
			 * rendered. Here on the front end the menu is complete and the
			 * order is the real one.
			 */
			$order = hp\get_array_value( $item, '_order' );
			$order = is_numeric( $order ) ? (int) $order : null;

			if ( isset( $seen[ $name ] ) && $seen[ $name ]['label'] === $label && $seen[ $name ]['route'] === $route && $seen[ $name ]['order'] === $order ) {
				continue;
			}

			$seen[ $name ] = [
				'label' => $label,
				'route' => $route,
				'order' => $order,
			];

			$changed = true;
		}

		/*
		 * Only ever written when something is genuinely new, so a settled site does no writes at
		 * all. Autoloaded on purpose: the read above happens on every page view for every
		 * logged-in user (the header dropdown builds this menu on every page, and page caches
		 * bypass logged-in traffic), and a non-autoloaded option would make each of those views
		 * pay one extra uncached SELECT forever. The payload is bounded - a name, a label and a
		 * route per account menu item, a few kilobytes on the heaviest site - which is exactly
		 * what alloptions is for.
		 */
		if ( $changed ) {
			update_option( 'hp_amehp_seen_items', $seen, true );

			// The cached read below is now behind the option, so it goes. The
			// next reader rebuilds from what was actually written rather than
			// from $seen, because the two are not the same thing: the write
			// stores labels as they arrived, and the read cleans them again.
			$this->seen_items = null;
		}
	}

	/**
	 * Gets the recorded account menu items.
	 *
	 * MEMOISED FOR THE REQUEST, because this is a hot path and the cleaning is
	 * not free. record_seen_items() calls it on every account menu build, and a
	 * signed-in page view builds that menu two or three times (the header
	 * dropdown, the sidebar, and the persistent component's probe) - measured
	 * on 2026-08-30. Each build then walked every stored record through
	 * wp_strip_all_tags(), mb_substr() and a route lookup, for an answer that
	 * cannot have changed since the last one in the same request.
	 *
	 * The only writer is record_seen_items(), which drops the cache when it
	 * writes, so nothing can read a stale list.
	 *
	 * @return array<string, array{label: string, route: string, order: int|null}>
	 */
	protected function get_seen_items() {
		if ( null !== $this->seen_items ) {
			return $this->seen_items;
		}

		$seen = get_option( 'hp_amehp_seen_items' );

		if ( ! is_array( $seen ) ) {
			$this->seen_items           = [];
			$this->seen_items_compacted = false;

			return $this->seen_items;
		}

		$items     = [];
		$custom    = null;
		$compacted = false;

		foreach ( $seen as $name => $item ) {
			if ( ! is_string( $name ) || ! is_array( $item ) ) {
				continue;
			}

			$label = hp\get_array_value( $item, 'label' );
			$route = hp\get_array_value( $item, 'route' );

			// Stored from a front-end filter anything may have hooked, so cleaned again on the way
			// out rather than trusting what went in.
			$label = is_string( $label ) ? mb_substr( wp_strip_all_tags( $label ), 0, 100 ) : '';

			if ( ! $label ) {
				continue;
			}

			$route = is_string( $route ) ? $route : '';

			// An item whose route no longer exists belongs to an extension that has been turned off.
			if ( $route && ! hivepress()->router->get_route( $route ) ) {
				continue;
			}

			/*
			 * A CUSTOM ITEM'S RECORD, FOR AN ITEM THAT NO LONGER EXISTS.
			 *
			 * The route test above cannot reach these. It only fires when a
			 * record HAS a route, and a custom item has none - so from 3.2.0,
			 * when recording began, every custom item ever created left a
			 * record here that nothing could ever remove. Measured on the
			 * development install on 2026-08-30: 3,384 bytes, the sixth-largest
			 * autoloaded option on the site and 2.2% of alloptions, holding
			 * three dead custom-item records against one live item. This option
			 * is autoloaded and read on every signed-in page view, so it is not
			 * a place for anything to accumulate for ever.
			 *
			 * Dropping every route-less record is the WRONG fix and would cost
			 * the owner real settings: the WooCommerce endpoints that HivePress
			 * merges into the account menu (Downloads, Addresses, Account
			 * details, Orders, Subscriptions) have no HivePress route either,
			 * and the settings screen would lose them. So the test is narrowed
			 * to this plugin's own item keys, and asks get_custom_items() - the
			 * one place that decides what a custom item is called - whether the
			 * item is still there.
			 *
			 * The same test is what writeOrder() applies to the stored menu
			 * order in the settings screen (mergeOrder() in preview-logic.js,
			 * "the one key that is dropped is a CUSTOM item that matches no
			 * row"), and it is deliberately the same one: both are asking
			 * whether an "amehp_item_" key still names something. Deriving the
			 * key list a second way here is how the two would drift.
			 *
			 * Resolved lazily, so a site that has never created a custom item
			 * pays nothing for this on a path that runs on every page view.
			 */
			if ( 0 === strpos( $name, 'amehp_item_' ) ) {
				if ( null === $custom ) {
					$custom = $this->get_custom_items();
				}

				if ( ! isset( $custom[ $name ] ) ) {
					$compacted = true;

					continue;
				}
			}

			// Absent on a record written before 3.2.0, and on any item whose
			// own registration carried no order, so it stays nullable.
			$order = hp\get_array_value( $item, 'order' );

			$items[ $name ] = [
				'label' => $label,
				'route' => $route,
				'order' => is_numeric( $order ) ? (int) $order : null,
			];
		}

		$this->seen_items           = $items;
		$this->seen_items_compacted = $compacted;

		return $items;
	}

	/**
	 * Gets the menu item options for the settings screen.
	 *
	 * @return array
	 */
	public function get_menu_item_options() {
		$options = [];

		/*
		 * The WooCommerce endpoint names, so an item this screen already lists
		 * under a WooCommerce name is recognised and left to the block below.
		 *
		 * ONE OPTION PER REAL DESTINATION is the rule these two loops keep, and
		 * get_settings_key() is the single place that decides which name an item
		 * is listed under. Asking it, rather than naming the exceptions again
		 * here, is what stops this list growing a second "Orders" entry that
		 * hides and styles a different row from the first.
		 */
		$endpoints = array_merge( $this->get_base_wc_items(), $this->get_registered_wc_endpoints() );

		// Add the live HivePress menu items.
		foreach ( $this->get_base_hp_items() as $name => $item ) {
			$key = $this->get_settings_key( (string) $name, $endpoints );

			if ( 0 !== strpos( $key, 'hp:' ) ) {
				continue;
			}

			$label = isset( $item['label'] ) ? wp_strip_all_tags( (string) $item['label'] ) : '';

			if ( $label ) {
				$options[ $key ] = $label;
			}
		}

		/*
		 * Add the items seen on the front end that the admin-built menu could not show.
		 *
		 * Only items that came from a route. The account menu also carries the WooCommerce endpoints
		 * that HivePress merges into it, and those have no route of their own; they are already
		 * offered below under their WooCommerce names, so admitting them here would list Downloads,
		 * Addresses and Account details twice on the settings screen.
		 *
		 * The same is true of the lists HivePress core adds - "Placed Orders",
		 * "Subscriptions" - which are equally route-less and equally already
		 * offered below. They are excluded by the key test rather than by the
		 * route test, because a site that has deactivated WooCommerce still
		 * holds their records and must not start offering them under a
		 * HivePress name.
		 */
		foreach ( $this->get_seen_items() as $name => $item ) {
			$key = $this->get_settings_key( (string) $name, $endpoints );

			if ( ! $item['route'] || 0 !== strpos( $key, 'hp:' ) || isset( $options[ $key ] ) ) {
				continue;
			}

			$options[ $key ] = $item['label'];
		}

		// Add the pinned HivePress menu items.
		foreach ( $this->get_pinned_items() as $name => $item ) {
			if ( isset( $options[ 'hp:' . $name ] ) || ! hivepress()->router->get_route( $item['route'] ) ) {
				continue;
			}

			$title = $this->get_route_title( $item['route'] );

			$options[ 'hp:' . $name ] = $title ? $title : $item['label'];
		}

		asort( $options );

		// Add the WooCommerce menu items.
		if ( hp\is_plugin_active( 'woocommerce' ) ) {
			$wc_options = [];

			foreach ( $this->get_base_wc_items() as $endpoint => $label ) {
				/* translators: %s: menu item label. */
				$wc_options[ 'wc:' . $endpoint ] = sprintf( esc_html__( '%s (WooCommerce)', 'account-menu-enhancer-for-hivepress' ), $label );
			}

			/*
			 * Then every account endpoint that is registered but did not come back from the menu.
			 *
			 * wc_get_account_menu_items() is a filtered list, and plugins routinely add their item
			 * only when the person looking has something to look at - WooCommerce Subscriptions adds
			 * "Subscriptions" only for a user who already has one. Asked in wp-admin, on behalf of an
			 * administrator who happens to have none, it answers without that item, and the owner
			 * could never choose to hide a menu entry their members can plainly see.
			 *
			 * The registered endpoints do not depend on who is asking, so they fill the gap.
			 */
			foreach ( $this->get_registered_wc_endpoints() as $endpoint => $label ) {
				if ( isset( $wc_options[ 'wc:' . $endpoint ] ) ) {
					continue;
				}

				/* translators: %s: menu item label. */
				$wc_options[ 'wc:' . $endpoint ] = sprintf( esc_html__( '%s (WooCommerce)', 'account-menu-enhancer-for-hivepress' ), $label );
			}

			$options = array_merge( $options, $wc_options );
		}

		/*
		 * Keep the previously saved keys selectable.
		 *
		 * From the stored rows, NOT from get_icon_rules(). That method deliberately returns only
		 * rows that have an icon, so a styling row carrying a text colour and no icon was missing
		 * from this list - and a key missing here is a key the select field no longer offers. Once
		 * the extension that provided the menu item was deactivated, the next save of this tab
		 * found the stored value outside the options, sanitised it to null, and core's Repeater
		 * drops any row whose required field came back null
		 * (hivepress/includes/fields/class-repeater.php:107-116). The row was gone for good, the
		 * screen said the settings had saved, and reactivating the extension brought nothing back.
		 * Anything a saved row REFERS TO has to stay selectable, whatever else that row does or
		 * does not set.
		 */
		$saved = array_merge( $this->get_hidden_keys(), $this->get_styled_keys() );

		foreach ( $saved as $key ) {
			if ( is_string( $key ) && '' !== $key && ! isset( $options[ $key ] ) ) {
				$options[ $key ] = ucwords( str_replace( [ 'hp:', 'wc:', '_', '-' ], [ '', '', ' ', ' ' ], $key ) );
			}
		}

		return $options;
	}

	/**
	 * Gets the menu item options that can appear in the WooCommerce account menu.
	 *
	 * OFFERING ONLY WHAT THE SETTING CAN ACT ON. "Also Hidden from the
	 * WooCommerce Menu" removes a row from that menu alone, so an item that
	 * cannot be in it is an option that would do nothing at all - and a setting
	 * that silently does nothing is a support question, not a feature. Two
	 * groups can be there, and they qualify for different reasons:
	 *
	 * - A WooCommerce endpoint (`wc:`) is in that menu on its own account,
	 *   whatever this plugin is set to.
	 * - A HivePress item (`hp:`) reaches it only while the WooCommerce
	 *   integration is merging the two menus, which is exactly the condition
	 *   alter_wc_menu() applies before it merges anything.
	 *
	 * @return array
	 */
	public function get_wc_menu_item_options() {
		if ( ! hp\is_plugin_active( 'woocommerce' ) ) {
			return [];
		}

		$catalogue = $this->get_menu_item_options();
		$merged    = $this->is_wc_integration_enabled();
		$options   = [];

		foreach ( $catalogue as $key => $label ) {
			if ( 0 === strpos( $key, 'wc:' ) || $merged ) {
				$options[ $key ] = $label;
			}
		}

		/*
		 * Keep whatever is already stored selectable, for the same reason
		 * get_menu_item_options() does: a saved value that is not among the
		 * options is sanitised away on the next save of this tab, so switching
		 * the integration off would quietly discard the owner's HivePress
		 * choices here rather than suspending them. Re-offering a key the owner
		 * has already chosen is not the same as offering it fresh.
		 */
		foreach ( $this->get_wc_hidden_keys() as $key ) {
			if ( isset( $options[ $key ] ) ) {
				continue;
			}

			$options[ $key ] = isset( $catalogue[ $key ] )
				? $catalogue[ $key ]
				: ucwords( str_replace( [ 'hp:', 'wc:', '_', '-' ], [ '', '', ' ', ' ' ], $key ) );
		}

		return $options;
	}

	/**
	 * Gets the link options for the settings screen.
	 *
	 * @return array
	 */
	public function get_link_options() {
		$options = [];

		// Add the HivePress account routes.
		foreach ( $this->get_pinned_items() as $item ) {
			$route = $item['route'];

			// Skip the routes that do not exist or cannot be resolved.
			if ( ! hivepress()->router->get_route( $route ) || ! hivepress()->router->get_url( $route ) ) {
				continue;
			}

			$title = $this->get_route_title( $route );

			$options[ 'route:' . $route ] = $title ? $title : $item['label'];
		}

		// Add the profile routes, resolved per user at display time. Their
		// route titles are callables, so plugin labels are used instead.
		$profiles = [
			'user_view_page'   => esc_html__( 'My Profile', 'account-menu-enhancer-for-hivepress' ),
			'vendor_view_page' => esc_html__( 'My Vendor Profile', 'account-menu-enhancer-for-hivepress' ),
		];

		foreach ( $profiles as $route => $label ) {
			if ( hivepress()->router->get_route( $route ) ) {
				$options[ 'route:' . $route ] = $label;
			}
		}

		asort( $options );

		// Add the WooCommerce endpoints.
		if ( hp\is_plugin_active( 'woocommerce' ) ) {
			foreach ( $this->get_base_wc_items() as $endpoint => $label ) {
				/* translators: %s: menu item label. */
				$options[ 'wc:' . $endpoint ] = sprintf( esc_html__( '%s (WooCommerce)', 'account-menu-enhancer-for-hivepress' ), $label );
			}
		}

		// Add the pages.
		$pages = get_pages(
			[
				'number' => 100,
			]
		);

		if ( is_array( $pages ) ) {
			foreach ( $pages as $page ) {
				/* translators: %s: page title. */
				$options[ 'page:' . $page->ID ] = sprintf( esc_html__( '%s (Page)', 'account-menu-enhancer-for-hivepress' ), $page->post_title );
			}
		}

		// Keep the previously saved links selectable, so that the saved
		// custom items still validate after their link target disappears.
		foreach ( $this->get_custom_items() as $item ) {
			$link = $item['link'];

			if ( '' !== $link && ! isset( $options[ $link ] ) ) {
				$options[ $link ] = ucwords( trim( str_replace( [ 'route:', 'wc:', 'page:', '_', '-' ], [ '', '', 'Page ', ' ', ' ' ], $link ) ) );
			}
		}

		return $options;
	}

	/**
	 * Gets the user role options for the settings screen.
	 *
	 * @return array
	 */
	public function get_role_options() {
		return array_map( 'translate_user_role', wp_roles()->get_names() );
	}

	/**
	 * Gets the icon options for the settings screen.
	 *
	 * Every icon in the bundled library, which is all of Font Awesome Free - brands and the version
	 * 6 and 7 additions included - in place of the 1,465-name list this plugin used to keep by hand.
	 *
	 * This list is the fallback. The pickers load their options over AJAX (see the icon field in
	 * configs/settings.php), because printing two thousand options into nine controls made the
	 * settings form megabytes of HTML; core still resolves this preset and throws the result away,
	 * which is wasteful but is core's own behaviour rather than something this plugin can avoid.
	 * Without the library the list is core's own Font Awesome 5 solid set, printed inline, because
	 * there is then no source to search.
	 *
	 * Labels are the raw icon slugs, matching core's list (configs/icons.php maps slug to slug) and
	 * the sibling plugins that extend it, so the dropdown reads consistently and matches the names
	 * on fontawesome.com; the dropdown previews show what each one looks like.
	 *
	 * @return array
	 */
	public function get_icon_options() {
		if ( class_exists( 'FAFH' ) ) {
			return \FAFH::choices();
		}

		$options = (array) hivepress()->get_config( 'icons' );

		ksort( $options );

		return $options;
	}
}
