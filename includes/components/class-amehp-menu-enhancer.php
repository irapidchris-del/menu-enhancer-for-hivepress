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
	 * URLs of the items injected into the WooCommerce menu.
	 *
	 * @var array
	 */
	protected $wc_urls = [];

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

			// Enqueue the front-end assets.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ], 20 );
		} else {

			// Enqueue the settings screen assets.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_assets' ] );
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
	 * Checks if the account layout unification is enabled.
	 *
	 * @return bool
	 */
	protected function is_unify_enabled() {
		return (bool) get_option( 'hp_amehp_unify_account', true );
	}

	/**
	 * Checks if the menu merging is enabled.
	 *
	 * @return bool
	 */
	protected function is_merge_enabled() {
		return (bool) get_option( 'hp_amehp_merge_menus', true );
	}

	/**
	 * Checks if the WooCommerce menu counters are enabled.
	 *
	 * @return bool
	 */
	protected function is_badges_enabled() {
		return $this->is_merge_enabled() && (bool) get_option( 'hp_amehp_wc_badges', true );
	}

	/**
	 * Gets the hidden item keys.
	 *
	 * @return array
	 */
	protected function get_hidden_keys() {
		$keys = get_option( 'hp_amehp_hidden_items' );

		return is_array( $keys ) ? $keys : [];
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

				$items[ 'amehp_item_' . ( $index + 1 ) ] = [
					'label'       => (string) $row['label'],
					'link'        => isset( $row['link'] ) ? (string) $row['link'] : '',
					'url'         => isset( $row['url'] ) ? (string) $row['url'] : '',
					'icon'        => isset( $row['icon'] ) ? (string) $row['icon'] : '',
					'colour'      => isset( $row['colour'] ) && is_string( $row['colour'] ) ? $row['colour'] : '',
					'text_colour' => isset( $row['text_colour'] ) && is_string( $row['text_colour'] ) ? $row['text_colour'] : '',
					'menus'       => isset( $row['menus'] ) && in_array( $row['menus'], [ 'hivepress', 'woocommerce' ], true ) ? $row['menus'] : 'both',
					'order'       => isset( $row['order'] ) && is_numeric( $row['order'] ) ? (int) $row['order'] : 100,
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
	 * Gets the WooCommerce account menu items without the plugin additions.
	 *
	 * @return array
	 */
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

		// Remove the hidden items.
		foreach ( $hidden as $key ) {
			if ( 0 === strpos( $key, 'hp:' ) ) {
				unset( $menu['items'][ substr( $key, strlen( 'hp:' ) ) ] );
			} elseif ( 0 === strpos( $key, 'wc:' ) ) {
				$endpoint = substr( $key, strlen( 'wc:' ) );

				unset( $menu['items'][ $endpoint ] );

				// Remove the items added by HivePress core.
				if ( 'orders' === $endpoint ) {
					unset( $menu['items']['orders_view'] );
				} elseif ( 'subscriptions' === $endpoint ) {
					unset( $menu['items']['subscriptions_view'] );
				}
			}
		}

		// Add the WooCommerce items.
		if ( $this->is_merge_enabled() && function_exists( 'wc_get_account_endpoint_url' ) ) {

			// Get the existing item URLs.
			$urls = [];

			foreach ( $menu['items'] as $item ) {
				$url = $this->get_item_url( $item );

				if ( $url ) {
					$urls[] = untrailingslashit( $url );
				}
			}

			$order = 60;

			foreach ( $this->get_base_wc_items() as $endpoint => $label ) {

				// Skip the endpoints managed by HivePress core, the sign-out
				// duplicate and the hidden items.
				if ( in_array( $endpoint, [ 'orders', 'subscriptions', 'customer-logout' ], true ) || in_array( 'wc:' . $endpoint, $hidden, true ) ) {
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

		$hidden = $this->get_hidden_keys();
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
		if ( $this->is_merge_enabled() ) {
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
				if ( ( 'orders_view' === $name && ( isset( $rows['orders'] ) || in_array( 'wc:orders', $hidden, true ) ) ) || ( 'subscriptions_view' === $name && ( isset( $rows['subscriptions'] ) || in_array( 'wc:subscriptions', $hidden, true ) ) ) ) {
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

		// Sort and flatten the items.
		return array_map(
			function ( $row ) {
				return $row['label'];
			},
			hp\sort_array( $rows )
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
		if ( ! $this->is_unify_enabled() || ! is_user_logged_in() ) {
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
		$css    = $this->get_icon_css() . $this->get_text_colour_css() . $this->get_appearance_css();
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
	 * Enqueues the settings screen assets.
	 *
	 * @param string $hook Page hook suffix.
	 */
	public function enqueue_backend_assets( $hook ) {

		// Reading the tab decides which assets to enqueue and changes nothing,
		// which is how HivePress itself reads the settings screen query args.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_hp_settings' !== $hook || 'account_menu' !== hp\get_array_value( $_GET, 'tab' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

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
	}

	/**
	 * Sanitizes a hex colour value.
	 *
	 * @param string $colour Colour value.
	 * @return string
	 */
	protected function sanitize_colour( $colour ) {
		return is_string( $colour ) && preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $colour ) ? $colour : '';
	}

	/**
	 * Gets the CSS selectors for a menu item key.
	 *
	 * @param string $key Item key.
	 * @return array
	 */
	protected function get_item_selectors( $key ) {
		$names = [];

		if ( 0 === strpos( $key, 'hp:' ) ) {
			$names[] = substr( $key, strlen( 'hp:' ) );
		} elseif ( 0 === strpos( $key, 'wc:' ) ) {
			$endpoint = substr( $key, strlen( 'wc:' ) );

			$names[] = $endpoint;

			// Cover the item keys used by HivePress core.
			if ( 'orders' === $endpoint ) {
				$names[] = 'orders_view';
			} elseif ( 'subscriptions' === $endpoint ) {
				$names[] = 'subscriptions_view';
			}
		} else {
			$names[] = $key;
		}

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
				];
			}
		}

		if ( ! $rules ) {
			return '';
		}

		// Get the icon codepoints.
		$codes = hivepress()->get_config( 'amehp_icon_codes' );

		$css       = '';
		$selectors = [];

		foreach ( $rules as $key => $rule ) {
			$code = hp\get_array_value( $codes, $rule['icon'] );

			if ( ! $code ) {
				continue;
			}

			$item_selectors = $this->get_item_selectors( (string) $key );

			if ( ! $item_selectors ) {
				continue;
			}

			$selectors = array_merge( $selectors, $item_selectors );

			// Add the icon rule.
			$css .= implode( '::before,', $item_selectors ) . '::before{content:"\\' . $code . '";}';

			// Add the colour rule.
			$colour = $this->sanitize_colour( $rule['colour'] );

			if ( $colour ) {
				$css .= implode( ',', $item_selectors ) . '{--amehp-icon-colour:' . $colour . ';}';
			}
		}

		if ( ! $css ) {
			return '';
		}

		// Add the base rule.
		$base = implode( '::before,', $selectors ) . '::before{font-family:"Font Awesome 7 Free","Font Awesome 6 Free","Font Awesome 5 Free";font-weight:900;font-style:normal;font-variant:normal;display:inline-block;width:1.25em;margin-inline-end:' . $this->get_icon_spacing() . ';text-align:center;line-height:1;text-rendering:auto;-webkit-font-smoothing:antialiased;color:var(--amehp-icon-colour,currentColor);}';

		// Keep the icon next to its label, and the counter on the right, when
		// the theme lays the menu link out as a flex row (some themes do this
		// to push the counter to the edge, which would otherwise fling the
		// icon away from its label). Both rules are ignored where the link is
		// not a flex container.
		$base .= implode( ',', $selectors ) . '{justify-content:flex-start;}';
		$base .= implode( '>small,', $selectors ) . '>small{margin-inline-start:auto;}';

		// Add the default colour.
		$default_colour = $this->sanitize_colour( (string) get_option( 'hp_amehp_icon_colour' ) );

		if ( $default_colour ) {
			$base .= ':root{--amehp-icon-colour:' . $default_colour . ';}';
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
			$css .= '.widget_nav_menu .hp-menu__item::before,.woocommerce-MyAccount-navigation ul li::before{display:none;}';
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

		return $items;
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
		$seen    = $this->get_seen_items();
		$changed = false;

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

			if ( isset( $seen[ $name ] ) && $seen[ $name ]['label'] === $label && $seen[ $name ]['route'] === $route ) {
				continue;
			}

			$seen[ $name ] = [
				'label' => $label,
				'route' => $route,
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
		}
	}

	/**
	 * Gets the recorded account menu items.
	 *
	 * @return array<string, array{label: string, route: string}>
	 */
	protected function get_seen_items() {
		$seen = get_option( 'hp_amehp_seen_items' );

		if ( ! is_array( $seen ) ) {
			return [];
		}

		$items = [];

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

			$items[ $name ] = [
				'label' => $label,
				'route' => $route,
			];
		}

		return $items;
	}

	/**
	 * Gets the menu item options for the settings screen.
	 *
	 * @return array
	 */
	public function get_menu_item_options() {
		$options = [];

		// Add the live HivePress menu items.
		foreach ( $this->get_base_hp_items() as $name => $item ) {

			// Skip the items added by HivePress core for WooCommerce, these
			// are listed under their WooCommerce names below.
			if ( in_array( $name, [ 'orders_view', 'subscriptions_view' ], true ) ) {
				continue;
			}

			$label = isset( $item['label'] ) ? wp_strip_all_tags( (string) $item['label'] ) : '';

			if ( $label ) {
				$options[ 'hp:' . $name ] = $label;
			}
		}

		/*
		 * Add the items seen on the front end that the admin-built menu could not show.
		 *
		 * Only items that came from a route. The account menu also carries the WooCommerce endpoints
		 * that HivePress merges into it, and those have no route of their own; they are already
		 * offered below under their WooCommerce names, so admitting them here would list Downloads,
		 * Addresses and Account details twice on the settings screen.
		 */
		foreach ( $this->get_seen_items() as $name => $item ) {
			if ( ! $item['route'] || isset( $options[ 'hp:' . $name ] ) ) {
				continue;
			}

			$options[ 'hp:' . $name ] = $item['label'];
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
}
