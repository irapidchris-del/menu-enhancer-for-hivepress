<?php
/**
 * Shared WordPress/HivePress stubs for the Account Menu Enhancer logic tests.
 *
 * Modelled on the Holiday Mode harness, with one deliberate difference: the
 * stubs live in their own file instead of being repeated in each test file.
 * Holiday Mode has two test files that stub different, small surfaces; this
 * plugin has two components, a settings screen and three migrations that all
 * need the SAME two hundred lines of WordPress. Two copies of that would drift,
 * and a stub that drifts makes one file green while the other is red for a
 * reason nobody can see. Everything is still inside tests/ and still needs
 * nothing but PHP.
 *
 * Nothing here is asserted on. These are inputs: the real plugin code runs
 * against them and the assertions live in the test files.
 *
 * Env: AMEHP_WC=absent          -> WooCommerce is not installed at all
 *      AMEHP_PAM=1              -> Persistent Account Menu is still active
 *      AMEHP_BOOKINGS=absent    -> HivePress Bookings is not installed
 *      AMEHP_MEMBERSHIPS=absent -> HivePress Memberships is not installed
 *
 * @package AccountMenuEnhancer\Tests
 */

namespace HivePress\Helpers {

	/**
	 * Mirrors hivepress/includes/helpers.php:67.
	 *
	 * @param array  $array Source array.
	 * @param string $key Key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_array_value( $array, $key, $default = null ) {
		return is_array( $array ) && isset( $array[ $key ] ) ? $array[ $key ] : $default;
	}

	/**
	 * Mirrors hivepress/includes/helpers.php:85.
	 *
	 * @param array $array Source array.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	function get_first_array_value( $array, $default = null ) {
		return is_array( $array ) && $array ? reset( $array ) : $default;
	}

	/**
	 * Mirrors hivepress/includes/helpers.php:218. The "_order" default of 0 for
	 * an item that carries none matters: it is what puts an unordered row at the
	 * top rather than at the end.
	 *
	 * @param array $array Source array.
	 * @return array
	 */
	function sort_array( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['order'] ) && is_int( $value['order'] ) ) {
					$array[ $key ]['_order'] = $value['order'];
				} elseif ( ! isset( $value['_order'] ) ) {
					$array[ $key ]['_order'] = 0;
				}
			}
		}

		return \wp_list_sort( $array, '_order', 'ASC', true );
	}

	/**
	 * Mirrors hivepress/includes/helpers.php:472.
	 *
	 * @param string $text Text to sanitize.
	 * @return string
	 */
	function sanitize_key( $text ) {
		$key = strtolower( (string) $text );
		$key = preg_replace( '/[^a-z0-9]+/', '_', $key );

		return ltrim( trim( $key, '_' ), '0..9' );
	}

	/**
	 * Mirrors hivepress/includes/helpers.php:462.
	 *
	 * @param string $text Text to sanitize.
	 * @return string
	 */
	function sanitize_slug( $text ) {
		return str_replace( '_', '-', sanitize_key( $text ) );
	}

	/**
	 * Mirrors hivepress/includes/helpers.php:512.
	 *
	 * @param string $name Class or function name.
	 * @return bool
	 */
	function is_plugin_active( $name ) {
		return class_exists( $name ) || function_exists( $name );
	}

	/**
	 * Only reachable on a core older than the template component, which the
	 * stub below always provides, so this is here for completeness.
	 *
	 * @param array $parent_tree Parent tree.
	 * @param array $child_tree Child tree.
	 * @return array
	 */
	function merge_trees( $parent_tree, $child_tree ) {
		return array_replace_recursive( $parent_tree, $child_tree );
	}
}

namespace HivePress\Traits {

	/**
	 * Mirrors hivepress/includes/traits/class-mutator.php.
	 */
	trait Mutator {

		/**
		 * Sets a property value.
		 *
		 * @param string $name Property name.
		 * @param mixed  $value Property value.
		 * @param string $prefix Method prefix.
		 */
		final protected function set_property( $name, $value, $prefix = '' ) {
			$method = $prefix . 'set_' . $name;

			if ( method_exists( $this, $method ) ) {
				call_user_func( [ $this, $method ], $value );
			} elseif ( property_exists( $this, $name ) ) {
				$this->$name = $value;
			}
		}
	}
}

namespace HivePress\Components {

	/**
	 * Mirrors hivepress/includes/components/class-component.php.
	 */
	abstract class Component {
		use \HivePress\Traits\Mutator;

		/**
		 * Class constructor.
		 *
		 * @param array $args Component arguments.
		 */
		public function __construct( $args = [] ) {
			foreach ( $args as $name => $value ) {
				$this->set_property( $name, $value );
			}

			$this->boot();
		}

		/**
		 * Bootstraps component properties.
		 */
		protected function boot() {}

		/**
		 * Sets the action and filter callbacks.
		 *
		 * @param array $callbacks Callback arguments.
		 */
		final protected function set_callbacks( $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$type = \HivePress\Helpers\get_array_value( $callback, 'filter' ) ? 'filter' : 'action';

				call_user_func_array(
					'add_' . $type,
					[
						$callback['hook'],
						$callback['action'],
						\HivePress\Helpers\get_array_value( $callback, '_order', 10 ),
						\HivePress\Helpers\get_array_value( $callback, 'args', 1 ),
					]
				);
			}
		}
	}
}

namespace HivePress\Menus {

	/**
	 * The account menu as the site would build it, before this plugin touches
	 * it. The tests set $GLOBALS['_hp_menu_items'] to whatever the scenario
	 * needs, so this is the harness's input rather than an assertion target.
	 */
	class User_Account {

		/**
		 * Gets the menu items.
		 *
		 * @return array
		 */
		public function get_items() {
			return $GLOBALS['_hp_menu_items'];
		}
	}
}

namespace HivePress\Models {

	// The vendor lookup behind the vendor-only placeholder pages and the
	// "My Vendor Profile" custom link.
	class Vendor {

		/**
		 * Starts a query.
		 *
		 * @return self
		 */
		public static function query() {
			return new self();
		}

		/**
		 * Filters the query.
		 *
		 * @param array $args Filter arguments.
		 * @return self
		 */
		public function filter( $args ) {
			return $this;
		}

		/**
		 * Gets the first matching id.
		 *
		 * @return int
		 */
		public function get_first_id() {
			return (int) $GLOBALS['_vendor_first_id'];
		}
	}
}

namespace HivePress\Blocks {

	/**
	 * Stands in for the page-title template part rendered by the WooCommerce
	 * unification path.
	 */
	class Part {

		/**
		 * Block arguments.
		 *
		 * @var array
		 */
		protected $args;

		/**
		 * Class constructor.
		 *
		 * @param array $args Block arguments.
		 */
		public function __construct( $args = [] ) {
			$this->args = $args;
		}

		/**
		 * Renders the block.
		 *
		 * @return string
		 */
		public function render() {
			return '<h1>' . \HivePress\Helpers\get_array_value( \HivePress\Helpers\get_array_value( $this->args, 'context', [] ), 'page_title', '' ) . '</h1>';
		}
	}
}

namespace PersistentAccountMenu {

	// Simulates the old Persistent Account Menu plugin still being active, which
	// is what amehp_take_over_persistent_menu() and the legacy option fallbacks
	// exist for. Declared only in that run, because the takeover is gated on
	// function_exists() for exactly this function.
	if ( '1' === getenv( 'AMEHP_PAM' ) ) {

		/**
		 * The old plugin's own item list. Only its existence is read.
		 *
		 * @return array
		 */
		function get_items() {
			return [];
		}

		/**
		 * The old plugin's settings filter.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		function alter_settings( $settings ) {
			return $settings;
		}

		/**
		 * The old plugin's routes filter.
		 *
		 * @param array $routes Routes.
		 * @return array
		 */
		function alter_routes( $routes ) {
			return $routes;
		}

		/**
		 * The old plugin's menu filter.
		 *
		 * @param array $menu Menu.
		 * @return array
		 */
		function alter_account_menu( $menu ) {
			return $menu;
		}

		/**
		 * The old plugin's template filter.
		 *
		 * @param array $template Template.
		 * @return array
		 */
		function alter_account_page( $template ) {
			return $template;
		}

		/**
		 * The old plugin's style enqueue.
		 */
		function enqueue_styles() {}

		/**
		 * The old plugin's reconciliation.
		 */
		function reconcile_items() {}
	}
}

namespace {

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DAY_IN_SECONDS', 86400 );

	define( 'AMEHP_TEST_WC', 'absent' !== getenv( 'AMEHP_WC' ) );
	define( 'AMEHP_TEST_PAM', '1' === getenv( 'AMEHP_PAM' ) );
	define( 'AMEHP_TEST_BOOKINGS', 'absent' !== getenv( 'AMEHP_BOOKINGS' ) );
	define( 'AMEHP_TEST_MEMBERSHIPS', 'absent' !== getenv( 'AMEHP_MEMBERSHIPS' ) );

	define( 'AMEHP_TEST_PLUGIN_DIR', dirname( __DIR__ ) );
	define( 'AMEHP_TEST_PLUGIN_FILE', AMEHP_TEST_PLUGIN_DIR . '/account-menu-enhancer-for-hivepress.php' );

	/**
	 * The routes a site has, which is how "extension absent" is expressed: the
	 * plugin decides whether an extension is installed by asking the router for
	 * its page route, so removing the route IS removing the extension.
	 *
	 * @return array
	 */
	function amehp_test_default_routes() {
		$routes = [
			'user_account_page'          => [ 'title' => 'Account' ],
			'user_edit_settings_page'    => [ 'title' => 'Settings' ],
			'user_logout_page'           => [ 'title' => 'Sign Out' ],
			'user_view_page'             => [ 'title' => 'Profile' ],
			'listings_edit_page'         => [ 'title' => 'Listings' ],
			'listings_view_page'         => [ 'title' => 'Browse' ],
			'listings_favorite_page'     => [ 'title' => 'Favorites' ],
			'listing_submit_page'        => [ 'title' => 'Add Listing' ],
			'messages_thread_page'       => [ 'title' => 'Messages' ],
			'search_alerts_view_page'    => [ 'title' => 'Searches' ],
			'requests_edit_page'         => [ 'title' => 'Requests' ],
			'request_submit_page'        => [ 'title' => 'Post a Request' ],
			'requests_view_page'         => [ 'title' => 'Requests' ],
			'offers_view_page'           => [ 'title' => 'Offers' ],
			'vendor_view_page'           => [ 'title' => 'Vendor' ],
			'vendor_dashboard_page'      => [ 'title' => 'Dashboard' ],
			'orders_edit_page'           => [ 'title' => 'Orders' ],
			'payouts_view_page'          => [ 'title' => 'Payouts' ],
		];

		if ( AMEHP_TEST_BOOKINGS ) {
			$routes['bookings_view_page']   = [ 'title' => 'Bookings' ];
			$routes['vendor_calendar_page'] = [ 'title' => 'Calendar' ];
		}

		if ( AMEHP_TEST_MEMBERSHIPS ) {
			$routes['memberships_view_page']    = [ 'title' => 'Memberships' ];
			$routes['membership_plans_view_page'] = [ 'title' => 'Plans' ];
		}

		return $routes;
	}

	/**
	 * Clears every piece of simulated site state between assertions.
	 */
	function amehp_test_reset_globals() {
		$GLOBALS['_options']         = [];
		$GLOBALS['_site_transients'] = [];
		$GLOBALS['_filters']         = [];
		$GLOBALS['_actions']         = [];
		$GLOBALS['_removed']         = [];
		$GLOBALS['_styles_reg']      = [];
		$GLOBALS['_styles_enq']      = [];
		$GLOBALS['_inline_styles']   = [];
		$GLOBALS['_scripts_enq']     = [];
		$GLOBALS['_localized']       = [];
		$GLOBALS['_posts']           = [];
		$GLOBALS['_pages']           = [];
		$GLOBALS['_routes']          = amehp_test_default_routes();
		$GLOBALS['_current_route']   = '';
		$GLOBALS['_current_user_id'] = 0;
		$GLOBALS['_user_roles']      = [];
		$GLOBALS['_user_login']      = '';
		$GLOBALS['_can']             = [ 'manage_options' => true ];
		$GLOBALS['_is_admin']        = false;
		$GLOBALS['_vendor_first_id'] = 0;
		$GLOBALS['_hp_menu_items']   = [];
		$GLOBALS['_wc_menu_items']   = [];
		$GLOBALS['_wc_query_vars']   = [];
		$GLOBALS['_wc_endpoint']     = '';
		$GLOBALS['_is_account_page'] = false;
		$GLOBALS['_theme_mods']      = [];
		$GLOBALS['_doing_filter']    = [];
	}

	amehp_test_reset_globals();

	/*
	-------------------------------------------------------------------------
	WordPress
	-------------------------------------------------------------------------
	*/

	/**
	 * Translates a string.
	 *
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = null ) {
		return $text;
	}

	/**
	 * Translates and escapes a string.
	 *
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = null ) {
		return $text;
	}

	/**
	 * Translates and escapes an attribute.
	 *
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = null ) {
		return $text;
	}

	/**
	 * Escapes HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}

	/**
	 * Escapes an attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}

	/**
	 * Escapes a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}

	/**
	 * Escapes a URL for storage.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}

	/**
	 * Strips tags.
	 *
	 * @param string $text Text.
	 * @param bool   $remove_breaks Remove breaks.
	 * @return string
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return trim( strip_tags( (string) $text ) );
	}

	/**
	 * Sanitises a text field.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}

	/**
	 * Sanitises a key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}

	/**
	 * Sanitises an HTML class.
	 *
	 * @param string $class Class name.
	 * @return string
	 */
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}

	/**
	 * Casts to a non-negative integer.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}

	/**
	 * Wraps text in paragraphs.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wpautop( $text ) {
		return '<p>' . $text . '</p>';
	}

	/**
	 * Parses a URL.
	 *
	 * @param string $url URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}

	/**
	 * Adds a trailing slash.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}

	/**
	 * Removes a trailing slash.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}

	/**
	 * Gets a site URL.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'http://example.test' . $path;
	}

	/**
	 * Gets an admin URL.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . $path;
	}

	/**
	 * Gets an admin URL.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function self_admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . $path;
	}

	/**
	 * Gets a plugin URL.
	 *
	 * @param string $path Path.
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugins_url( $path = '', $file = '' ) {
		return 'http://example.test/wp-content/plugins/account-menu-enhancer-for-hivepress/' . ltrim( $path, '/' );
	}

	/**
	 * Gets a plugin directory URL.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_url( $file ) {
		return 'http://example.test/wp-content/plugins/account-menu-enhancer-for-hivepress/';
	}

	/**
	 * Gets a plugin basename.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_basename( $file ) {
		return 'account-menu-enhancer-for-hivepress/account-menu-enhancer-for-hivepress.php';
	}

	/**
	 * Whether the request is an admin one.
	 *
	 * @return bool
	 */
	function is_admin() {
		return (bool) $GLOBALS['_is_admin'];
	}

	/**
	 * Reads an option. Returns the default only when the row is absent, which is
	 * what the plugin's `get_option( $name, null )` reads depend on.
	 *
	 * @param string $name Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['_options'] ) ? $GLOBALS['_options'][ $name ] : $default;
	}

	/**
	 * Writes an option.
	 *
	 * @param string $name Option name.
	 * @param mixed  $value Value.
	 * @param mixed  $autoload Autoload flag.
	 * @return bool
	 */
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['_option_writes'][] = $name;

		$GLOBALS['_options'][ $name ] = $value;

		return true;
	}

	/**
	 * Deletes an option.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['_options'][ $name ] );

		return true;
	}

	/**
	 * Reads a site transient.
	 *
	 * @param string $name Transient name.
	 * @return mixed
	 */
	function get_site_transient( $name ) {
		return array_key_exists( $name, $GLOBALS['_site_transients'] ) ? $GLOBALS['_site_transients'][ $name ] : false;
	}

	/**
	 * Writes a site transient.
	 *
	 * @param string $name Transient name.
	 * @param mixed  $value Value.
	 * @param int    $expiry Expiry.
	 * @return bool
	 */
	function set_site_transient( $name, $value, $expiry = 0 ) {
		$GLOBALS['_site_transients'][ $name ] = $value;

		return true;
	}

	/**
	 * Deletes a site transient.
	 *
	 * @param string $name Transient name.
	 * @return bool
	 */
	function delete_site_transient( $name ) {
		unset( $GLOBALS['_site_transients'][ $name ] );

		return true;
	}

	/**
	 * Registers a filter.
	 *
	 * @param string   $tag Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args Accepted arguments.
	 * @return bool
	 */
	function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['_filters'][ $tag ][] = [
			'cb'   => $callback,
			'prio' => $priority,
			'args' => $args,
		];

		return true;
	}

	/**
	 * Registers an action.
	 *
	 * @param string   $tag Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args Accepted arguments.
	 * @return bool
	 */
	function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $tag, $callback, $priority, $args );
	}

	/**
	 * Removes a filter, matching by callback identity the way core does.
	 *
	 * @param string   $tag Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return bool
	 */
	function remove_filter( $tag, $callback, $priority = 10 ) {
		$GLOBALS['_removed'][] = [ $tag, $callback ];

		foreach ( $GLOBALS['_filters'][ $tag ] ?? [] as $index => $hook ) {
			if ( $hook['cb'] === $callback ) {
				unset( $GLOBALS['_filters'][ $tag ][ $index ] );
			}
		}

		return true;
	}

	/**
	 * Removes an action.
	 *
	 * @param string   $tag Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return bool
	 */
	function remove_action( $tag, $callback, $priority = 10 ) {
		return remove_filter( $tag, $callback, $priority );
	}

	/**
	 * Applies a filter.
	 *
	 * @param string $tag Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		$extra = array_slice( func_get_args(), 2 );

		if ( empty( $GLOBALS['_filters'][ $tag ] ) ) {
			return $value;
		}

		$hooks = $GLOBALS['_filters'][ $tag ];

		usort(
			$hooks,
			function ( $a, $b ) {
				return $a['prio'] <=> $b['prio'];
			}
		);

		$GLOBALS['_doing_filter'][] = $tag;

		foreach ( $hooks as $hook ) {
			$argv  = array_slice( array_merge( [ $value ], $extra ), 0, max( 1, (int) $hook['args'] ) );
			$value = call_user_func_array( $hook['cb'], $argv );
		}

		array_pop( $GLOBALS['_doing_filter'] );

		return $value;
	}

	/**
	 * Fires an action.
	 *
	 * @param string $tag Hook name.
	 */
	function do_action( $tag ) {
		$GLOBALS['_actions'][] = $tag;
	}

	/**
	 * Whether a hook is currently running.
	 *
	 * @param string $tag Hook name.
	 * @return bool
	 */
	function doing_filter( $tag = null ) {
		return null === $tag ? (bool) $GLOBALS['_doing_filter'] : in_array( $tag, $GLOBALS['_doing_filter'], true );
	}

	/**
	 * Gets the current user id.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return (int) $GLOBALS['_current_user_id'];
	}

	/**
	 * Whether a user is signed in.
	 *
	 * @return bool
	 */
	function is_user_logged_in() {
		return $GLOBALS['_current_user_id'] > 0;
	}

	/**
	 * Whether the current user has a capability.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return ! empty( $GLOBALS['_can'][ $capability ] );
	}

	/**
	 * Gets the current user. WP_User exposes ->roles as a plain slug array and
	 * ->exists() as the signed-in test, which is all the plugin reads.
	 *
	 * @return object
	 */
	function wp_get_current_user() {
		return new class() {

			/**
			 * User roles.
			 *
			 * @var array
			 */
			public $roles;

			/**
			 * User login.
			 *
			 * @var string
			 */
			public $user_login;

			/**
			 * Class constructor.
			 */
			public function __construct() {
				$this->roles      = (array) $GLOBALS['_user_roles'];
				$this->user_login = (string) $GLOBALS['_user_login'];
			}

			/**
			 * Whether the user exists.
			 *
			 * @return bool
			 */
			public function exists() {
				return $GLOBALS['_current_user_id'] > 0;
			}
		};
	}

	/**
	 * Gets the role list.
	 *
	 * @return object
	 */
	function wp_roles() {
		return new class() {

			/**
			 * Gets the role names.
			 *
			 * @return array
			 */
			public function get_names() {
				return [
					'administrator' => 'Administrator',
					'subscriber'    => 'Subscriber',
					'hp_vendor'     => 'Vendor',
				];
			}
		};
	}

	/**
	 * Translates a role name.
	 *
	 * @param string $name Role name.
	 * @return string
	 */
	function translate_user_role( $name ) {
		return $name;
	}

	/**
	 * Gets a post.
	 *
	 * @param int $id Post id.
	 * @return object|null
	 */
	function get_post( $id ) {
		return isset( $GLOBALS['_posts'][ $id ] ) ? (object) $GLOBALS['_posts'][ $id ] : null;
	}

	/**
	 * Gets a post field.
	 *
	 * @param string $field Field name.
	 * @param int    $id Post id.
	 * @return string
	 */
	function get_post_field( $field, $id ) {
		return isset( $GLOBALS['_posts'][ $id ][ $field ] ) ? (string) $GLOBALS['_posts'][ $id ][ $field ] : '';
	}

	/**
	 * Gets a permalink.
	 *
	 * @param object|int $post Post.
	 * @return string
	 */
	function get_permalink( $post ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;

		return isset( $GLOBALS['_posts'][ $id ] ) ? 'http://example.test/?p=' . $id : '';
	}

	/**
	 * Gets pages.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	function get_pages( $args = [] ) {
		return $GLOBALS['_pages'];
	}

	/**
	 * Gets posts.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	function get_posts( $args = [] ) {
		return [];
	}

	/**
	 * Counts posts.
	 *
	 * @param string $type Post type.
	 * @return object
	 */
	function wp_count_posts( $type = 'post' ) {
		return (object) [ 'publish' => 0 ];
	}

	/**
	 * Gets a theme mod.
	 *
	 * @param string $name Mod name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_theme_mod( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['_theme_mods'] ) ? $GLOBALS['_theme_mods'][ $name ] : $default;
	}

	/**
	 * Sorts a list by a field, preserving keys. PHP 8 sorts are stable, which is
	 * what keeps two items sharing an "_order" in their original sequence.
	 *
	 * @param array  $list List.
	 * @param string $orderby Field.
	 * @param string $order Direction.
	 * @param bool   $preserve_keys Preserve keys.
	 * @return array
	 */
	function wp_list_sort( $list, $orderby = '', $order = 'ASC', $preserve_keys = false ) {
		$field = is_array( $orderby ) ? array_key_first( $orderby ) : $orderby;

		uasort(
			$list,
			function ( $a, $b ) use ( $field ) {
				$left  = is_array( $a ) && isset( $a[ $field ] ) ? $a[ $field ] : 0;
				$right = is_array( $b ) && isset( $b[ $field ] ) ? $b[ $field ] : 0;

				return $left <=> $right;
			}
		);

		return $preserve_keys ? $list : array_values( $list );
	}

	/**
	 * Registers a stylesheet.
	 *
	 * @param string $handle Handle.
	 * @param string $src Source.
	 * @param array  $deps Dependencies.
	 * @param mixed  $version Version.
	 * @return bool
	 */
	function wp_register_style( $handle, $src = '', $deps = [], $version = false ) {
		if ( ! isset( $GLOBALS['_styles_reg'][ $handle ] ) ) {
			$GLOBALS['_styles_reg'][ $handle ] = $src;
		}

		return true;
	}

	/**
	 * Enqueues a stylesheet.
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	function wp_enqueue_style( $handle, ...$args ) {
		$GLOBALS['_styles_enq'][] = $handle;

		return true;
	}

	/**
	 * Whether a stylesheet is registered or enqueued.
	 *
	 * @param string $handle Handle.
	 * @param string $list List name.
	 * @return bool
	 */
	function wp_style_is( $handle, $list = 'enqueued' ) {
		if ( 'registered' === $list ) {
			return isset( $GLOBALS['_styles_reg'][ $handle ] );
		}

		return in_array( $handle, $GLOBALS['_styles_enq'], true );
	}

	/**
	 * Adds inline CSS.
	 *
	 * @param string $handle Handle.
	 * @param string $css CSS.
	 * @return bool
	 */
	function wp_add_inline_style( $handle, $css ) {
		$GLOBALS['_inline_styles'][ $handle ] = ( $GLOBALS['_inline_styles'][ $handle ] ?? '' ) . $css;

		return true;
	}

	/**
	 * Enqueues a script.
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	function wp_enqueue_script( $handle, ...$args ) {
		$GLOBALS['_scripts_enq'][] = $handle;

		return true;
	}

	/**
	 * Localises a script.
	 *
	 * @param string $handle Handle.
	 * @param string $name Object name.
	 * @param array  $data Data.
	 * @return bool
	 */
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['_localized'][ $name ] = $data;

		return true;
	}

	/**
	 * Adds a settings section.
	 *
	 * @param string   $id Section id.
	 * @param string   $title Title.
	 * @param callable $callback Callback.
	 * @param string   $page Page.
	 */
	function add_settings_section( $id, $title, $callback, $page ) {
		$GLOBALS['wp_settings_sections'][ $page ][ $id ] = [
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
		];
	}

	/*
	 * The updater surface. None of it is exercised by these tests - the release
	 * lookup is Holiday Mode's updater-tests territory and is not what silently
	 * corrupts a menu - but the main plugin file has to load, and it registers
	 * these at file scope.
	 */

	/**
	 * Makes an HTTP request. Never reached: no test forces a release check.
	 *
	 * @param string $url URL.
	 * @param array  $args Arguments.
	 * @return array
	 */
	function wp_remote_get( $url, $args = [] ) {
		return [
			'response' => [ 'code' => 0 ],
			'body'     => '',
			'headers'  => [],
		];
	}

	/**
	 * Whether a value is an error.
	 *
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	/**
	 * Gets a response code.
	 *
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}

	/**
	 * Gets a response body.
	 *
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return (string) ( $response['body'] ?? '' );
	}

	/**
	 * Gets a response header.
	 *
	 * @param array  $response Response.
	 * @param string $name Header name.
	 * @return string
	 */
	function wp_remote_retrieve_header( $response, $name ) {
		return (string) ( $response['headers'][ $name ] ?? '' );
	}

	/**
	 * Whether an event is scheduled.
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	function wp_next_scheduled( $hook ) {
		return false;
	}

	/**
	 * Schedules an event.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $hook Hook name.
	 * @return bool
	 */
	function wp_schedule_single_event( $timestamp, $hook ) {
		return true;
	}

	/**
	 * Reads plugin headers.
	 *
	 * @param string $file File.
	 * @param array  $headers Headers.
	 * @return array
	 */
	function get_file_data( $file, $headers = [] ) {
		$contents = (string) file_get_contents( $file );
		$data     = [];

		foreach ( $headers as $field => $label ) {
			$data[ $field ] = preg_match( '~^[ \t/*#@]*' . preg_quote( $label, '~' ) . ':(.*)$~mi', $contents, $match ) ? trim( $match[1] ) : '';
		}

		return $data;
	}

	/**
	 * Adds a nonce to a URL.
	 *
	 * @param string $url URL.
	 * @param string $action Action.
	 * @return string
	 */
	function wp_nonce_url( $url, $action = -1 ) {
		return $url;
	}

	/**
	 * Verifies an admin referer.
	 *
	 * @param string $action Action.
	 * @return bool
	 */
	function check_admin_referer( $action = -1 ) {
		return true;
	}

	/**
	 * Redirects.
	 *
	 * @param string $location Location.
	 * @return bool
	 */
	function wp_safe_redirect( $location ) {
		return true;
	}

	/**
	 * Adds a query argument.
	 *
	 * @param string $key Key.
	 * @param string $value Value.
	 * @param string $url URL.
	 * @return string
	 */
	function add_query_arg( $key, $value = '', $url = '' ) {
		return $url . '?' . $key . '=' . $value;
	}

	/**
	 * Clears the plugin cache.
	 */
	function wp_clean_plugins_cache() {}

	/**
	 * Refreshes the plugin update list.
	 */
	function wp_update_plugins() {}

	/**
	 * Minimal WP_Error.
	 */
	class WP_Error {

		/**
		 * Class constructor.
		 *
		 * @param string $code Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {}
	}

	/*
	-------------------------------------------------------------------------
	WooCommerce
	-------------------------------------------------------------------------
	*/

	// hp\is_plugin_active( 'woocommerce' ) is class_exists()/function_exists(),
	// and class_exists() is case insensitive, so this class IS "WooCommerce is
	// installed" as far as the plugin can tell.
	if ( AMEHP_TEST_WC ) {

		/**
		 * Stands in for the WooCommerce main class.
		 */
		class WooCommerce {

			/**
			 * Query handler.
			 *
			 * @var object
			 */
			public $query;

			/**
			 * Class constructor.
			 */
			public function __construct() {
				$this->query = new class() {

					/**
					 * Gets the registered account query vars.
					 *
					 * @return array
					 */
					public function get_query_vars() {
						return $GLOBALS['_wc_query_vars'];
					}

					/**
					 * Gets the current endpoint.
					 *
					 * @return string
					 */
					public function get_current_endpoint() {
						return (string) $GLOBALS['_wc_endpoint'];
					}

					/**
					 * Gets an endpoint title.
					 *
					 * @param string $endpoint Endpoint.
					 * @return string
					 */
					public function get_endpoint_title( $endpoint ) {
						return ucwords( str_replace( '-', ' ', (string) $endpoint ) );
					}
				};
			}
		}

		/**
		 * Gets the WooCommerce instance.
		 *
		 * @return WooCommerce
		 */
		function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
			static $instance = null;

			if ( null === $instance ) {
				$instance = new WooCommerce();
			}

			return $instance;
		}

		/**
		 * Gets the account menu items.
		 *
		 * @return array
		 */
		function wc_get_account_menu_items() {
			return $GLOBALS['_wc_menu_items'];
		}

		/**
		 * Gets an account endpoint URL.
		 *
		 * @param string $endpoint Endpoint.
		 * @return string
		 */
		function wc_get_account_endpoint_url( $endpoint ) {
			return 'http://example.test/my-account/' . $endpoint;
		}

		/**
		 * Gets an endpoint URL.
		 *
		 * @param string $endpoint Endpoint.
		 * @param string $value Value.
		 * @param string $permalink Permalink.
		 * @return string
		 */
		function wc_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
			return 'http://example.test/my-account/' . $endpoint;
		}

		/**
		 * Gets a page permalink.
		 *
		 * @param string $page Page name.
		 * @return string
		 */
		function wc_get_page_permalink( $page ) {
			return 'http://example.test/my-account/';
		}

		/**
		 * Whether the current page is the account page.
		 *
		 * @return bool
		 */
		function is_account_page() {
			return (bool) $GLOBALS['_is_account_page'];
		}

		/**
		 * Swaps the page title on an endpoint.
		 *
		 * @param string $title Title.
		 * @return string
		 */
		function wc_page_endpoint_title( $title ) {
			return $title;
		}
	}

	/*
	-------------------------------------------------------------------------
	HivePress
	-------------------------------------------------------------------------
	*/

	/**
	 * The HivePress facade. Components are reached through a magic getter, which
	 * is why the plugin assigns before testing rather than using isset().
	 */
	class Amehp_Test_HivePress {

		/**
		 * Router.
		 *
		 * @var object
		 */
		public $router;

		/**
		 * Template component.
		 *
		 * @var object
		 */
		public $template;

		/**
		 * Translator.
		 *
		 * @var object
		 */
		public $translator;

		/**
		 * Scheduler.
		 *
		 * @var object
		 */
		public $scheduler;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->router     = new Amehp_Test_Router();
			$this->template   = new Amehp_Test_Template();
			$this->translator = new class() {

				/**
				 * Gets a translated string.
				 *
				 * @param string $name String name.
				 * @return string
				 */
				public function get_string( $name ) {
					return ucfirst( str_replace( '_', ' ', $name ) );
				}
			};

			$this->scheduler = new class() {

				/**
				 * Queues an action.
				 *
				 * @param string $hook Hook name.
				 */
				public function add_action( $hook ) {
					$GLOBALS['_actions'][] = 'scheduled:' . $hook;
				}
			};
		}

		/**
		 * Resolves a component by name.
		 *
		 * @param string $name Component name.
		 * @return mixed
		 */
		public function __get( $name ) {
			return $GLOBALS['_components'][ $name ] ?? null;
		}

		/**
		 * Gets a configuration.
		 *
		 * @param string $name Config name.
		 * @return array
		 */
		public function get_config( $name ) {
			static $configs = [];

			if ( ! isset( $configs[ $name ] ) ) {
				$configs[ $name ] = [];

				// The REAL codes config, so every codepoint, family and rejected
				// name in these tests is the one a site would get.
				if ( 'amehp_icon_codes' === $name ) {
					$configs[ $name ] = (array) require AMEHP_TEST_PLUGIN_DIR . '/includes/configs/amehp-icon-codes.php';
				} elseif ( 'icons' === $name ) {

					// A slice of core's own Font Awesome 5 solid list.
					$configs[ $name ] = [
						'anchor'      => 'anchor',
						'heart'       => 'heart',
						'info-circle' => 'info-circle',
					];
				}
			}

			return $configs[ $name ];
		}

		/**
		 * Gets the HivePress path.
		 *
		 * @return string
		 */
		public function get_path() {
			return '/hivepress';
		}

		/**
		 * Gets an extension version.
		 *
		 * @param string $name Extension name.
		 * @return string|null
		 */
		public function get_version( $name ) {
			return null;
		}
	}

	/**
	 * The router. A route that is not registered is an extension that is not
	 * installed, which is how the matrix removes Bookings and Memberships.
	 */
	class Amehp_Test_Router {

		/**
		 * Gets a route.
		 *
		 * @param string $name Route name.
		 * @return array|null
		 */
		public function get_route( $name ) {
			return $GLOBALS['_routes'][ $name ] ?? null;
		}

		/**
		 * Gets a route URL.
		 *
		 * @param string $name Route name.
		 * @param array  $params Route parameters.
		 * @return string
		 */
		public function get_url( $name, $params = [] ) {
			if ( ! isset( $GLOBALS['_routes'][ $name ] ) ) {
				return '';
			}

			$url = 'http://example.test/' . str_replace( '_', '-', $name );

			foreach ( $params as $value ) {
				$url .= '/' . $value;
			}

			return $url;
		}

		/**
		 * Gets the current route name.
		 *
		 * @return string
		 */
		public function get_current_route_name() {
			return (string) $GLOBALS['_current_route'];
		}
	}

	/**
	 * Mirrors HivePress\Components\Template::merge_blocks()/_merge_blocks(),
	 * including the part that shapes the plugin's two-call usage: a block that
	 * has just been matched is not descended into.
	 */
	class Amehp_Test_Template {

		/**
		 * Merges blocks into a template.
		 *
		 * @param array $template Template.
		 * @param array $blocks Blocks.
		 * @return array
		 */
		public function merge_blocks( &$template, $blocks ) {
			if ( isset( $template['blocks'] ) ) {
				$template['blocks'] = $this->merge( $template['blocks'], $blocks );
			} else {
				$template = $this->merge( $template, $blocks );
			}

			return $template;
		}

		/**
		 * Merges one level.
		 *
		 * @param array $template Template.
		 * @param array $blocks Blocks.
		 * @return array
		 */
		private function merge( &$template, &$blocks ) {
			$names = array_keys( $blocks );

			foreach ( $template as $name => $block ) {
				if ( ! $names ) {
					break;
				}

				$index = array_search( $name, $names, true );

				if ( false !== $index ) {
					$template[ $name ] = array_replace_recursive( $template[ $name ], $blocks[ $name ] );

					unset( $blocks[ $name ], $names[ $index ] );
				} elseif ( isset( $block['blocks'] ) ) {
					$template[ $name ]['blocks'] = $this->merge( $template[ $name ]['blocks'], $blocks );
				}
			}

			return $template;
		}
	}

	$GLOBALS['_components'] = [];
	$GLOBALS['_hivepress']  = new Amehp_Test_HivePress();

	/**
	 * Gets the HivePress facade.
	 *
	 * @return Amehp_Test_HivePress
	 */
	function hivepress() {
		return $GLOBALS['_hivepress'];
	}

	/*
	-------------------------------------------------------------------------
	Test plumbing
	-------------------------------------------------------------------------
	*/

	$GLOBALS['_pass'] = 0;
	$GLOBALS['_fail'] = 0;

	/**
	 * Records one assertion.
	 *
	 * @param bool   $condition Condition.
	 * @param string $label Label.
	 */
	function ok( $condition, $label ) {
		if ( $condition ) {
			$GLOBALS['_pass']++;

			echo "  PASS  $label\n";
		} else {
			$GLOBALS['_fail']++;

			echo "  FAIL  $label\n";
		}
	}

	/**
	 * Prints the summary line run.php collects, and exits.
	 */
	function amehp_test_finish() {
		echo "\n----------------------------------------\n";
		echo "RESULT: {$GLOBALS['_pass']} passed, {$GLOBALS['_fail']} failed\n";

		exit( $GLOBALS['_fail'] > 0 ? 1 : 0 );
	}

	/**
	 * Reads a private or protected property.
	 *
	 * @param object $instance Instance.
	 * @param string $name Property name.
	 * @return mixed
	 */
	function get_priv( $instance, $name ) {
		$property = new ReflectionProperty( $instance, $name );

		$property->setAccessible( true );

		return $property->getValue( $instance );
	}

	/**
	 * Writes a private or protected property.
	 *
	 * @param object $instance Instance.
	 * @param string $name Property name.
	 * @param mixed  $value Value.
	 */
	function set_priv( $instance, $name, $value ) {
		$property = new ReflectionProperty( $instance, $name );

		$property->setAccessible( true );

		$property->setValue( $instance, $value );
	}

	/**
	 * Calls a private or protected method.
	 *
	 * @param object $instance Instance.
	 * @param string $name Method name.
	 * @param array  $args Arguments.
	 * @return mixed
	 */
	function call_priv( $instance, $name, $args = [] ) {
		$method = new ReflectionMethod( $instance, $name );

		$method->setAccessible( true );

		return $method->invokeArgs( $instance, $args );
	}
}
