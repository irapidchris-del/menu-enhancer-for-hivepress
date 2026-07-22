=== Account Menu Enhancer for HivePress ===
Contributors: chrisb
Tags: hivepress, woocommerce, account, menu, icons
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item icons and colours, custom items, and hidden items.

== Description ==

HivePress renders the WooCommerce Orders page inside its own account layout, but leaves the other WooCommerce account pages (Dashboard, Addresses, Payment methods, Account details and Downloads) using the WooCommerce layout with a different sidebar menu. This plugin fixes that inconsistency and adds full control over the account menu.

**Features**

* **One account layout.** Renders the remaining WooCommerce account pages inside the HivePress account template, using the same mechanism HivePress core already uses for the Orders page, so every account page shares one sidebar menu.
* **Menu merging.** Optionally lists the WooCommerce account links in the HivePress menu and the HivePress account links in the WooCommerce menu, so both menus match wherever they appear.
* **Icons and colours.** Assign any of the 1,000 Font Awesome icons bundled with HivePress to any menu item, each with an optional colour, using the native HivePress icon dropdown with previews.
* **Custom menu items.** Add your own links (a page, a HivePress account page, a WooCommerce endpoint or a custom URL) with a label, icon, colour, position, menu targeting and per-role visibility.
* **Hidden items.** Hide any HivePress or WooCommerce menu item.
* **Counters.** Mirrors the HivePress menu counters (for example unread messages) into the WooCommerce menu when the menus are merged.

All settings live under HivePress, then Settings, then Account Menu.

**Notes**

* Icons are rendered with CSS using Font Awesome codepoints, so they work with the Font Awesome 5 Free files bundled with HivePress as well as self-hosted Font Awesome 6 or 7. If you subset Font Awesome yourself, make sure the icons you select here are included in your subset.
* The WooCommerce settings only appear while WooCommerce is active.

== Installation ==

1. Install and activate HivePress.
2. Upload the plugin files to the "/wp-content/plugins/account-menu-enhancer-for-hivepress" directory, or install the plugin ZIP through the WordPress plugins screen.
3. Activate the plugin.
4. Configure it under HivePress, then Settings, then Account Menu.

== Changelog ==

= 2.0.1 =
* Fixed a critical error when opening the settings page on sites where the user or vendor profile pages are available. Route titles that are resolved by callbacks (which expect the front-end context) are no longer invoked when building the settings dropdowns; plugin labels are used instead.

= 2.0.0 =
* Complete rewrite as a native HivePress extension with settings under HivePress, then Settings.
* Added the option to render the WooCommerce account pages inside the HivePress account layout.
* Added per-item Font Awesome icons and colours for all account menu items.
* Added the option to hide any HivePress or WooCommerce menu item.
* Custom items now support icons, colours and a native link dropdown.
* Counters are now read from the HivePress menu data instead of the page markup.
* Settings from version 1.x are migrated automatically on upgrade.

= 1.1.0 =
* Legacy version.
