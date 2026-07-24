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
final class Account_Menu_Enhancer extends Component {

	/**
	 * Suppresses the plugin menu filters while fetching the base menus.
	 *
	 * @var bool
	 */
	protected $suppressed = false;

	/**
	 * Base HivePress menu items cache.
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
					'label'  => (string) $row['label'],
					'link'   => isset( $row['link'] ) ? (string) $row['link'] : '',
					'url'    => isset( $row['url'] ) ? (string) $row['url'] : '',
					'icon'   => isset( $row['icon'] ) ? (string) $row['icon'] : '',
					'colour' => isset( $row['colour'] ) && is_string( $row['colour'] ) ? $row['colour'] : '',
					'menus'  => isset( $row['menus'] ) && in_array( $row['menus'], [ 'hivepress', 'woocommerce' ], true ) ? $row['menus'] : 'both',
					'order'  => isset( $row['order'] ) && is_numeric( $row['order'] ) ? (int) $row['order'] : 100,
					'roles'  => isset( $row['roles'] ) && is_array( $row['roles'] ) ? $row['roles'] : [],
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
	 * @return array
	 */
	protected function get_base_hp_items() {
		if ( ! isset( $this->hp_items ) ) {
			$this->hp_items = [];

			if ( class_exists( '\HivePress\Menus\User_Account' ) ) {
				$this->suppressed = true;

				try {
					$this->hp_items = ( new \HivePress\Menus\User_Account() )->get_items();
				} catch ( \Throwable $throwable ) {

					// Third-party menu items can resolve their labels via
					// route title callbacks that expect the front-end query
					// context, so fall back to an empty base menu if the
					// menu cannot be built in the current context.
					$this->hp_items = [];
				}

				$this->suppressed = false;
			}
		}

		return $this->hp_items;
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

			$order++;
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

				// Skip the HivePress items that mirror hidden WooCommerce
				// endpoints, since their endpoint rows are already removed
				// before the duplicate URL check can catch them.
				if ( ( 'orders_view' === $name && in_array( 'wc:orders', $hidden, true ) ) || ( 'subscriptions_view' === $name && in_array( 'wc:subscriptions', $hidden, true ) ) ) {
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

			// Set the page title. The argument count matches the current
			// WooCommerce registration so that re-adding the filter keeps
			// the queried-object guard of newer WooCommerce versions.
			if ( 'dashboard' !== $this->get_current_endpoint_key() ) {
				add_filter( 'the_title', 'wc_page_endpoint_title', 10, 2 );
			}

			// Alter the page template.
			$template = hp\merge_trees(
				$template,
				[
					'blocks' => [
						'page_container' => [
							'type' => 'container',
						],

						'page_content'   => [
							'blocks' => [
								'amehp_woocommerce_content' => [
									'type'     => 'callback',
									'callback' => 'do_action',
									'params'   => [ 'woocommerce_account_content' ],
									'_order'   => 10,
								],
							],
						],
					],
				]
			);
		}

		return $template;
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
	 * Enqueues the front-end assets.
	 */
	public function enqueue_frontend_assets() {
		$css    = $this->get_icon_css() . $this->get_appearance_css();
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
			AMEHP_VERSION
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
				AMEHP_VERSION,
				true
			);

			wp_localize_script(
				'amehp-frontend',
				'amehpFrontendData',
				[
					'badges' => $badges,
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
		if ( 'toplevel_page_hp_settings' !== $hook || 'account_menu' !== hp\get_array_value( $_GET, 'tab' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'amehp-backend',
			plugins_url( 'assets/css/backend.css', AMEHP_FILE ),
			[],
			AMEHP_VERSION
		);

		wp_enqueue_script(
			'amehp-backend',
			plugins_url( 'assets/js/backend.js', AMEHP_FILE ),
			[ 'jquery', 'wp-color-picker', 'hivepress-core' ],
			AMEHP_VERSION,
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
		$codes = hivepress()->get_config( 'icon_codes' );

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
		$base = implode( '::before,', $selectors ) . '::before{font-family:"Font Awesome 7 Free","Font Awesome 6 Free","Font Awesome 5 Free";font-weight:900;font-style:normal;font-variant:normal;display:inline-block;width:1.25em;margin-inline-end:0.5em;text-align:center;line-height:1;text-rendering:auto;-webkit-font-smoothing:antialiased;color:var(--amehp-icon-colour,currentColor);}';

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

		// Set the menu item font weight.
		$weight = (string) get_option( 'hp_amehp_menu_weight' );

		if ( preg_match( '/^[1-9]00$/', $weight ) ) {
			$css .= '.hp-menu--user-account .hp-menu__item > a,.woocommerce-MyAccount-navigation ul li > a{font-weight:' . $weight . ';}';
		}

		// Hide the theme navigation chevrons on the account menus only. The
		// selectors are specific enough to override the theme rule, and they
		// are scoped so that other navigation menus (for example in the
		// footer) keep their markers.
		if ( get_option( 'hp_amehp_hide_chevrons' ) ) {
			$css .= '.hp-menu--user-account .hp-menu__item::before,.woocommerce-MyAccount-navigation ul li::before{display:none;}';
		}

		// Hide the WooCommerce account page header.
		if ( hp\is_plugin_active( 'woocommerce' ) && get_option( 'hp_amehp_hide_wc_header' ) ) {
			$css .= 'body.woocommerce-account .header-hero--title{display:none;}';
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

			$options = array_merge( $options, $wc_options );
		}

		// Keep the previously saved keys selectable.
		$saved = array_merge( $this->get_hidden_keys(), array_keys( $this->get_icon_rules() ) );

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
