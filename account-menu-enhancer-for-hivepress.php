<?php
/**
 * Plugin Name: Account Menu Enhancer for HivePress
 * Description: Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item Font Awesome icons and colours, custom menu items, and the option to hide any item.
 * Version: 2.2.4
 * Author: ChrisB @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb/summary
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: account-menu-enhancer-for-hivepress
 * Domain Path: /languages
 * Update URI: https://github.com/irapidchris-del/menu-enhancer-for-hivepress
 *
 * @package AccountMenuEnhancer
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
if ( ! defined( 'AMEHP_VERSION' ) ) {
	define( 'AMEHP_VERSION', '2.2.4' );
}

// Define the plugin file.
if ( ! defined( 'AMEHP_FILE' ) ) {
	define( 'AMEHP_FILE', __FILE__ );
}

// Define the plugin directory.
if ( ! defined( 'AMEHP_DIR' ) ) {
	define( 'AMEHP_DIR', __DIR__ );
}

/**
 * Registers the plugin as a HivePress extension.
 *
 * HivePress registers a plain directory path only when the main plugin file is
 * named after its folder, which is the case for the released package. When the
 * folder has a different name (for example when installed straight from source)
 * the array form is used instead, which HivePress accepts regardless of the
 * folder name so the plugin still works.
 *
 * @param array $extensions Extension configurations.
 * @return array
 */
function amehp_register_extension( $extensions ) {
	if ( basename( AMEHP_DIR ) === basename( AMEHP_FILE, '.php' ) ) {
		$extensions[] = AMEHP_DIR;
	} else {
		$extensions['account_menu_enhancer'] = [
			'name'    => 'Account Menu Enhancer for HivePress',
			'version' => AMEHP_VERSION,
			'path'    => AMEHP_DIR,
			'url'     => rtrim( plugin_dir_url( AMEHP_FILE ), '/' ),
		];
	}

	return $extensions;
}

add_filter( 'hivepress/v1/extensions', 'amehp_register_extension' );

/**
 * Loads the plugin translations.
 *
 * HivePress loads extension text domains based on the folder name, so the
 * plugin loads its own translations to keep working with any folder name.
 */
function amehp_load_textdomain() {
	load_plugin_textdomain( 'account-menu-enhancer-for-hivepress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'amehp_load_textdomain' );

/**
 * Displays an admin notice if HivePress is not active.
 */
function amehp_admin_notice() {
	if ( function_exists( 'hivepress' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html__( 'Account Menu Enhancer for HivePress requires the HivePress plugin to be installed and activated.', 'account-menu-enhancer-for-hivepress' ) . '</p></div>';
}

add_action( 'admin_notices', 'amehp_admin_notice' );

/**
 * Adds a settings link to the plugin action links.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function amehp_add_settings_link( $links ) {
	if ( function_exists( 'hivepress' ) ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=hp_settings&tab=account_menu' ) ) . '">' . esc_html__( 'Settings', 'account-menu-enhancer-for-hivepress' ) . '</a>' );
	}

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'amehp_add_settings_link' );

/**
 * Migrates settings from plugin version 1.x once.
 *
 * Version 1.x stored everything in a single "amehp_settings" option. Version 2
 * saves each setting as a separate HivePress option. The legacy option is kept
 * untouched so that rolling back remains possible.
 */
function amehp_maybe_migrate() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check the installed version.
	if ( version_compare( (string) get_option( 'amehp_version' ), '2.0.0', '>=' ) ) {
		return;
	}

	// Get the legacy settings.
	$legacy = get_option( 'amehp_settings' );

	// The migrated values are written unconditionally because HivePress seeds
	// the plugin options with their defaults on the same request (via the
	// activation event at "init"), before this callback runs on "admin_init".
	if ( is_array( $legacy ) ) {

		// Migrate the integration toggle.
		update_option( 'hp_amehp_merge_menus', empty( $legacy['enable_integration'] ) ? '' : '1' );

		// Preserve the existing page layout for upgraded sites.
		update_option( 'hp_amehp_unify_account', '' );

		// Migrate the hidden WooCommerce items.
		if ( ! empty( $legacy['woocommerce_items_to_hide'] ) && is_array( $legacy['woocommerce_items_to_hide'] ) ) {
			update_option(
				'hp_amehp_hidden_items',
				array_map(
					function ( $endpoint ) {
						return 'wc:' . sanitize_text_field( (string) $endpoint );
					},
					$legacy['woocommerce_items_to_hide']
				)
			);
		}

		// Migrate the custom menu items.
		if ( ! empty( $legacy['custom_menu_items'] ) && is_array( $legacy['custom_menu_items'] ) ) {
			$items = [];

			foreach ( $legacy['custom_menu_items'] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['label'] ) || '' === $item['label'] ) {
					continue;
				}

				$link = '';
				$url  = '';

				if ( isset( $item['type'] ) && 'page' === $item['type'] && ! empty( $item['page_id'] ) ) {
					$link = 'page:' . absint( $item['page_id'] );
				} elseif ( isset( $item['type'] ) && 'hivepress_route' === $item['type'] && ! empty( $item['route'] ) ) {
					$link = 'route:' . sanitize_text_field( (string) $item['route'] );
				} elseif ( ! empty( $item['url'] ) ) {
					$url = esc_url_raw( (string) $item['url'] );
				}

				$items[] = [
					'label'  => sanitize_text_field( (string) $item['label'] ),
					'link'   => $link,
					'url'    => $url,
					'icon'   => null,
					'colour' => null,
					'menus'  => isset( $item['menu'] ) && in_array( $item['menu'], [ 'hivepress', 'woocommerce', 'both' ], true ) ? $item['menu'] : 'both',
					'order'  => isset( $item['position'] ) ? absint( $item['position'] ) : 100,
					'roles'  => isset( $item['roles'] ) && is_array( $item['roles'] ) ? array_map( 'sanitize_text_field', $item['roles'] ) : null,
				];
			}

			if ( $items ) {
				update_option( 'hp_amehp_custom_items', $items );
			}
		}
	}

	// Flag the migration as complete.
	update_option( 'amehp_version', AMEHP_VERSION );
}

add_action( 'admin_init', 'amehp_maybe_migrate' );

/*
 * -------------------------------------------------------------------------
 * Updates
 *
 * The plugin is distributed via GitHub releases rather than wordpress.org,
 * so update checks go through the native `update_plugins_{$hostname}` API
 * added in WordPress 5.8, keyed off the Update URI header above. The update
 * package is the release asset named `*.zip`, which contains a single
 * "account-menu-enhancer-for-hivepress" directory.
 * -------------------------------------------------------------------------
 */

if ( ! defined( 'AMEHP_UPDATE_REPO' ) ) {
	define( 'AMEHP_UPDATE_REPO', 'irapidchris-del/menu-enhancer-for-hivepress' );
}

if ( ! defined( 'AMEHP_UPDATE_SLUG' ) ) {
	define( 'AMEHP_UPDATE_SLUG', 'account-menu-enhancer-for-hivepress' );
}

if ( ! defined( 'AMEHP_UPDATE_CACHE_KEY' ) ) {
	define( 'AMEHP_UPDATE_CACHE_KEY', 'amehp_github_release' );
}

/**
 * Gets the latest GitHub release details, cached for 6 hours.
 *
 * @param bool $force Bypass the cache.
 * @return array<string, string>|null
 */
function amehp_get_latest_release( $force = false ) {
	$release = $force ? false : get_site_transient( AMEHP_UPDATE_CACHE_KEY );

	if ( ! is_array( $release ) ) {
		$release = amehp_fetch_latest_release();

		// Failures are cached briefly so the API is not queried repeatedly.
		set_site_transient( AMEHP_UPDATE_CACHE_KEY, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	}

	return $release ? $release : null;
}

/**
 * Fetches the latest release details from the GitHub API.
 *
 * Draft and pre-release entries are excluded by the endpoint itself, so
 * publishing a pre-release never triggers an update notice.
 *
 * @return array<string, string>
 */
function amehp_fetch_latest_release() {
	$response = wp_remote_get(
		'https://api.github.com/repos/' . AMEHP_UPDATE_REPO . '/releases/latest',
		[
			'timeout' => 10,
			'headers' => [ 'Accept' => 'application/vnd.github+json' ],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) ) {
		return [];
	}

	// The version is read from the release tag, with or without a "v" prefix.
	$version = ltrim( (string) ( isset( $data['tag_name'] ) ? $data['tag_name'] : '' ), 'vV' );

	if ( ! $version ) {
		return [];
	}

	// The update package is the first release asset named `*.zip`.
	$package = '';

	foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : [] ) as $asset ) {
		$name = strtolower( (string) ( isset( $asset['name'] ) ? $asset['name'] : '' ) );

		if ( '.zip' === substr( $name, -4 ) && ! empty( $asset['browser_download_url'] ) ) {
			$package = (string) $asset['browser_download_url'];

			break;
		}
	}

	if ( ! $package ) {
		return [];
	}

	return [
		'version'   => $version,
		'package'   => $package,
		'url'       => (string) ( isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . AMEHP_UPDATE_REPO ),
		'notes'     => (string) ( isset( $data['body'] ) ? $data['body'] : '' ),
		'published' => (string) ( isset( $data['published_at'] ) ? $data['published_at'] : '' ),
	];
}

/**
 * Provides the update details to the WordPress update system.
 *
 * WordPress matches the plugin to this filter via the Update URI header
 * hostname and compares the versions itself, filing the result under
 * either the available updates or the up-to-date list.
 *
 * @param array<string, mixed>|false $update Update data.
 * @param array<string, string>      $plugin_data Plugin headers.
 * @param string                     $plugin_file Plugin basename.
 * @return array<string, mixed>|false
 */
function amehp_check_for_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $update;
	}

	$release = amehp_get_latest_release();

	if ( ! $release ) {
		return $update;
	}

	return [
		'id'      => 'https://github.com/' . AMEHP_UPDATE_REPO,
		'slug'    => AMEHP_UPDATE_SLUG,
		'plugin'  => $plugin_file,
		'version' => $release['version'],
		'url'     => $release['url'],
		'package' => $release['package'],
	];
}

add_filter( 'update_plugins_github.com', 'amehp_check_for_update', 10, 3 );

/**
 * Provides the plugin details for the update information popup.
 *
 * Without this the "View version x.x.x details" link on the Plugins screen
 * would open an empty modal, since the plugin is not on wordpress.org.
 *
 * @param object|array|false $result Result object.
 * @param string             $action API action.
 * @param object             $args API arguments.
 * @return object|array|false
 */
function amehp_get_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || AMEHP_UPDATE_SLUG !== ( isset( $args->slug ) ? $args->slug : '' ) ) {
		return $result;
	}

	$release = amehp_get_latest_release();

	if ( ! $release ) {
		return $result;
	}

	$plugin_data = get_file_data(
		__FILE__,
		[
			'Name'        => 'Plugin Name',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		]
	);

	return (object) [
		'name'          => $plugin_data['Name'],
		'slug'          => AMEHP_UPDATE_SLUG,
		'version'       => $release['version'],
		'author'        => '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . esc_html( $plugin_data['Author'] ) . '</a>',
		'homepage'      => 'https://github.com/' . AMEHP_UPDATE_REPO,
		'requires'      => $plugin_data['RequiresWP'],
		'requires_php'  => $plugin_data['RequiresPHP'],
		'last_updated'  => $release['published'],
		'download_link' => $release['package'],
		'sections'      => [
			'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
			'changelog'   => $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '<p>' . esc_html__( 'See the GitHub releases page for the changelog.', 'account-menu-enhancer-for-hivepress' ) . '</p>',
		],
	];
}

add_filter( 'plugins_api', 'amehp_get_plugin_information', 10, 3 );

/**
 * Adds the manual update check link to the plugin row.
 *
 * @param array<string> $links Plugin action links.
 * @return array<string>
 */
function amehp_add_update_check_link( $links ) {
	if ( current_user_can( 'update_plugins' ) ) {
		$links[] = '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?amehp_check_updates=1' ), 'amehp_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'account-menu-enhancer-for-hivepress' ) . '</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'amehp_add_update_check_link' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'amehp_add_update_check_link' );

/**
 * Handles the manual update check.
 *
 * Refreshes the cached release, re-runs the update check and redirects back
 * to the Plugins screen with the result.
 *
 * @return void
 */
function amehp_handle_update_check() {
	if ( ! isset( $_GET['amehp_check_updates'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	check_admin_referer( 'amehp_check_updates' );

	$release = amehp_get_latest_release( true );

	wp_clean_plugins_cache();
	wp_update_plugins();

	$status = 'none';

	if ( ! $release ) {
		$status = 'error';
	} elseif ( version_compare( $release['version'], AMEHP_VERSION, '>' ) ) {
		$status = 'available';
	}

	wp_safe_redirect( add_query_arg( 'amehp_checked', $status, self_admin_url( 'plugins.php' ) ) );

	exit;
}

add_action( 'admin_init', 'amehp_handle_update_check' );

/**
 * Shows the manual update check result.
 *
 * @return void
 */
function amehp_show_update_check_notice() {
	if ( ! isset( $_GET['amehp_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$status = sanitize_key( wp_unslash( $_GET['amehp_checked'] ) );

	if ( 'available' === $status ) {
		$release = amehp_get_latest_release();

		/* translators: %s: new version number. */
		$message = sprintf( __( 'A new version of Account Menu Enhancer for HivePress (%s) is available.', 'account-menu-enhancer-for-hivepress' ), $release ? $release['version'] : '' );
		$class   = 'notice-success';
	} elseif ( 'none' === $status ) {
		$message = __( 'Account Menu Enhancer for HivePress is up to date.', 'account-menu-enhancer-for-hivepress' );
		$class   = 'notice-success';
	} elseif ( 'error' === $status ) {
		$message = __( 'Could not reach GitHub to check for updates. Please try again later.', 'account-menu-enhancer-for-hivepress' );
		$class   = 'notice-error';
	} else {
		return;
	}

	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}

add_action( 'admin_notices', 'amehp_show_update_check_notice' );
add_action( 'network_admin_notices', 'amehp_show_update_check_notice' );

/**
 * Keeps updates installing into the current plugin directory.
 *
 * The extracted release folder is renamed to match the directory the plugin
 * is installed in, so an update can never end up in a differently named
 * folder even if the release zip is packaged unexpectedly.
 *
 * @param string               $source Extracted update source.
 * @param string               $remote_source Remote source directory.
 * @param object               $upgrader Upgrader instance.
 * @param array<string, mixed> $hook_extra Extra hook arguments.
 * @return string|WP_Error
 */
function amehp_fix_update_directory( $source, $remote_source, $upgrader, $hook_extra = [] ) {
	global $wp_filesystem;

	if ( plugin_basename( __FILE__ ) !== ( isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '' ) || ! $wp_filesystem ) {
		return $source;
	}

	$directory = dirname( plugin_basename( __FILE__ ) );

	if ( '.' === $directory ) {
		return $source;
	}

	$target = trailingslashit( $remote_source ) . $directory . '/';

	if ( trailingslashit( $source ) === $target ) {
		return $source;
	}

	if ( ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $target ) ) ) {
		return new WP_Error( 'amehp_rename_failed', __( 'Could not rename the update directory.', 'account-menu-enhancer-for-hivepress' ) );
	}

	return $target;
}

add_filter( 'upgrader_source_selection', 'amehp_fix_update_directory', 10, 4 );
