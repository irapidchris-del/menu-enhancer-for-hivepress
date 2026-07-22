<?php
/**
 * Plugin Name: Account Menu Enhancer for HivePress
 * Description: Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item Font Awesome icons and colours, custom menu items, and the option to hide any item.
 * Version: 2.0.1
 * Author: Chris Bruce
 * Author URI: https://community.hivepress.io/u/chrisb
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * Text Domain: account-menu-enhancer-for-hivepress
 * Domain Path: /languages
 *
 * @package AccountMenuEnhancer
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
if ( ! defined( 'AMEHP_VERSION' ) ) {
	define( 'AMEHP_VERSION', '2.0.1' );
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
 * Registers the plugin directory as a HivePress extension.
 *
 * @param array $extensions Extension directory paths.
 * @return array
 */
function amehp_register_extension( $extensions ) {
	$extensions[] = __DIR__;

	return $extensions;
}

add_filter( 'hivepress/v1/extensions', 'amehp_register_extension' );

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

	if ( is_array( $legacy ) && false === get_option( 'hp_amehp_merge_menus', false ) ) {

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
