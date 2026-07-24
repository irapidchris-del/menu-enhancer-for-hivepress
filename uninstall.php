<?php
/**
 * Uninstalls the plugin.
 *
 * Deletes all plugin options, including the preserved version 1.x settings,
 * since rolling back no longer applies once the plugin is deleted.
 *
 * @package AccountMenuEnhancer
 */

// Exit if uninstall is not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$amehp_options = [
	'hp_amehp_icons',
	'hp_amehp_icon_colour',
	'hp_amehp_menu_weight',
	'hp_amehp_hide_chevrons',
	'hp_amehp_custom_items',
	'hp_amehp_hidden_items',
	'hp_amehp_unify_account',
	'hp_amehp_merge_menus',
	'hp_amehp_wc_badges',
	'hp_amehp_hide_wc_header',
	'amehp_version',
	'amehp_settings',
];

foreach ( $amehp_options as $amehp_option ) {
	delete_option( $amehp_option );
}
