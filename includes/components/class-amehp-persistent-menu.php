<?php
/**
 * Persistent account menu component.
 *
 * Keeps the HivePress account menu items visible even when they are empty and
 * replaces each empty page with a helpful notice, icon and button. This is the
 * former Persistent Account Menu for HivePress plugin, folded into Account
 * Menu Enhancer in version 3.0.0. The stored hp_hppam_* options are migrated
 * to hp_amehp_* keys by amehp_maybe_migrate(), and every read below also falls
 * back to the legacy key so a front-end request before the migration has run
 * still honours the owner's saved choices.
 *
 * @package AccountMenuEnhancer\Components
 */

namespace HivePress\Components;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Keeps the account menu items visible when their pages are empty.
 */
final class Amehp_Persistent_Menu extends Component {

	/**
	 * The highest filter priority the probe still listens to.
	 *
	 * Every data-driven adder in the HivePress ecosystem registers at or
	 * below 100. Verified in the 1.7.28 reference on 2026-08-24: core at the
	 * default 10 (hivepress/includes/components/class-listing.php:70), the
	 * official extensions likewise at 10 (hivepress-messages
	 * /includes/components/class-message.php:62, hivepress-favorites
	 * /class-favorite.php:39, hivepress-bookings/class-booking.php:172,
	 * hivepress-requests/class-offer.php:103, hivepress-search-alerts
	 * /class-search-alert.php:48, hivepress-memberships
	 * /class-membership.php:140), Marketplace at 100
	 * (hivepress-marketplace/class-marketplace.php:136) and Vendor Analytics'
	 * own adder at 100. Every filter that expresses the OWNER'S PREFERENCE
	 * rather than the presence of data sits above it: Vendor Analytics hides
	 * Marketplace's dashboard at 200, this plugin's menu enhancer hides at
	 * 1000, and the forcing below runs at 500. If a future extension ever
	 * ADDS an item above this ceiling, its page would be judged empty and
	 * given the notice; raise the ceiling above it, or answer for that one
	 * item through the `amehp/persistent_native_item` filter.
	 */
	const PROBE_PRIORITY_CEILING = 100;

	/**
	 * The filters the probe reduces.
	 *
	 * Both menu filter stages have to be covered, and both class names with
	 * them. `Menu::__construct()` applies `hivepress/v1/menus/{name}` for the
	 * whole class chain (hivepress/includes/menus/class-menu.php:94) and
	 * `boot()` then applies `hivepress/v1/menus/{name}/items` the same way
	 * (:125), so for the account menu that is four hook names, not one. An
	 * item can be hidden at any of them: the menu enhancer component uses two
	 * of the four.
	 *
	 * THE FIFTH IS NOT A MENU HOOK, AND IT IS HERE BECAUSE THE PROBE TURNED
	 * OUT TO BE RE-ENTRANT. Core reads the WooCommerce account rows from
	 * inside its own account menu filter, only to label the Orders and
	 * Subscriptions items (hivepress/includes/components/class-woocommerce.php
	 * :441 and :449, registered at the default 10 at :80, so the reduction
	 * keeps it). The menu enhancer component answers on that WooCommerce hook
	 * at 999 by building a SECOND account menu and memoising it on its own
	 * component for the rest of the request. Left alone, that second build
	 * runs inside the probe with the hooks already reduced, so the owner's
	 * hidden items are baked into a cache the real WooCommerce menu then
	 * renders from. Reducing this hook as well lifts that 999 callback for
	 * the duration, so nothing re-enters and the cache is left for the real
	 * render to fill. Backtraced and proved by execution on 2026-08-24, when
	 * the two components were still separate plugins; keeping both in one
	 * plugin does not change the mechanics.
	 */
	const PROBE_HOOKS = [
		'hivepress/v1/menus/menu',
		'hivepress/v1/menus/menu/items',
		'hivepress/v1/menus/user_account',
		'hivepress/v1/menus/user_account/items',
		'woocommerce_account_menu_items',
	];

	/**
	 * Default managed items cache.
	 *
	 * @var array|null
	 */
	protected $default_items;

	/**
	 * Managed items cache, before the developer filter.
	 *
	 * @var array|null
	 */
	protected $items;

	/**
	 * The stored selection the cache above was built from.
	 *
	 * Held so the cache can be dropped when reconcile_items() moves the
	 * selection mid-request. See get_items().
	 *
	 * @var mixed
	 */
	protected $items_selection;

	/**
	 * Whether the native menu is being probed right now.
	 *
	 * @var bool
	 */
	protected $probing = false;

	/**
	 * Whether the current user has a vendor profile, cached per request.
	 *
	 * @var bool|null
	 */
	protected $vendor;

	/**
	 * Native menu items keyed by user ID.
	 *
	 * @var array
	 */
	protected $native_items = [];

	/**
	 * Class constructor.
	 *
	 * @param array $args Component arguments.
	 */
	public function __construct( $args = [] ) {

		// Add the settings sections.
		add_filter( 'hivepress/v1/settings', [ $this, 'alter_settings' ] );

		// Neutralise the empty-page bounce on the managed routes.
		add_filter( 'hivepress/v1/routes', [ $this, 'alter_routes' ], 500 );

		// Force the managed items into the account menu.
		add_filter( 'hivepress/v1/menus/user_account', [ $this, 'alter_account_menu' ], 500 );

		// Add the empty-state notice to the managed account pages. The vendor
		// calendar is the one managed page outside the account template chain,
		// as Bookings' `Vendor_Calendar_Page` extends `Page_Wide` rather than
		// `User_Account_Page`, so its template filter is hooked directly.
		add_filter( 'hivepress/v1/templates/user_account_page', [ $this, 'alter_account_page' ], 200 );
		add_filter( 'hivepress/v1/templates/vendor_calendar_page', [ $this, 'alter_account_page' ], 200 );

		if ( ! is_admin() ) {

			// Enqueue the notice styles.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		} else {

			// Reconcile the saved menu items with the offered choices.
			add_action( 'admin_init', [ $this, 'reconcile_items' ] );
		}

		parent::__construct( $args );
	}

	/*
	-------------------------------------------------------------------------
	Options
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets an option value, falling back to its former hp_hppam_* key.
	 *
	 * The migration in amehp_maybe_migrate() runs on admin_init, so a
	 * front-end request on an upgraded site can arrive before it has ever
	 * run. Reading the legacy key whenever the new one is absent keeps the
	 * owner's saved choices working in that window, and also while the old
	 * Persistent Account Menu plugin is still installed alongside.
	 *
	 * @param string $name New option name.
	 * @param string $legacy_name Legacy option name.
	 * @return mixed
	 */
	protected function get_option_value( $name, $legacy_name ) {
		$value = get_option( $name, null );

		if ( null === $value ) {
			$value = get_option( $legacy_name, null );
		}

		return $value;
	}

	/*
	-------------------------------------------------------------------------
	Managed items
	-------------------------------------------------------------------------
	*/

	/**
	 * Gets the default managed menu items.
	 *
	 * Routes, orders, display conditions, empty-page redirects and page
	 * block names are source-verified against HivePress 1.7.27,
	 * Favorites 1.2.2, Messages 1.4.0, Bookings 1.5.5, Marketplace 1.3.15,
	 * Memberships 2.2.0, Requests 1.2.5 and Search Alerts 1.1.3. Items
	 * whose route is not registered (extension inactive) are skipped
	 * automatically. Titles are only used for the settings screen.
	 *
	 * @return array
	 */
	protected function get_default_items() {
		if ( null !== $this->default_items ) {
			return $this->default_items;
		}

		$items = [
			'listings_edit'      => [
				'title'  => __( 'Listings', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'listings_edit_page',
				'_order' => 10,
				'notice' => [
					'icon'   => 'list',
					'text'   => __( "You haven't added any listings yet. Once you add your first listing, you can return to this page to view, edit and manage it.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Add listing', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listing_submit_page',
					],
					'blank'  => [ 'listings' ],
				],
			],

			'requests_edit'      => [
				'title'  => __( 'Requests', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'requests_edit_page',
				'_order' => 10,
				'notice' => [
					'icon'   => 'tasks',
					'text'   => __( "You haven't posted any requests yet. Once you post a request, you can return to this page to manage it and review offers.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Post a request', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'request_submit_page',
					],
					'blank'  => [ 'requests' ],
				],
			],

			'offers_view'        => [
				'title'  => __( 'Offers', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'offers_view_page',
				'_order' => 15,
				'notice' => [
					'icon'   => 'tags',
					'text'   => __( "You haven't made any offers yet. When you make an offer on a request, it will appear here.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Browse requests', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'requests_view_page',
					],
					'blank'  => [ 'offers' ],
				],
			],

			'listings_favorite'  => [
				'title'  => __( 'Favorites', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'listings_favorite_page',
				'_order' => 20,
				'notice' => [
					'icon'   => 'heart',
					'text'   => __( "You haven't added any listings to your favourites yet. Once you click the heart icon on a listing, you can return to this page to find the listing more easily next time.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Browse listings', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listings_view_page',
					],
					'blank'  => [ 'listings' ],
				],
			],

			'vendor_calendar'    => [
				'title'  => __( 'Calendar (vendors)', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'vendor_calendar_page',
				'_order' => 25,
				'vendor' => true,
				'notice' => [
					'icon'   => 'calendar-alt',
					'text'   => __( 'Your calendar shows the bookings made for your listings. Add a listing to get started.', 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Add listing', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listing_submit_page',
					],
					'blank'  => [],
				],
			],

			'search_alerts_view' => [
				'title'  => __( 'Saved Searches', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'search_alerts_view_page',
				'_order' => 25,
				'notice' => [
					'icon'   => 'search',
					'text'   => __( "You haven't saved any searches yet. Save a search to be notified when new matching listings are added.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Browse listings', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listings_view_page',
					],
					'blank'  => [ 'search_alerts' ],
				],
			],

			'bookings_view'      => [
				'title'  => __( 'Bookings', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'bookings_view_page',
				'_order' => 27,
				'notice' => [
					'icon'   => 'calendar-check',
					'text'   => __( "You don't have any bookings yet. When you make or receive a booking, the details will appear here.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Browse listings', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listings_view_page',
					],
					'blank'  => [ 'bookings' ],
				],
			],

			'messages_thread'    => [
				'title'   => __( 'Messages', 'account-menu-enhancer-for-hivepress' ),
				'route'   => 'messages_thread_page',
				'_order'  => 30,
				'enabled' => [ $this, 'is_message_storage_enabled' ],
				'notice'  => [
					'icon'   => 'comments',
					'text'   => __( "You haven't exchanged any messages yet. When you send or receive a message, the conversation will appear here.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'Browse listings', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'listings_view_page',
					],
					'blank'  => [ 'messages' ],
				],
			],

			'memberships_view'   => [
				'title'  => __( 'Membership', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'memberships_view_page',
				'_order' => 35,
				'notice' => [
					'icon'   => 'id-card',
					'text'   => __( "You don't have a membership yet. Choose a plan to get started.", 'account-menu-enhancer-for-hivepress' ),
					'button' => [
						'label' => __( 'View plans', 'account-menu-enhancer-for-hivepress' ),
						'route' => 'membership_plans_view_page',
					],
					'blank'  => [ 'memberships' ],
				],
			],

			'orders_edit'        => [
				'title'  => __( 'Received Orders (vendors)', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'orders_edit_page',
				'_order' => 35,
				'vendor' => true,
				'notice' => [
					'icon'  => 'shopping-cart',
					'text'  => __( "You haven't received any orders yet. When a customer places an order with you, it will appear here.", 'account-menu-enhancer-for-hivepress' ),
					'blank' => [ 'orders' ],
				],
			],

			'payouts_view'       => [
				'title'  => __( 'Payouts (vendors)', 'account-menu-enhancer-for-hivepress' ),
				'route'  => 'payouts_view_page',
				'_order' => 45,
				'vendor' => true,
				'notice' => [
					'icon'  => 'money-bill',
					'text'  => __( "You don't have any payouts yet. Once you request a payout, its status will appear here.", 'account-menu-enhancer-for-hivepress' ),
					'blank' => [ 'payouts' ],
				],
			],
		];

		/*
		 * The WooCommerce items that HivePress core links into the account
		 * menu. Their pages already render native WooCommerce empty states,
		 * so no notice is set - and, deliberately, NO WooCommerce function
		 * is called here. This method first runs from the routes filter on
		 * `init`, before `wp_loaded`, and `wc_get_account_menu_items()`
		 * loads the available payment gateways to decide its own items -
		 * on a real site a gateway-conditions plugin then touches the cart,
		 * and WooCommerce logs a doing-it-wrong for every request (found on
		 * the clone site: ~50 KB of `get_cart` notices per page view,
		 * chain alter_routes > wc_get_account_menu_items >
		 * get_available_payment_gateways > WC_Cart::get_cart). Only the
		 * endpoint slug is recorded; the live label and URL are resolved at
		 * menu-build time in `alter_account_menu()`, which is when core's
		 * own WooCommerce component calls these functions too.
		 */
		if ( function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_account_menu_items' ) ) {
			$items['orders_view'] = [
				'title'       => __( 'Orders (WooCommerce)', 'account-menu-enhancer-for-hivepress' ),
				'label'       => __( 'Orders', 'account-menu-enhancer-for-hivepress' ),
				'wc_endpoint' => 'orders',
				'_order'      => 40,
			];

			if ( class_exists( 'WC_Subscriptions' ) ) {
				$items['subscriptions_view'] = [
					'title'       => __( 'Subscriptions (WooCommerce)', 'account-menu-enhancer-for-hivepress' ),
					'label'       => __( 'Subscriptions', 'account-menu-enhancer-for-hivepress' ),
					'wc_endpoint' => 'subscriptions',
					'_order'      => 42,
				];
			}
		}

		$this->default_items = $items;

		return $items;
	}

	/**
	 * Gets the managed menu items.
	 *
	 * Applies the admin selection from the settings, then the developer
	 * filter. Items the admin chose not to force are left completely
	 * untouched and keep the stock behaviour.
	 *
	 * WHAT IS CACHED HERE AND WHAT IS NOT, because getting this wrong either
	 * way has already been a bug.
	 *
	 * The BUILD is cached. This method is called from the routes filter, the
	 * menu filter, the template filter, the stylesheet enqueue and the probe's
	 * fail-safe, so a signed-in page view ran it between five and sixteen
	 * times - measured on 2026-08-30 - and every run made about sixty-six
	 * get_option() calls for an answer that had not moved. That cost 1.1ms to
	 * 2.5ms of every page view for nothing.
	 *
	 * The FILTER is not cached, and must not be. `amehp/persistent_items` is a
	 * public extension point and a callback on it is entitled to answer
	 * differently for the page being rendered than for the one being probed,
	 * so it is applied on every call exactly as before. Nothing stores what it
	 * returns, so a callback altering the list still cannot affect the next
	 * caller - which was true before this cache and stays true.
	 *
	 * The selection is re-read on every call and the cache dropped when it has
	 * moved, because `reconcile_items()` can write it mid-request (while the
	 * routes are being built, no less) and a cache that missed that would
	 * serve the pre-reconciliation list for the rest of the request. That is
	 * two get_option() calls instead of sixty-six, not none.
	 *
	 * @return array
	 */
	protected function get_items() {

		// Keep only the items enabled in the settings. Until the setting is
		// saved for the first time, every item is managed.
		$enabled = $this->get_option_value( 'hp_amehp_persistent_items', 'hp_hppam_items' );

		if ( null !== $this->items && $enabled === $this->items_selection ) {
			/** This filter is documented below. */
			return apply_filters( 'amehp/persistent_items', $this->items );
		}

		$items = $this->get_default_items();

		if ( null !== $enabled ) {
			$items = array_intersect_key( $items, array_flip( array_filter( (array) $enabled ) ) );
		}

		/*
		 * Apply the owner's customisations. Every one of them is an override
		 * of something that already works: a blank field means "keep what
		 * this page shows now", never an empty page.
		 */
		foreach ( $items as $name => $item ) {
			if ( ! isset( $item['notice'] ) ) {
				continue;
			}

			// The chosen icon is carried by NAME, alongside the built-in
			// codepoint rather than over it, so render_notice() can tell an
			// owner's choice from the default and look up the right font for
			// it - a brand icon is in a different font from a solid one.
			$icon = get_option( 'hp_amehp_page_icon_' . $name );

			if ( is_string( $icon ) && preg_match( '/^[a-z0-9-]+$/', $icon ) ) {
				$items[ $name ]['notice']['icon_name'] = $icon;
			}

			$text = get_option( 'hp_amehp_page_text_' . $name );

			if ( is_string( $text ) && '' !== trim( $text ) ) {
				$items[ $name ]['notice']['text'] = $text;
			}

			$label = $this->get_option_value( 'hp_amehp_button_label_' . $name, 'hp_hppam_button_label_' . $name );

			if ( $label ) {
				$items[ $name ]['notice']['button']['label'] = $label;
			}

			$url = $this->get_option_value( 'hp_amehp_button_url_' . $name, 'hp_hppam_button_url_' . $name );

			if ( $url ) {
				$items[ $name ]['notice']['button']['url'] = $url;

				unset( $items[ $name ]['notice']['button']['route'] );
			}
		}

		$this->items           = $items;
		$this->items_selection = $enabled;

		/**
		 * Filters the menu items kept visible when they are empty.
		 *
		 * Unset an item here to stop forcing it, or adjust its notice text,
		 * icon codepoint and button. The admin selection from the settings
		 * is already applied at this point.
		 *
		 * Hook name: "amehp/persistent_items". Before version 3.0.0 this was
		 * the "hppam/v1/items" hook of the Persistent Account Menu plugin.
		 *
		 * @param array $items Menu items.
		 */
		return apply_filters( 'amehp/persistent_items', $items );
	}

	/**
	 * Gets the menu item choices for the settings field.
	 *
	 * Only items whose extension is currently active are offered.
	 *
	 * @return array
	 */
	public function get_item_options() {
		$options = [];

		foreach ( $this->get_default_items() as $name => $item ) {

			// Skip items whose extension is inactive.
			if ( isset( $item['route'] ) && ! hivepress()->router->get_route( $item['route'] ) ) {
				continue;
			}

			$options[ $name ] = hp\get_array_value( $item, 'title', $name );
		}

		return $options;
	}

	/**
	 * Reconciles the saved menu items with the currently offered choices.
	 *
	 * A `checkboxes` setting stores only the ticked list, which freezes the
	 * set of choices that existed when it was saved. A choice that appears
	 * later, because an extension was activated or a plugin update added an
	 * item, is absent from the stored value, and absent reads as deliberately
	 * unticked, so the new item would stay off with nothing saying a feature
	 * arrived. A separate record of every choice already offered tells the
	 * two apart: anything offered but not recorded is new, so it is switched
	 * on and written into both options, keeping the settings screen, the
	 * record and the behaviour in agreement.
	 *
	 * Runs on `admin_init`, deliberately, rather than on every request. The
	 * check has to write two options when it finds something new, and a
	 * front-end hook would put that write behind an unauthenticated request:
	 * on a busy site every visitor arriving after an extension is activated
	 * would race the same read-modify-write. Until an admin loads any admin
	 * page, a newly offered item simply behaves as unticked, which is the
	 * stock HivePress behaviour rather than a fault.
	 */
	public function reconcile_items() {
		$enabled = $this->get_option_value( 'hp_amehp_persistent_items', 'hp_hppam_items' );

		// Until the setting is saved every item is forced, so there is
		// nothing to reconcile; the record starts with the first save.
		if ( null === $enabled ) {
			return;
		}

		$offered = array_keys( $this->get_item_options() );
		$known   = $this->get_option_value( 'hp_amehp_persistent_known_items', 'hp_hppam_known_items' );

		if ( null === $known ) {

			// Seed the record from the current choices, so nothing the
			// admin already unticked is switched back on.
			update_option( 'hp_amehp_persistent_known_items', $offered );

			return;
		}

		$new_items = array_diff( $offered, (array) $known );

		if ( ! $new_items ) {
			return;
		}

		update_option( 'hp_amehp_persistent_items', array_values( array_unique( array_merge( array_filter( (array) $enabled ), $new_items ) ) ) );
		update_option( 'hp_amehp_persistent_known_items', array_values( array_unique( array_merge( (array) $known, $new_items ) ) ) );
	}

	/**
	 * Gets the managed pages whose template the admin has customised.
	 *
	 * `Blocks\Template::render()` has two paths: when any `hp_template` post
	 * is published and one matches this template's name, the page renders
	 * that saved editor content and the template class's own block tree is
	 * never used (`blocks/class-template.php:47-92`). The notice is injected
	 * into that block tree, so on those pages it cannot appear, with nothing
	 * in the logs to say why. The menu item and the empty-page bounce
	 * suppression are unaffected, since neither goes through the template.
	 *
	 * The template name equals the route name for these pages (core builds
	 * the class as `\HivePress\Templates\{route}`,
	 * `components/class-template.php:220`).
	 *
	 * @return array Item titles, for the settings screen.
	 */
	protected function get_overridden_pages() {
		$titles = [];

		$counts = wp_count_posts( 'hp_template' );

		// Cheap gate, exactly the one core uses before querying.
		if ( ! $counts || ! $counts->publish ) {
			return $titles;
		}

		$templates = [];

		foreach ( $this->get_items() as $item ) {
			if ( isset( $item['notice'], $item['route'] ) ) {
				$templates[ $item['route'] ] = hp\get_array_value( $item, 'title', $item['route'] );
			}
		}

		if ( ! $templates ) {
			return $titles;
		}

		$overridden = get_posts(
			[
				'post_type'        => 'hp_template',
				'post_status'      => 'publish',
				'post_name__in'    => array_keys( $templates ),
				'posts_per_page'   => count( $templates ),
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);

		foreach ( $overridden as $post_id ) {
			$name = get_post_field( 'post_name', $post_id );

			if ( isset( $templates[ $name ] ) ) {
				$titles[] = $templates[ $name ];
			}
		}

		return $titles;
	}

	/*
	-------------------------------------------------------------------------
	Settings
	-------------------------------------------------------------------------
	*/

	/**
	 * Adds the persistent menu sections to the plugin settings tab.
	 *
	 * The sections join the Account Menu tab registered by the settings
	 * config, so everything this plugin does is configured in one place.
	 *
	 * @param array $settings Settings configuration.
	 * @return array
	 */
	public function alter_settings( $settings ) {
		if ( ! isset( $settings['account_menu']['sections'] ) ) {
			return $settings;
		}

		$options = $this->get_item_options();

		$description = __( 'Tick the account menu items that should stay visible even when their pages are empty. Empty pages then show a short notice and a button instead of disappearing. Unticked items keep the default behaviour.', 'account-menu-enhancer-for-hivepress' );

		// Warn about pages whose template has been customised, where the
		// notice cannot be shown. Silence here would read as a plugin fault.
		$overridden = $this->get_overridden_pages();

		if ( $overridden ) {
			$description .= ' ' . sprintf(
				/* translators: %s: comma-separated list of page names. */
				__( 'Note: these pages are customised under HivePress > Templates, so they show your own layout instead of the notice: %s. Delete the template to use the notice again.', 'account-menu-enhancer-for-hivepress' ),
				implode( ', ', $overridden )
			);
		}

		$settings['account_menu']['sections']['persistent'] = [
			'title'       => __( 'Persistent Menu Items', 'account-menu-enhancer-for-hivepress' ),
			'description' => $description,
			'_order'      => 30,

			'fields'      => [
				'amehp_persistent_items' => [
					'label'       => __( 'Visible Items', 'account-menu-enhancer-for-hivepress' ),
					'description' => __( 'Tick an item to keep it visible even when its page is empty.', 'account-menu-enhancer-for-hivepress' ),
					'type'        => 'checkboxes',
					'options'     => $options,
					'default'     => array_keys( $options ),
					'_order'      => 10,
				],
			],
		];

		/*
		 * The placeholder pages, four settings each.
		 *
		 * One section holding every page, rather than a section per page:
		 * with eleven or more pages the tab's own quick-links bar would be
		 * nothing but page names. backend.js folds each page's four fields
		 * into a group headed by the page name, using the same chevron as the
		 * repeater cards, so the section reads as a list of pages that open.
		 *
		 * The two button field names are unchanged from 3.0.0 on purpose:
		 * they hold the wording the owner has already saved, so the section
		 * can be renamed and rebuilt around them without anybody's buttons
		 * changing. The icon and text fields are new and empty, and empty
		 * means "what this page did before", never a blank page.
		 */
		$fields = [];
		$order  = 10;

		foreach ( $this->get_default_items() as $name => $item ) {
			if ( ! isset( $item['notice'] ) || ! isset( $options[ $name ] ) ) {
				continue;
			}

			$button = hp\get_array_value( $item['notice'], 'button' );

			/*
			 * The labels name the setting only, not the page.
			 *
			 * Each page's four fields are moved into a card headed by that
			 * page's own name (backend.js), so "Listings: Icon" inside a card
			 * called Listings said it twice - and the repeated page name was
			 * what made these read as ordinary settings rows belonging to the
			 * section rather than to the page. Reported from the screen on
			 * 2026-08-30.
			 */
			$fields[ 'amehp_page_icon_' . $name ] = [
				'label'       => __( 'Icon', 'account-menu-enhancer-for-hivepress' ),
				'description' => __( 'The large icon shown on the empty page. It takes the icon colour, weight and size from the Appearance section. Leave it empty to keep the default icon.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'select',
				'options'     => 'amehp_icons',

				// Loaded over AJAX from the shared library, like every other icon picker on this
				// tab. See the note above $amehp_icon_source in configs/settings.php.
				'source'      => class_exists( 'FAFH' ) ? \FAFH::picker_source() : '',
				'placeholder' => __( 'Default Icon', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => $order,

				'attributes'  => [
					'data-template' => 'icon',
				],
			];

			$fields[ 'amehp_page_text_' . $name ] = [
				'label'       => __( 'Page text', 'account-menu-enhancer-for-hivepress' ),
				'description' => __( 'The message shown on the empty page. Leave it blank to keep the default wording.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'textarea',
				'max_length'  => 500,
				'placeholder' => hp\get_array_value( $item['notice'], 'text', '' ),
				'_order'      => $order + 2,
			];

			$fields[ 'amehp_button_label_' . $name ] = [
				'label'       => __( 'Button label', 'account-menu-enhancer-for-hivepress' ),
				'description' => $button
					? __( 'The text shown on the button. Leave it blank to keep the default.', 'account-menu-enhancer-for-hivepress' )
					: __( 'This page has no button by default. Set both a label and a URL to add one.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'text',
				'max_length'  => 100,
				'placeholder' => $button ? hp\get_array_value( $button, 'label', '' ) : '',
				'_order'      => $order + 4,
			];

			/*
			 * A URL field, not a text field.
			 *
			 * A text field sanitises with sanitize_text_field(), which strips
			 * percent-encoded octets outright: "/a%20b?x=1" was stored as
			 * "/ab?x=1" and "?q=caf%C3%A9" as "?q=caf", silently, with
			 * validation passing. Fields\URL sanitises with esc_url_raw()
			 * instead, which keeps them. See hivepress-settings.md, "Never
			 * store a URL in a text field".
			 *
			 * display_type is forced back to text so the control stays a
			 * plain text box. Left to itself the field renders
			 * <input type="url">, whose browser validation demands a scheme
			 * and would reject the relative path this setting documents and
			 * get_custom_item_url() resolves - the save would simply refuse
			 * with a browser tooltip. esc_url_raw() keeps a leading-slash
			 * path intact, so only the HTML control needed changing.
			 */
			$fields[ 'amehp_button_url_' . $name ] = [
				'label'        => __( 'Button URL', 'account-menu-enhancer-for-hivepress' ),
				'description'  => __( 'A full URL or a relative path like /listings. Leave it blank to keep the default link.', 'account-menu-enhancer-for-hivepress' ),
				'type'         => 'url',
				'display_type' => 'text',
				'max_length'   => 2048,
				'_order'       => $order + 6,
			];

			$order += 10;
		}

		if ( $fields ) {
			$settings['account_menu']['sections']['persistent_buttons'] = [
				'title'       => __( 'Placeholder Pages', 'account-menu-enhancer-for-hivepress' ),
				'description' => __( 'Customise what an account page shows while it is still empty. Each page below has its own icon, message and button. Leave a field blank to keep what that page shows now.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 40,
				'fields'      => $fields,
			];
		}

		return $settings;
	}

	/**
	 * Gets the placeholder page names, for the settings screen script.
	 *
	 * The script folds this section's fields into one group per page, and it
	 * matches a field to its page by the suffix on the field name. The titles
	 * come from here so the group headings read the same as the rest of the
	 * screen.
	 *
	 * @return array Page key mapped to its title.
	 */
	public function get_placeholder_pages() {
		$pages   = [];
		$options = $this->get_item_options();

		foreach ( $this->get_default_items() as $name => $item ) {
			if ( isset( $item['notice'], $options[ $name ] ) ) {
				$pages[ $name ] = (string) hp\get_array_value( $item, 'title', $name );
			}
		}

		return $pages;
	}

	/*
	-------------------------------------------------------------------------
	Conditions
	-------------------------------------------------------------------------
	*/

	/**
	 * Checks if message storage is enabled.
	 *
	 * The Messages page route redirects away unconditionally when storage is
	 * disabled, so the item is only forced when storage is on.
	 *
	 * @return bool
	 */
	public function is_message_storage_enabled() {
		return (bool) get_option( 'hp_message_enable_storage' );
	}

	/**
	 * Checks if the current user has a pending or published vendor profile.
	 *
	 * Used to force vendor-only items for vendors regardless of whether they
	 * have data yet. Draft profiles are excluded on purpose, since those are
	 * abandoned registration attempts rather than real vendors. Core's
	 * `vendor_id` request context is capability-gated, so the vendor profile
	 * is queried directly and cached per request.
	 *
	 * @return bool
	 */
	protected function is_vendor() {
		if ( null === $this->vendor ) {
			$this->vendor = (bool) ( is_user_logged_in() && class_exists( '\HivePress\Models\Vendor' ) && \HivePress\Models\Vendor::query()->filter(
				[
					'status__in' => [ 'pending', 'publish' ],
					'user'       => get_current_user_id(),
				]
			)->get_first_id() );
		}

		return $this->vendor;
	}

	/*
	-------------------------------------------------------------------------
	Native menu probe
	-------------------------------------------------------------------------
	*/

	/**
	 * Lifts the owner's menu customisers out of the way for one probe.
	 *
	 * WHY THIS EXISTS. The probe asks "does this account page have anything
	 * to show?" and reads the answer off the native menu, because a
	 * data-driven extension only adds its item when there is data behind it.
	 * A filter that hides an item because the owner asked for it hidden runs
	 * on the same hooks and is indistinguishable from here: the item is
	 * simply gone. Reading that as an absence of DATA is what shipped as a
	 * bug. A site owner who hid "Listings" in the menu enhancer found that
	 * the Listings page itself, reached by bookmark or by any link outside
	 * the menu, showed the "no listings yet" notice with its real listing
	 * table blanked, while the vendor had six listings. Reproduced on
	 * 2026-08-24. The rule: a late customiser expressing the owner's
	 * PREFERENCE must never be read as an absence of DATA.
	 *
	 * HOW, AND THE TRAP IN DOING IT THE OBVIOUS WAY. The unwanted callbacks
	 * are NOT unset from `$wp_filter[ $hook ]->callbacks`. Since WordPress
	 * 6.4 `WP_Hook` caches its priority list in its own protected
	 * `$priorities` property (wp-includes/class-wp-hook.php:44) and
	 * `::apply_filters()` iterates THAT, not the callbacks array (:335), so
	 * unsetting a priority behind its back leaves apply_filters() reading a
	 * key that no longer exists and PHP 8 fatals on the foreach. Instead a
	 * fresh `WP_Hook` is built through the public `add_filter()` with only
	 * the kept callbacks in it and swapped in for the duration. The original
	 * object is never touched, so restoring it is a single assignment, and
	 * any menu filter already part way through carries on iterating the
	 * object it started on.
	 *
	 * @param array $suspended Replaced hook objects, filled in as the swap proceeds.
	 */
	protected function suspend_menu_preferences( &$suspended ) {
		if ( ! isset( $GLOBALS['wp_filter'] ) || ! is_array( $GLOBALS['wp_filter'] ) ) {
			return;
		}

		foreach ( self::PROBE_HOOKS as $hook ) {
			$original = hp\get_array_value( $GLOBALS['wp_filter'], $hook );

			if ( ! $original instanceof \WP_Hook ) {
				continue;
			}

			$reduced = new \WP_Hook();
			$lifted  = false;

			foreach ( $original->callbacks as $priority => $callbacks ) {

				// A priority that is not a number cannot be compared against
				// the ceiling, so it is kept. Keeping a callback is the
				// direction that changes nothing.
				if ( is_numeric( $priority ) && $priority > self::PROBE_PRIORITY_CEILING ) {
					$lifted = true;

					continue;
				}

				foreach ( $callbacks as $callback ) {

					// The priority is handed back exactly as it was keyed and
					// never cast, so the rebuilt hook orders identically.
					$reduced->add_filter( $hook, $callback['function'], $priority, $callback['accepted_args'] );
				}
			}

			if ( ! $lifted ) {
				continue;
			}

			// Recorded BEFORE the swap, into the caller's array by reference,
			// so that a throw part way through this loop still leaves the
			// caller holding every hook that has actually been replaced.
			$suspended[ $hook ] = $original;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Swapped, not overwritten: the original object is held in $suspended and put back by restore_menu_preferences() in the caller's finally, which the sniff cannot see.
			$GLOBALS['wp_filter'][ $hook ] = $reduced;
		}
	}

	/**
	 * Puts the suspended menu customisers back.
	 *
	 * Restoration is exact by construction: the original `WP_Hook` objects
	 * were set aside rather than modified, so this puts the same objects back
	 * where they were. It has to run even when the probe throws, which is why
	 * the caller does it in a `finally`.
	 *
	 * @param array $suspended Replaced hook objects.
	 */
	protected function restore_menu_preferences( $suspended ) {
		foreach ( $suspended as $hook => $original ) {

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This IS the restore: it puts back the untouched object that suspend_menu_preferences() set aside a moment ago.
			$GLOBALS['wp_filter'][ $hook ] = $original;
		}
	}

	/**
	 * Checks if an item is present in the native account menu.
	 *
	 * Extensions only add their item when there is data to show, so a missing
	 * item means the page is empty. The native menu is built once per user
	 * per request, with the forcing suppressed and the owner's own menu
	 * customisers lifted out of the way - see `suspend_menu_preferences()`
	 * for why that second part decides whether a page keeps its content.
	 * Guarded against third-party route title callables that are unsafe
	 * outside their own context.
	 *
	 * The answer is cached per user id rather than per request, because
	 * anything that changes the current user mid-request (WP-CLI, a REST
	 * handler, a scheduled task calling `wp_set_current_user()`) would
	 * otherwise have one visitor's menu answered from another's, and this
	 * answer decides whether a page's real content is blanked.
	 *
	 * @param string $name Menu item name.
	 * @return bool
	 */
	protected function is_native_item( $name ) {
		$user_id = get_current_user_id();

		if ( ! isset( $this->native_items[ $user_id ] ) ) {
			$items     = null;
			$suspended = [];

			try {
				$this->suspend_menu_preferences( $suspended );

				$this->probing = true;

				$items = ( new \HivePress\Menus\User_Account() )->get_items();
			} catch ( \Throwable $throwable ) {
				$items = null;
			} finally {
				$this->probing = false;

				$this->restore_menu_preferences( $suspended );
			}

			if ( ! is_array( $items ) ) {

				// Fail safe: treat every item as populated. This is the safe
				// direction on purpose. A populated item shows no notice and
				// blanks nothing, so a probe that could not run leaves every
				// page exactly as its own extension rendered it.
				$items = array_fill_keys( array_keys( $this->get_items() ), true );
			}

			$this->native_items[ $user_id ] = $items;
		}

		$native = isset( $this->native_items[ $user_id ][ $name ] );

		/**
		 * Filters whether an account page counts as populated.
		 *
		 * Return false to replace the page with the empty-state notice,
		 * true to leave the page exactly as its own extension rendered it.
		 *
		 * Hook name: "amehp/persistent_native_item". Before version 3.0.0
		 * this was the "hppam/v1/native_item" hook of the Persistent
		 * Account Menu plugin.
		 *
		 * @param bool   $native Whether the item is present natively.
		 * @param string $name Menu item name.
		 * @param array  $items Native menu items.
		 */
		return (bool) apply_filters( 'amehp/persistent_native_item', $native, $name, $this->native_items[ $user_id ] );
	}

	/*
	-------------------------------------------------------------------------
	Menu and routes
	-------------------------------------------------------------------------
	*/

	/**
	 * Forces the managed items into the account menu.
	 *
	 * Runs at priority 500, after every stock condition filter (core at 10,
	 * Marketplace at 100), so items added natively are left untouched and
	 * only the missing ones are forced. The menu enhancer's own filters run
	 * later still, at 1000, so an item the owner has hidden there stays
	 * hidden even when it is forced here.
	 *
	 * @param array $menu Menu arguments.
	 * @return array
	 */
	public function alter_account_menu( $menu ) {

		// Never force items in the admin area or while probing the native menu.
		if ( is_admin() || $this->probing || ! is_user_logged_in() ) {
			return $menu;
		}

		foreach ( $this->get_items() as $name => $item ) {

			// Skip items added natively.
			if ( isset( $menu['items'][ $name ] ) ) {
				continue;
			}

			// Skip items disabled by their own condition.
			if ( isset( $item['enabled'] ) && ! call_user_func( $item['enabled'] ) ) {
				continue;
			}

			// Skip vendor items for non-vendors.
			if ( hp\get_array_value( $item, 'vendor' ) && ! $this->is_vendor() ) {
				continue;
			}

			if ( isset( $item['route'] ) ) {

				// Skip items whose extension is inactive.
				if ( ! hivepress()->router->get_route( $item['route'] ) ) {
					continue;
				}

				$menu['items'][ $name ] = [
					'route'  => $item['route'],
					'_order' => $item['_order'],
				];
			} elseif ( isset( $item['wc_endpoint'] ) && function_exists( 'wc_get_account_menu_items' ) ) {

				// WooCommerce label and URL are resolved HERE, at menu-build
				// time, never during the routes filter - see the comment in
				// get_default_items() for the early-cart trap this avoids.
				$menu['items'][ $name ] = [
					'label'  => hp\get_array_value( wc_get_account_menu_items(), $item['wc_endpoint'], hp\get_array_value( $item, 'label', $item['title'] ) ),
					'url'    => wc_get_endpoint_url( $item['wc_endpoint'], '', wc_get_page_permalink( 'myaccount' ) ),
					'_order' => $item['_order'],
				];
			}
		}

		// Mirror the Marketplace label when both order lists are present.
		//
		// The text domain below is Marketplace's, NOT ours, and that is deliberate.
		// This reuses the exact string Marketplace ships
		// (hivepress-marketplace/includes/components/class-marketplace.php:2637,
		// msgid "Placed Orders" in its own POT) so our label reads identically to
		// Marketplace's in every language Marketplace has been translated into.
		// Re-domaining it to account-menu-enhancer-for-hivepress would render
		// English on a translated site until somebody translated our POT too, and
		// would then let the two labels disagree about the same list - the opposite
		// of what "mirror" means here. The branch is only reachable when Marketplace
		// is active, so its .mo is already loaded by the time this runs.
		//
		// Plugin Check reports this as WordPress.WP.I18n.TextDomainMismatch. That is
		// a correct reading of a wp.org rule we are knowingly outside: see
		// resources/security-standards.md, "Borrowing another plugin's text domain".
		if ( isset( $menu['items']['orders_edit'] ) && isset( $menu['items']['orders_view'] ) ) {
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional reuse of Marketplace's string, see above.
			$menu['items']['orders_view']['label'] = esc_html__( 'Placed Orders', 'hivepress-marketplace' );
		}

		return $menu;
	}

	/**
	 * Neutralises the empty-page bounce on the managed routes.
	 *
	 * HivePress account pages redirect back to the account page when they
	 * have nothing to show (verified in core and in every managed extension
	 * list page), which would make the forced menu links unusable. Each
	 * managed route's redirect callbacks are wrapped so that, for logged-in
	 * users, a redirect targeting the account page is suppressed while every
	 * other redirect (authentication, feature gates, verification) passes
	 * through untouched.
	 *
	 * @param array $routes Route arguments.
	 * @return array
	 */
	public function alter_routes( $routes ) {

		// Read once and used twice below, so both halves of this filter are
		// provably talking about the same list.
		$items = $this->get_items();

		foreach ( $items as $item ) {
			$name = hp\get_array_value( $item, 'route' );

			if ( ! $name || ! isset( $routes[ $name ]['redirect'] ) ) {
				continue;
			}

			$callbacks = $routes[ $name ]['redirect'];

			// Normalise the callbacks the same way core does.
			if ( count( $callbacks ) === 2 && is_object( hp\get_first_array_value( $callbacks ) ) ) {
				$callbacks = [
					[
						'callback' => $callbacks,
						'_order'   => 5,
					],
				];
			}

			$callbacks = array_filter(
				array_map(
					function ( $args ) {
						return hp\get_array_value( $args, 'callback' );
					},
					hp\sort_array( $callbacks )
				)
			);

			$routes[ $name ]['redirect'] = [
				[
					'callback' => function () use ( $callbacks, $item ) {
						return $this->filter_redirect( $callbacks, $item );
					},

					'_order'   => 5,
				],
			];
		}

		// Pair the received-orders title with the forced placed-orders item.
		// Marketplace only titles the page "Received Orders" once the vendor
		// has placed orders of their own, because natively the "Placed
		// Orders" item cannot appear before then. Since the placed-orders
		// item is always forced next to it here, the plain "Orders" fallback
		// would make the two items ambiguous, so it is upgraded while custom
		// and already-distinct titles pass through untouched.
		if ( isset( $routes['orders_edit_page']['title'], $items['orders_view'] ) ) {
			$title = $routes['orders_edit_page']['title'];

			$routes['orders_edit_page']['title'] = function () use ( $title ) {
				$title = is_callable( $title ) ? call_user_func( $title ) : $title;

				if ( is_user_logged_in() && hivepress()->translator->get_string( 'orders' ) === $title ) {
					// Marketplace's text domain on purpose, mirroring its own route title
					// (hivepress-marketplace/includes/controllers/class-marketplace.php:619).
					// Same reasoning as the "Placed Orders" label in alter_account_menu()
					// above - read that comment before "fixing" this one.
					// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional reuse of Marketplace's string, see above.
					$title = esc_html__( 'Received Orders', 'hivepress-marketplace' );
				}

				return $title;
			};
		}

		return $routes;
	}

	/**
	 * Runs the original redirect callbacks, suppressing the empty bounce.
	 *
	 * The bounce is only suppressed for users the item is actually forced
	 * for, so gated pages keep their native behaviour for everyone else.
	 *
	 * @param array $callbacks Original redirect callbacks.
	 * @param array $item Item arguments.
	 * @return mixed
	 */
	protected function filter_redirect( $callbacks, $item ) {
		$account_url = untrailingslashit( (string) hivepress()->router->get_url( 'user_account_page' ) );

		// Check the item conditions.
		$forcible = is_user_logged_in();

		if ( $forcible && isset( $item['enabled'] ) && ! call_user_func( $item['enabled'] ) ) {
			$forcible = false;
		}

		if ( $forcible && hp\get_array_value( $item, 'vendor' ) && ! $this->is_vendor() ) {
			$forcible = false;
		}

		foreach ( $callbacks as $callback ) {
			$redirect = call_user_func( $callback );

			// Falsy results mean no redirect, the same as in core.
			if ( ! $redirect ) {
				continue;
			}

			// Honour boolean redirects (feature gates) and every redirect
			// for users the item is not forced for.
			if ( is_bool( $redirect ) || ! $forcible ) {
				return $redirect;
			}

			// Suppress the empty bounce back to the account page.
			if ( untrailingslashit( (string) $redirect ) === $account_url ) {
				continue;
			}

			return $redirect;
		}

		return false;
	}

	/*
	-------------------------------------------------------------------------
	Empty-state notice
	-------------------------------------------------------------------------
	*/

	/**
	 * Adds the empty-state notice to the managed account pages.
	 *
	 * Hooked on the base account page template, which fires for every child
	 * template because HivePress applies template filters for the whole
	 * class chain. The notice only renders when the extension itself left
	 * the item out of the native menu, meaning the page is empty.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function alter_account_page( $template ) {
		$route = hivepress()->router->get_current_route_name();

		if ( ! $route ) {
			return $template;
		}

		foreach ( $this->get_items() as $name => $item ) {
			if ( hp\get_array_value( $item, 'route' ) !== $route || ! isset( $item['notice'] ) ) {
				continue;
			}

			// Skip populated pages.
			if ( $this->is_native_item( $name ) ) {
				break;
			}

			// Add the notice above the page content. Blocks are merged with
			// `merge_blocks` rather than the soon-deprecated `merge_trees`,
			// in two separate calls: `_merge_blocks` never descends into a
			// block it has just matched, so the notice (added under
			// `page_content`) and the blanks (children of `page_content`)
			// cannot be merged in one pass.
			hivepress()->template->merge_blocks(
				$template,
				[
					'page_content' => [
						'blocks' => [
							'amehp_empty_notice' => [
								'type'    => 'content',
								'content' => $this->render_notice( $item['notice'] ),
								'_order'  => 5,
							],
						],
					],
				]
			);

			// Blank the page's own output so the default "Nothing found"
			// message is not shown alongside the notice.
			$blanks = [];

			foreach ( hp\get_array_value( $item['notice'], 'blank', [] ) as $block_name ) {
				$blanks[ $block_name ] = [
					'type'    => 'content',
					'content' => '',
				];
			}

			if ( $blanks ) {
				hivepress()->template->merge_blocks( $template, $blanks );
			}

			break;
		}

		return $template;
	}

	/**
	 * Resolves a notice's icon to an icon name.
	 *
	 * The owner's chosen icon wins over the page's built-in one. Both are plain Font Awesome names
	 * now: the built-ins were codepoints until 2026-09-02, when the icons stopped being webfont
	 * glyphs, and a codepoint is meaningless to a library that works from path data. The names are
	 * the Font Awesome 5 spellings they were before, which the library resolves through its own
	 * alias table, so `calendar-alt` and `search` still land on the right glyph.
	 *
	 * @param array $notice Notice arguments.
	 * @return string Icon name, or an empty string.
	 */
	protected function get_notice_icon( $notice ) {
		$chosen = hp\get_array_value( $notice, 'icon_name' );

		/*
		 * A chosen name is only used if the library actually has it. The name reaching here is
		 * whatever was saved in the setting, which may be an icon that has since been renamed away or
		 * simply a typo the field's format check let through - it only rejects characters that are
		 * not icon-name characters. Drawing nothing at all in that case would read as the placeholder
		 * page being broken, so the page's own icon is used instead, which is what happened before
		 * this looked names up rather than codepoints.
		 */
		if ( $chosen && ( ! class_exists( 'FAFH' ) || \FAFH::has( $chosen ) ) ) {
			return (string) $chosen;
		}

		return (string) hp\get_array_value( $notice, 'icon', '' );
	}

	/**
	 * Renders the empty-state notice.
	 *
	 * @param array $notice Notice arguments.
	 * @return string
	 */
	protected function render_notice( $notice ) {
		$output = '<div class="amehp-empty">';

		/*
		 * Icon, drawn as inline SVG. The owner's choice wins over the page's built-in one, and the
		 * shared library resolves the name to its real style itself, so a brand icon needs no
		 * special handling here - it used to need the family carried through to the markup, because
		 * brands live in a different webfont at a different weight.
		 *
		 * Escaped with the library's own allow-list rather than a general one: wp_kses_post() strips
		 * <svg> entirely, which would leave every placeholder page with no icon at all.
		 */
		$icon = $this->get_notice_icon( $notice );
		$svg  = $icon && class_exists( 'FAFH' ) ? \FAFH::svg( $icon ) : '';

		if ( $svg ) {
			$output .= '<span class="amehp-empty__icon" aria-hidden="true">' . wp_kses( $svg, \FAFH::kses() ) . '</span>';
		}

		// Text.
		$output .= '<p class="amehp-empty__text">' . esc_html( hp\get_array_value( $notice, 'text', '' ) ) . '</p>';

		// Button.
		$button = hp\get_array_value( $notice, 'button' );

		if ( $button ) {
			$url   = hp\get_array_value( $button, 'url' );
			$route = hp\get_array_value( $button, 'route' );

			if ( ! $url && $route ) {
				$url = hivepress()->router->get_url( $route );
			}

			$label = hp\get_array_value( $button, 'label', '' );

			if ( $url && $label ) {

				// `hp-button` is core's structural button class and
				// `button button--primary` the appearance pair every official
				// theme styles. `alt` is inert outside WooCommerce pages (all
				// six themes scope their `.button.alt` rules to
				// `.woocommerce`), but every official extension CTA carries
				// it, so it is kept for convention rather than effect.
				$output .= '<a href="' . esc_url( $url ) . '" class="amehp-empty__button hp-button button button--primary alt">' . esc_html( $label ) . '</a>';
			}
		}

		$output .= '</div>';

		/**
		 * Filters the rendered empty-state notice.
		 *
		 * Hook name: "amehp/persistent_notice_html". Before version 3.0.0
		 * this was the "hppam/v1/notice_html" hook of the Persistent
		 * Account Menu plugin.
		 *
		 * @param string $output Notice HTML.
		 * @param array  $notice Notice arguments.
		 */
		return apply_filters( 'amehp/persistent_notice_html', $output, $notice );
	}

	/**
	 * Enqueues the notice styles on the managed account pages.
	 */
	public function enqueue_styles() {
		$route = hivepress()->router->get_current_route_name();

		if ( ! $route ) {
			return;
		}

		// The item this page belongs to, so its own icon can be resolved.
		$current = null;

		foreach ( $this->get_items() as $item ) {
			if ( hp\get_array_value( $item, 'route' ) === $route ) {
				$current = $item;

				break;
			}
		}

		if ( ! $current ) {
			return;
		}

		$enhancer = hivepress()->amehp_menu_enhancer;

		wp_register_style( 'amehp-persistent', false, [], AMEHP_VERSION );
		wp_enqueue_style( 'amehp-persistent' );

		/*
		 * Spacing sticks to core's rem scale and the icon size is a percentage
		 * so type scales with the theme, per the native look-and-feel rules.
		 *
		 * The button colour is pinned for every interactive state, hover
		 * included. The `.button.alt` pair is scoped to `.woocommerce` in all
		 * six official themes, so on these HivePress pages the theme's
		 * generic `a:hover` colour outranked the button styling and turned
		 * the white button text blue on hover. `button--primary` renders
		 * white text in every official theme, so white is pinned explicitly
		 * rather than left to a cascade that has already lost once.
		 */
		$css = '.amehp-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:3rem 1rem;gap:1rem}
			.amehp-empty__icon{display:inline-block;font-size:275%;line-height:1;opacity:.25}
			.amehp-empty__icon svg{display:block;width:1em;height:1em;fill:currentColor}
			.amehp-empty__text{max-width:26rem;margin:0}
			.amehp-empty__button,.amehp-empty__button:hover,.amehp-empty__button:focus,.amehp-empty__button:active{color:#fff}';

		if ( $enhancer ) {
			/*
			 * The placeholder icon follows the same three Appearance settings
			 * the menu icons do.
			 *
			 * Colour: a chosen colour is shown as chosen, at full strength.
			 * With none set the glyph stays at the quarter opacity it has
			 * always had, which is what keeps it reading as a soft
			 * illustration on a site that has never touched these settings.
			 *
			 * Size: the setting is a MENU icon size, and this glyph is a
			 * large decorative one, so it is used as the base and tripled
			 * rather than applied flat - an 18px menu icon would otherwise
			 * shrink the placeholder illustration to the size of the body
			 * text. With nothing set it keeps its own 275% scale.
			 */
			$colour = $enhancer->sanitize_colour( (string) get_option( 'hp_amehp_icon_colour' ) );

			if ( $colour ) {
				$css .= '.amehp-empty__icon{color:' . $colour . ';opacity:1}';
			}

			$size = get_option( 'hp_amehp_icon_size' );

			if ( is_numeric( $size ) ) {
				$css .= '.amehp-empty__icon{font-size:' . ( max( 8, min( 48, (int) $size ) ) * 3 ) . 'px}';
			}

			$stroke = $enhancer->get_stroke_width( (string) get_option( 'hp_amehp_icon_weight' ) );

			if ( $stroke ) {
				/*
				 * A stroke on the path, not a text stroke: the glyph is no longer text. The width is
				 * the same CSS length as before because the library emits
				 * `vector-effect="non-scaling-stroke"` on every path it draws, which is what makes
				 * stroke-width mean rendered pixels rather than user units - a Font Awesome viewBox
				 * is 512 units tall, so "1px" read as user units would be about a twentieth of a
				 * percent of the icon and invisible.
				 */
				$css .= '.amehp-empty__icon svg{stroke:currentColor;stroke-width:' . $stroke . ';stroke-linejoin:round;paint-order:stroke fill}';
			}
		}

		wp_add_inline_style( 'amehp-persistent', $css );
	}
}
