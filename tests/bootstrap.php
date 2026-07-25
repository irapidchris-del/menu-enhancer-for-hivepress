<?php
/**
 * Isolated test bootstrap.
 *
 * Loads the real HivePress helpers and the plugin classes with minimal
 * WordPress and WooCommerce shims, so the plugin logic can be verified
 * without a WordPress installation.
 *
 * Usage: HIVEPRESS_PATH=/path/to/hivepress php tests/run-tests.php
 *
 * @package AccountMenuEnhancer\Tests
 */

// phpcs:ignoreFile

// Abort unless run from the command line.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'AMEHP_TESTS', true );

// Plugin constants, matching the ones defined by the main plugin file.
define( 'AMEHP_VERSION', '2.2.0' );
define( 'AMEHP_FILE', dirname( __DIR__ ) . '/account-menu-enhancer-for-hivepress.php' );
define( 'AMEHP_DIR', dirname( __DIR__ ) );

error_reporting( E_ALL );

/*
---------------------------------------------------------------------------
 Test state
---------------------------------------------------------------------------
*/

$GLOBALS['amehp_test'] = [
	'options'        => [],
	'filters'        => [],
	'actions'        => [],
	'logged_in'      => true,
	'user_roles'     => [ 'customer' ],
	'user_login'     => 'chris',
	'is_admin'       => false,
	'is_account'     => false,
	'wc_endpoint'    => '',
	'wc_items'       => [],
	'hp_menu_items'  => [],
	'permalinks'     => [],
	'posts'          => [],
	'global_post'    => null,
	'route_urls'     => [],
	'route_titles'   => [],
	'route_params'   => [],
	'vendor_id'      => 0,
	'menu_throws'    => false,
	'wc_menu_throws' => false,
];

function amehp_test_state( $key, $value = null ) {
	if ( null !== $value ) {
		$GLOBALS['amehp_test'][ $key ] = $value;
	}

	return $GLOBALS['amehp_test'][ $key ];
}

function amehp_test_reset() {
	$GLOBALS['amehp_test'] = array_merge(
		$GLOBALS['amehp_test'],
		[
			'options'        => [],
			'filters'        => [],
			'actions'        => [],
			'logged_in'      => true,
			'user_roles'     => [ 'customer' ],
			'user_login'     => 'chris',
			'is_admin'       => false,
			'is_account'     => false,
			'wc_endpoint'    => '',
			'wc_items'       => [],
			'hp_menu_items'  => [],
			'permalinks'     => [],
			'posts'          => [],
			'global_post'    => null,
			'route_urls'     => [],
			'route_titles'   => [],
			'route_params'   => [],
			'vendor_id'      => 0,
			'menu_throws'    => false,
			'wc_menu_throws' => false,
		]
	);
}

/*
---------------------------------------------------------------------------
 WordPress shims
---------------------------------------------------------------------------
*/

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['amehp_test']['options'] ) ? $GLOBALS['amehp_test']['options'][ $name ] : $default;
}

function update_option( $name, $value ) {
	$GLOBALS['amehp_test']['options'][ $name ] = $value;

	return true;
}

function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['amehp_test']['filters'][ $tag ][] = [ $callback, $priority, $args ];

	return true;
}

function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['amehp_test']['actions'][ $tag ][] = [ $callback, $priority, $args ];

	return true;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function do_action( $tag ) {}

function is_admin() {
	return amehp_test_state( 'is_admin' );
}

function is_user_logged_in() {
	return amehp_test_state( 'logged_in' );
}

function get_current_user_id() {
	return amehp_test_state( 'logged_in' ) ? 7 : 0;
}

function wp_get_current_user() {
	$user = new stdClass();

	$user->roles = amehp_test_state( 'logged_in' ) ? amehp_test_state( 'user_roles' ) : [];

	$user->exists = amehp_test_state( 'logged_in' );

	return new class( $user ) {
		private $user;

		public $roles;

		public $user_login;

		public function __construct( $user ) {
			$this->user       = $user;
			$this->roles      = $user->roles;
			$this->user_login = amehp_test_state( 'logged_in' ) ? amehp_test_state( 'user_login' ) : '';
		}

		public function exists() {
			return $this->user->exists;
		}
	};
}

function current_user_can( $capability ) {
	return true;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return $url;
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $text ) );
}

function sanitize_title( $title ) {
	return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $title ) );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/' );
}

function trailingslashit( $value ) {
	return untrailingslashit( $value ) . '/';
}

function absint( $value ) {
	return abs( (int) $value );
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function get_permalink( $post = 0 ) {
	$post_id = is_object( $post ) ? $post->ID : $post;

	$permalinks = amehp_test_state( 'permalinks' );

	return isset( $permalinks[ $post_id ] ) ? $permalinks[ $post_id ] : false;
}

function get_post( $post_id = null ) {
	// Mirror WordPress: get_post( 0 ) returns the global post, if any.
	if ( empty( $post_id ) ) {
		$global = amehp_test_state( 'global_post' );

		return $global ? (object) $global : null;
	}

	$post_id = absint( $post_id );
	$posts   = amehp_test_state( 'posts' );

	if ( isset( $posts[ $post_id ] ) ) {
		return (object) array_merge( [ 'ID' => $post_id, 'post_status' => 'publish' ], $posts[ $post_id ] );
	}

	// Treat any page with a known permalink as published by default.
	$permalinks = amehp_test_state( 'permalinks' );

	if ( isset( $permalinks[ $post_id ] ) ) {
		return (object) [ 'ID' => $post_id, 'post_status' => 'publish' ];
	}

	return null;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

function wp_list_sort( $input_list, $orderby = [], $order = 'ASC', $preserve_keys = false ) {
	uasort(
		$input_list,
		function ( $a, $b ) use ( $orderby ) {
			$field = is_array( $orderby ) ? key( $orderby ) : $orderby;

			return ( isset( $a[ $field ] ) ? $a[ $field ] : 0 ) <=> ( isset( $b[ $field ] ) ? $b[ $field ] : 0 );
		}
	);

	return $input_list;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function plugins_url( $path, $plugin ) {
	return 'https://example.com/wp-content/plugins/' . plugin_basename( $plugin ) . '/' . ltrim( $path, '/' );
}

function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . $path;
}

function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['amehp_test']['inline_css'] = $css;
}
function wp_localize_script( $handle, $name, $data ) {
	$GLOBALS['amehp_test']['localized'] = $data;
}

function translate_user_role( $role ) {
	return $role;
}

function get_pages( $args = [] ) {
	return [];
}

function wp_roles() {
	return new class() {
		public function get_names() {
			return [
				'administrator' => 'Administrator',
				'customer'      => 'Customer',
			];
		}
	};
}

/*
---------------------------------------------------------------------------
 WooCommerce shims
---------------------------------------------------------------------------
*/

class WooCommerce {
	public $query;

	public function __construct() {
		$this->query = new class() {
			public function get_current_endpoint() {
				return amehp_test_state( 'wc_endpoint' );
			}
		};
	}
}

function WC() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new WooCommerce();
	}

	return $instance;
}

function is_account_page() {
	return amehp_test_state( 'is_account' );
}

function wc_get_account_menu_items() {
	if ( amehp_test_state( 'wc_menu_throws' ) ) {
		throw new \TypeError( 'wc_get_account_menu_items(): front-end context required' );
	}

	// Apply the registered filter like WooCommerce does.
	$items = amehp_test_state( 'wc_items' );

	foreach ( $GLOBALS['amehp_test']['filters']['woocommerce_account_menu_items'] ?? [] as $filter ) {
		$items = call_user_func( $filter[0], $items );
	}

	return $items;
}

function wc_get_account_endpoint_url( $endpoint ) {
	$url = 'dashboard' === $endpoint ? 'https://example.com/my-account/' : 'https://example.com/my-account/' . $endpoint . '/';

	foreach ( $GLOBALS['amehp_test']['filters']['woocommerce_get_endpoint_url'] ?? [] as $filter ) {
		$url = call_user_func( $filter[0], $url, $endpoint );
	}

	return $url;
}

function wc_page_endpoint_title( $title ) {
	return $title;
}

/*
---------------------------------------------------------------------------
 HivePress shims
---------------------------------------------------------------------------
*/

$hivepress_path = getenv( 'HIVEPRESS_PATH' );

if ( ! $hivepress_path || ! file_exists( $hivepress_path . '/includes/helpers.php' ) ) {
	fwrite( STDERR, "Set HIVEPRESS_PATH to the HivePress plugin directory.\n" );
	exit( 1 );
}

require_once $hivepress_path . '/includes/helpers.php';

class Amehp_Test_Router {
	public function get_url( $route, $params = [] ) {
		$recorded                 = amehp_test_state( 'route_params' );
		$recorded[ $route ]       = $params;
		amehp_test_state( 'route_params', $recorded );

		// Mirror core: the user profile URL is built from the username, so a
		// missing/wrong parameter key produces an empty (invalid) URL.
		if ( 'user_view_page' === $route ) {
			$username = isset( $params['username'] ) ? (string) $params['username'] : '';

			return '' !== $username ? 'https://example.com/user/' . $username . '/' : '';
		}

		$urls = amehp_test_state( 'route_urls' );

		return isset( $urls[ $route ] ) ? $urls[ $route ] : '';
	}

	public function get_route( $route ) {
		$titles = amehp_test_state( 'route_titles' );

		if ( isset( $titles[ $route ] ) ) {
			return [ 'title' => $titles[ $route ] ];
		}

		$urls = amehp_test_state( 'route_urls' );

		// Routes can exist without a title (e.g. user_logout_page).
		return isset( $urls[ $route ] ) ? [ 'path' => '/' . $route ] : null;
	}
}

class Amehp_Test_Hivepress {
	public $router;

	public $account_menu_enhancer;

	public function __construct() {
		$this->router = new Amehp_Test_Router();
	}

	public function get_path() {
		return getenv( 'HIVEPRESS_PATH' );
	}

	public function get_config( $name ) {
		$filepath = getenv( 'HIVEPRESS_PATH' ) . '/includes/configs/' . str_replace( '_', '-', $name ) . '.php';

		if ( 'icon_codes' === $name ) {
			$filepath = dirname( __DIR__ ) . '/includes/configs/icon-codes.php';
		}

		return file_exists( $filepath ) ? include $filepath : [];
	}
}

function hivepress() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Amehp_Test_Hivepress();
	}

	return $instance;
}

/*
---------------------------------------------------------------------------
 HivePress class shims and plugin classes
---------------------------------------------------------------------------
*/

// Minimal component base class.
eval(
	'namespace HivePress\Components;
	abstract class Component {
		public function __construct( $args = [] ) {}
	}'
);

// Menu returning the canned items.
eval(
	'namespace HivePress\Menus;
	class User_Account {
		public function get_items() {
			if ( \amehp_test_state( "menu_throws" ) ) {
				throw new \TypeError( "array_reduce(): Argument #1 (\$array) must be of type array, null given" );
			}

			return \amehp_test_state( "hp_menu_items" );
		}
	}'
);

// Vendor model resolving the canned vendor.
eval(
	'namespace HivePress\Models;
	class Vendor {
		public static function query() {
			return new class() {
				public function filter( $args ) {
					return $this;
				}
				public function get_first_id() {
					return \amehp_test_state( "vendor_id" ) ? \amehp_test_state( "vendor_id" ) : null;
				}
			};
		}
	}'
);

require_once dirname( __DIR__ ) . '/includes/components/class-account-menu-enhancer.php';

// Expose the component to the settings configuration.
function amehp_test_component( $args = [] ) {
	$component = new \HivePress\Components\Account_Menu_Enhancer( $args );

	hivepress()->account_menu_enhancer = $component;

	return $component;
}

function amehp_test_call( $component, $method, $args = [] ) {
	$reflection = new \ReflectionMethod( $component, $method );

	$reflection->setAccessible( true );

	return $reflection->invokeArgs( $component, $args );
}
