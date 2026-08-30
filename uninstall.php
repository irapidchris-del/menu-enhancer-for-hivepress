<?php
/**
 * Uninstalls the plugin.
 *
 * Your settings are kept unless you asked for them to go. Deleting a plugin is
 * often a reinstall in disguise - a site owner clearing a problem, or swapping a
 * broken copy for a clean one - so destruction is opt-in via the "Delete All
 * Data" setting and never the default. WordPress prints its own "will also
 * delete its data" warning on the delete screen whenever an uninstall.php
 * exists, whatever that file actually does, so the setting's description says
 * plainly that the warning does not apply here unless the box is ticked.
 *
 * @package AccountMenuEnhancer
 */

// Exit if uninstall is not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Regenerable runtime junk goes either way.
 *
 * The cached GitHub release lookup is rebuilt on the next update check, so
 * there is nothing to lose by clearing it and an orphaned row to gain by
 * leaving it.
 */
delete_site_transient( 'amehp_github_release' );

/*
 * The updater's other two site transients and its background job, which used to be left behind.
 *
 * All three are regenerable runtime state belonging to the update check, not the owner's
 * configuration, so they go unconditionally alongside the release cache above. Core's daily sweep
 * clears expired site transients within about a day on single-site, which is why this read as
 * harmless; on multisite they live in wp_sitemeta and are only purged when something asks for
 * them, so on a network they simply stay. The scheduled refresh is worse than debris: it is a job
 * whose callback no longer exists.
 *
 * Unscheduled from both places it can be, because the refresh is queued through HivePress's
 * scheduler (Action Scheduler) when HivePress is present and through WP-Cron when it is not.
 */
delete_site_transient( 'amehp_github_release_reason' );
delete_site_transient( 'amehp_github_release_rate_limit' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'amehp_github_release_refresh', [], 'hivepress' );
	as_unschedule_all_actions( 'amehp_github_release_refresh' );
}

wp_clear_scheduled_hook( 'amehp_github_release_refresh' );

// Everything below is the owner's own configuration, so it only goes on request.
if ( ! get_option( 'hp_amehp_delete_data' ) ) {
	return;
}

$amehp_options = [
	'hp_amehp_icons',
	'hp_amehp_icon_colour',
	'hp_amehp_icon_background',
	'hp_amehp_icon_size',
	'hp_amehp_icon_weight',
	'hp_amehp_menu_weight',
	'hp_amehp_hide_chevrons',
	'hp_amehp_sidebar_heading_font',
	'hp_amehp_custom_items',
	'hp_amehp_hidden_items',
	'hp_amehp_hidden_wc_items',
	'hp_amehp_menu_order',
	'hp_amehp_wc_integration',
	'hp_amehp_unify_account',
	'hp_amehp_merge_menus',
	'hp_amehp_wc_badges',
	'hp_amehp_hide_wc_header',
	'hp_amehp_icon_spacing',
	'hp_amehp_seen_items',
	'hp_amehp_persistent_items',
	'hp_amehp_persistent_known_items',
	'amehp_version',
	'amehp_settings',
];

foreach ( $amehp_options as $amehp_option ) {
	delete_option( $amehp_option );
}

/*
 * The per-page button options absorbed from Persistent Account Menu in 3.0.0.
 *
 * Swept by prefix because the set is dynamic: one label and one URL per
 * managed menu item, and which items exist depends on the extensions that
 * were active. This runs once, while the plugin is being deleted, so there is
 * nothing worth caching.
 */
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$amehp_button_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'hp_amehp_button_label_' ) . '%',
		$wpdb->esc_like( 'hp_amehp_button_url_' ) . '%',
		// The icon and message added to each placeholder page in 3.3.0.
		$wpdb->esc_like( 'hp_amehp_page_icon_' ) . '%',
		$wpdb->esc_like( 'hp_amehp_page_text_' ) . '%'
	)
);

foreach ( (array) $amehp_button_options as $amehp_button_option ) {
	delete_option( $amehp_button_option );
}

/**
 * The flag itself goes last, deliberately.
 *
 * If anything above fails part-way through, the flag is still set, so deleting
 * the plugin a second time finishes the job. Clearing it first would silently
 * flip the site back to "retain" with half the settings already gone.
 */
delete_option( 'hp_amehp_delete_data' );
