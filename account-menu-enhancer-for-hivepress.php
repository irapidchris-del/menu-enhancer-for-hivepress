<?php
/**
 * Plugin Name: Account Menu Enhancer for HivePress
 * Plugin URI: https://github.com/irapidchris-del/menu-enhancer-for-hivepress
 * Description: Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item Font Awesome icons and colours, custom menu items, and the option to hide any item.
 * Version: 2.2.7
 * Author: ChrisB @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb/summary
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: account-menu-enhancer-for-hivepress
 * Domain Path: /languages/
 * Update URI: https://github.com/irapidchris-del/menu-enhancer-for-hivepress
 *
 * @package AccountMenuEnhancer
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
if ( ! defined( 'AMEHP_VERSION' ) ) {
	define( 'AMEHP_VERSION', '2.2.7' );
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
 * The author's support page.
 *
 * One place, so the Plugins row and the View details popup can never drift apart.
 */
if ( ! defined( 'AMEHP_SUPPORT_URL' ) ) {
	define( 'AMEHP_SUPPORT_URL', 'https://ko-fi.com/chrisbathivepresscommunity' );
}

/**
 * Registers the plugin as a HivePress extension.
 *
 * The form is picked at runtime. The bare directory path only works when the
 * main file is named after its folder, so a renamed folder (for example a
 * GitHub source download) would make the plugin silently do nothing. The array
 * form works from any folder name, but HivePress's updater probe concatenates
 * every entry into a file path, so an array entry makes core log an "Array to
 * string conversion" warning on each request. The normal install therefore
 * registers the plain path, and the renamed-folder fallback registers the
 * array after seeding the updater path itself so the probe never runs.
 *
 * @param array $extensions Extension configurations.
 * @return array
 */
function amehp_register_extension( $extensions ) {
	if ( file_exists( AMEHP_DIR . '/' . basename( AMEHP_DIR ) . '.php' ) ) {
		$extensions[] = AMEHP_DIR;
	} else {
		if ( ! isset( $extensions['updates'] ) ) {
			foreach ( $extensions as $amehp_dir ) {
				if ( is_string( $amehp_dir ) && file_exists( $amehp_dir . '/vendor/hivepress/hivepress-updates/hivepress-updates.php' ) ) {
					$extensions['updates'] = $amehp_dir . '/vendor/hivepress/hivepress-updates';

					break;
				}
			}
		}

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
 * Why the last release check came back empty, so the notice can say which.
 */
if ( ! defined( 'AMEHP_UPDATE_REASON_KEY' ) ) {
	define( 'AMEHP_UPDATE_REASON_KEY', 'amehp_github_release_reason' );
}

/**
 * When GitHub's hourly allowance for this server is expected back. While this is set the
 * API is not called at all, so a site that has run out does not spend the rest of the
 * window making requests that can only fail.
 */
if ( ! defined( 'AMEHP_UPDATE_RATE_LIMIT_KEY' ) ) {
	define( 'AMEHP_UPDATE_RATE_LIMIT_KEY', 'amehp_github_release_rate_limit' );
}

/**
 * Gets the latest GitHub release details, cached for 6 hours.
 *
 * @param bool $force Bypass the cache.
 * @return array<string, string>|null
 */
function amehp_get_latest_release( $force = false ) {
	$cached = get_site_transient( AMEHP_UPDATE_CACHE_KEY );

	if ( ! $force && is_array( $cached ) ) {
		return $cached ? $cached : null;
	}

	$release = amehp_fetch_latest_release();

	// A failed check must not erase what the last good one found. Overwriting the cache with an
	// empty result took a genuinely pending update off the Plugins screen for an hour with nothing
	// to say why, which is worse than showing a result that is at most a few hours old. The short
	// lifetime means the next check still tries again promptly.

	if ( ! $release && $cached ) {
		set_site_transient( AMEHP_UPDATE_CACHE_KEY, $cached, HOUR_IN_SECONDS );

		return $cached;
	}

	// Failures are cached briefly so the lookup is not repeated on every admin page load.
	set_site_transient( AMEHP_UPDATE_CACHE_KEY, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

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
	$data = amehp_fetch_release_data();

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
 * Gets the latest release, from github.com in preference to the GitHub API.
 *
 * WHY THIS DOES NOT SIMPLY CALL THE API
 *
 * Without a token `api.github.com` allows **60 requests an hour per IP address**, and that
 * allowance is shared by every plugin on the site, by every other site on the same server, and by
 * anything else calling the API from that address. A site running several of these extensions,
 * plus a few clicks of "Check for updates" - which deliberately bypasses the cache - spends it
 * easily; on shared hosting a neighbouring site can spend it alone. GitHub then answers 403, and
 * reporting that as "could not reach GitHub" sends the owner hunting a network fault that does not
 * exist. That is the same family of bug as reporting a 404 as unreachable: a refusal is an answer,
 * not a failure to get one.
 *
 * Everything this lookup needs is also published on github.com itself, which carries no such
 * allowance:
 *
 *   - `/releases/latest` answers 302, and the Location header names the release GitHub considers
 *     latest, with drafts and pre-releases excluded exactly as the API excludes them;
 *   - `/releases/expanded_assets/{tag}` is the fragment the release page uses to list its own
 *     downloads, so it names the asset;
 *   - `/releases.atom` carries the release notes.
 *
 * Measured against GitHub's own rate-limit counter on 2026-08-19, thirteen full update checks
 * through this route moved it by zero. The API is kept as a fallback so that a change at github.com
 * cannot leave the plugin with no way to check at all.
 *
 * @return array<string, mixed>|null Release data in the API's own shape, or null.
 */
function amehp_fetch_release_data() {
	$site = amehp_fetch_release_from_site();

	if ( isset( $site['release'] ) ) {
		delete_site_transient( AMEHP_UPDATE_REASON_KEY );

		return $site['release'];
	}

	// github.com has given a definite answer that nothing is published. Asking the API would only
	// repeat it, at the cost of one of the sixty.
	if ( isset( $site['reason'] ) && 'no_release' === $site['reason'] ) {
		set_site_transient( AMEHP_UPDATE_REASON_KEY, 'no_release', HOUR_IN_SECONDS );

		return null;
	}

	return amehp_fetch_release_from_api();
}

/**
 * Reads the latest release from github.com, without touching the API allowance.
 *
 * @return array<string, mixed> Either a `release` in the API's shape, a `reason`, or empty to fall
 *                              back to the API.
 */
function amehp_fetch_release_from_site() {
	$base = 'https://github.com/' . AMEHP_UPDATE_REPO;

	$response = amehp_request(
		$base . '/releases/latest',
		[
			// Do not follow it. The redirect target is the answer.
			'redirection' => 0,
		]
	);

	if ( ! $response ) {
		return [];
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	// A repository with nothing published answers 404 here, which is the normal state of a new
	// repository rather than a fault.
	if ( 404 === $code ) {
		return [ 'reason' => 'no_release' ];
	}

	if ( 301 !== $code && 302 !== $code ) {
		return [];
	}

	$location = wp_remote_retrieve_header( $response, 'location' );

	// WordPress hands back an array when a header repeats.
	if ( is_array( $location ) ) {
		$location = end( $location );
	}

	if ( ! preg_match( '#/releases/tag/(.+)$#', (string) $location, $matches ) ) {
		return [];
	}

	$tag = rawurldecode( trim( $matches[1] ) );

	$asset = amehp_fetch_release_asset( $base, $tag );

	// No downloadable asset means there is nothing the updater could install, so let the API have
	// its say rather than reporting a release that cannot be applied.
	if ( ! $asset ) {
		return [];
	}

	$notes = amehp_fetch_release_notes( $base, $tag );

	// Shaped exactly like the API's own answer, so everything downstream is identical either way.
	return [
		'release' => [
			'tag_name'     => $tag,
			'html_url'     => $base . '/releases/tag/' . rawurlencode( $tag ),
			'body'         => $notes['body'],
			'published_at' => $notes['published'],
			'assets'       => [
				[
					'name'                 => $asset['name'],
					'browser_download_url' => $asset['url'],
				],
			],
		],
	];
}

/**
 * Reads a release's asset from the fragment the release page uses to list its own downloads.
 *
 * @param string $base Repository URL.
 * @param string $tag Release tag.
 * @return array<string, string>|null
 */
function amehp_fetch_release_asset( $base, $tag ) {
	$response = amehp_request( $base . '/releases/expanded_assets/' . rawurlencode( $tag ) );

	if ( ! $response || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	if ( ! preg_match_all( '#href="(/[^"]*/releases/download/[^"]+\.zip)"#i', wp_remote_retrieve_body( $response ), $matches ) ) {
		return null;
	}

	// Take the first zip, matching what the API branch does with the assets list.
	$path = html_entity_decode( $matches[1][0], ENT_QUOTES, 'UTF-8' );

	return [
		'name' => rawurldecode( basename( $path ) ),
		'url'  => 'https://github.com' . $path,
	];
}

/**
 * Reads a release's notes and publication date from the releases feed.
 *
 * Only the changelog in the plugin details popup depends on this, so a failure here is not fatal.
 *
 * @param string $base Repository URL.
 * @param string $tag Release tag.
 * @return array<string, string>
 */
function amehp_fetch_release_notes( $base, $tag ) {
	$empty = [
		'body'      => '',
		'published' => '',
	];

	$response = amehp_request( $base . '/releases.atom' );

	if ( ! $response || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $empty;
	}

	if ( ! preg_match_all( '#<entry>(.*?)</entry>#s', wp_remote_retrieve_body( $response ), $entries ) ) {
		return $empty;
	}

	foreach ( $entries[1] as $entry ) {

		// Match the tag rather than taking the newest entry: the feed also carries pre-releases,
		// which the latest-release redirect deliberately skips.
		if ( false === strpos( $entry, '/releases/tag/' . $tag ) ) {
			continue;
		}

		$notes = '';

		if ( preg_match( '#<content[^>]*>(.*?)</content>#s', $entry, $content ) ) {
			$notes = amehp_release_notes_to_text( $content[1] );
		}

		$published = '';

		if ( preg_match( '#<updated>(.*?)</updated>#s', $entry, $updated ) ) {
			$published = trim( $updated[1] );
		}

		return [
			'body'      => $notes,
			'published' => $published,
		];
	}

	return $empty;
}

/**
 * Turns the rendered notes in the feed back into the plain text the API would have returned.
 *
 * The API hands back the release body as it was written, in Markdown, and the details popup prints
 * that as text. The feed carries the rendered HTML instead, so headings, bold runs and list items
 * are put back into their Markdown spelling to keep the popup reading the same either way.
 *
 * @param string $html Rendered notes.
 * @return string
 */
function amehp_release_notes_to_text( $html ) {
	$text = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );

	$text = preg_replace( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', "\n**$1**\n", $text );
	$text = preg_replace( '#<(strong|b)[^>]*>(.*?)</\1>#is', '**$2**', $text );
	$text = preg_replace( '#<(em|i)[^>]*>(.*?)</\1>#is', '*$2*', $text );
	$text = preg_replace( '#<li[^>]*>#i', "\n- ", $text );
	$text = preg_replace( '#</(p|div|ul|ol|li|pre|blockquote)>#i', "\n", $text );
	$text = preg_replace( '#<br\s*/?>#i', "\n", $text );

	$text = wp_strip_all_tags( (string) $text );

	// Collapse the blank lines the substitutions leave behind.
	$text = preg_replace( '#\n{3,}#', "\n\n", (string) $text );

	return trim( (string) $text );
}

/**
 * Reads the latest release from the GitHub API.
 *
 * Kept as a fallback only. See `amehp_fetch_release_data()` for why it is not the first choice.
 *
 * @return array<string, mixed>|null
 */
function amehp_fetch_release_from_api() {

	// GitHub has already said the allowance is spent, so sit the window out rather than spending it
	// on requests that can only be refused.
	if ( get_site_transient( AMEHP_UPDATE_RATE_LIMIT_KEY ) ) {
		set_site_transient( AMEHP_UPDATE_REASON_KEY, 'rate_limited', HOUR_IN_SECONDS );

		return null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . AMEHP_UPDATE_REPO . '/releases/latest',
		[
			'timeout'    => 10,
			'headers'    => [ 'Accept' => 'application/vnd.github+json' ],

			// Our own User-Agent, because WordPress's default is "WordPress/{version}; {site url}"
			// (wp-includes/class-wp-http.php:211) and that puts the site's address and its exact
			// WordPress version into every release check. GitHub only requires that the header
			// identifies something, so this satisfies it while telling them nothing about the site.
			'user-agent' => 'account-menu-enhancer-for-hivepress/' . AMEHP_VERSION,
		]
	);

	if ( is_wp_error( $response ) ) {
		set_site_transient( AMEHP_UPDATE_REASON_KEY, 'unreachable', HOUR_IN_SECONDS );

		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( 200 !== $code ) {
		$reason = 404 === $code ? 'no_release' : 'unreachable';

		// A 403 or 429 with nothing left on the counter means this server's hourly allowance is
		// spent. Nothing is wrong with the site, the plugin or the repository, so it must not be
		// reported as though something were.
		if ( ( 403 === $code || 429 === $code ) && '0' === (string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) ) {
			$reason = 'rate_limited';
			$reset  = (int) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
			$wait   = $reset > time() ? min( $reset - time(), HOUR_IN_SECONDS ) : 5 * MINUTE_IN_SECONDS;

			set_site_transient( AMEHP_UPDATE_RATE_LIMIT_KEY, $reset ? $reset : time() + $wait, $wait );
		}

		set_site_transient( AMEHP_UPDATE_REASON_KEY, $reason, HOUR_IN_SECONDS );

		return null;
	}

	delete_site_transient( AMEHP_UPDATE_RATE_LIMIT_KEY );
	delete_site_transient( AMEHP_UPDATE_REASON_KEY );

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Makes a request to github.com.
 *
 * The User-Agent is set for the same reason as in the API branch: WordPress's default would put the
 * site's address and its exact WordPress version into every check.
 *
 * @param string               $url Request URL.
 * @param array<string, mixed> $args Extra request arguments.
 * @return array<string, mixed>|null
 */
function amehp_request( $url, $args = [] ) {
	$response = wp_remote_get(
		$url,
		array_merge(
			[
				'timeout'    => 10,
				'headers'    => [ 'Accept' => 'text/html, application/xml;q=0.9, */*;q=0.8' ],
				'user-agent' => 'account-menu-enhancer-for-hivepress/' . AMEHP_VERSION,
			],
			$args
		)
	);

	return is_wp_error( $response ) ? null : $response;
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
		'donate_link'   => AMEHP_SUPPORT_URL,
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

	// Read why the lookup ended as it did rather than inferring it from the result. Since a failed
	// check now keeps the last good answer, the presence of a release no longer proves the check
	// itself succeeded, and reporting a stale answer as a fresh one would be a lie.
	$reason = get_site_transient( AMEHP_UPDATE_REASON_KEY );

	if ( 'no_release' === $reason ) {
		$status = 'empty';
	} elseif ( 'rate_limited' === $reason ) {
		$status = 'limited';
	} elseif ( 'unreachable' === $reason ) {
		$status = 'error';
	} elseif ( $release && version_compare( $release['version'], AMEHP_VERSION, '>' ) ) {
		$status = 'available';
	} else {
		$status = 'none';
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

	// The status flag only chooses which message to display, and the check it
	// reports on was already nonce verified before the redirect that set it.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['amehp_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$status = sanitize_key( wp_unslash( $_GET['amehp_checked'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'available' === $status ) {
		$release = amehp_get_latest_release();

		/* translators: %s: new version number. */
		$message = sprintf( __( 'A new version of Account Menu Enhancer for HivePress (%s) is available.', 'account-menu-enhancer-for-hivepress' ), $release ? $release['version'] : '' );
		$class   = 'notice-success';
	} elseif ( 'none' === $status ) {
		$message = __( 'Account Menu Enhancer for HivePress is up to date.', 'account-menu-enhancer-for-hivepress' );
		$class   = 'notice-success';
	} elseif ( 'empty' === $status ) {
		$message = __( 'No releases have been published for Account Menu Enhancer for HivePress yet, so there is nothing to update to. This is normal for a brand new copy and does not mean anything is wrong.', 'account-menu-enhancer-for-hivepress' );
		$class   = 'notice-info';
	} elseif ( 'limited' === $status ) {
		$message = __( 'GitHub limits how many update checks one server may make each hour, and this server has reached that limit. Nothing is wrong with the plugin or your site, and checking will work again within the hour.', 'account-menu-enhancer-for-hivepress' );
		$class   = 'notice-warning';
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

/**
 * Adds the house "Donate" link to this plugin's row on the Plugins screen.
 *
 * WordPress fires plugin_row_meta for EVERY plugin on the screen, so without the basename
 * test the link would appear on every row on the site. The markup is copied verbatim from
 * the house spec in `releasing.md` rather than composed here: every plugin's row has to look
 * identical and sessions have drifted before. The label is exactly "Donate", matching the
 * wording WordPress itself uses in the details popup, and the icon is a Dashicon rather than
 * Font Awesome because Dashicons is the admin's own font and is always loaded there.
 * WordPress joins row-meta items with " | " itself, so this returns a bare anchor.
 *
 * @param array<string> $meta        Row meta links.
 * @param string        $plugin_file Plugin file the row belongs to.
 * @return array<string>
 */
function amehp_add_row_meta( $meta, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) === $plugin_file ) {
		$meta[] = '<a href="' . esc_url( AMEHP_SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">'
			. '<span class="dashicons dashicons-star-filled" style="font-size:14px;line-height:1.3;"></span> '
			. esc_html__( 'Donate', 'account-menu-enhancer-for-hivepress' )
			. '</a>';
	}

	return $meta;
}

add_filter( 'plugin_row_meta', 'amehp_add_row_meta', 10, 2 );
