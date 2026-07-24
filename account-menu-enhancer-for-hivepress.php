<?php
/**
 * Plugin Name: Account Menu Enhancer for HivePress
 * Description: Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item Font Awesome icons and colours, custom menu items, and the option to hide any item.
 * Version: 2.1.3
 * Author: ChrisB @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb/summary
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: account-menu-enhancer-for-hivepress
 * Domain Path: /languages
 *
 * @package AccountMenuEnhancer
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
if ( ! defined( 'AMEHP_VERSION' ) ) {
	define( 'AMEHP_VERSION', '2.1.3' );
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
