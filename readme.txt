=== Account Menu Enhancer for HivePress ===
Contributors: chrisb
Tags: hivepress, woocommerce, account, menu, icons
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.3.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unifies the HivePress and WooCommerce account areas into one consistent menu, with per-item icons and colours, custom items, and hidden items.

== Description ==

HivePress renders the WooCommerce Orders page inside its own account layout, but leaves the other WooCommerce account pages (Dashboard, Addresses, Payment methods, Account details and Downloads) using the WooCommerce layout with a different sidebar menu. This plugin fixes that inconsistency and adds full control over the account menu.

**Features**

* **One account layout, one menu.** A single WooCommerce Integration switch renders the remaining WooCommerce account pages inside the HivePress account template, using the same mechanism HivePress core already uses for the Orders page, and lists the WooCommerce account links in the HivePress menu and the HivePress account links in the WooCommerce menu, so every account page shares one sidebar and both menus match wherever they appear.
* **Icons and colours.** Assign an icon to any menu item, each with an optional colour, from a dropdown with previews: the Font Awesome icons bundled with HivePress, the names added in Font Awesome 6 and 7, and a set of brand icons such as Stripe, PayPal and WhatsApp. Set the icon size, an icon weight that thickens the glyphs, and a round colour chip behind every icon.
* **Live preview and drag ordering.** The settings tab shows your real account menu as your site will render it, and you drag the items into the order you want, or move them with arrow buttons. The order applies to the HivePress account dropdown, the HivePress account sidebar and the WooCommerce account sidebar alike.
* **Custom menu items.** Add your own links (a page, a HivePress account page, a WooCommerce endpoint or a custom URL) with a label, icon, colour, menu targeting and per-role visibility, placed wherever you drag them.
* **Hidden items.** Hide any HivePress or WooCommerce menu item from both account menus, or from the WooCommerce account menu alone.
* **Persistent menu items.** Keep chosen account menu items visible even when their pages are empty, instead of letting them disappear.
* **Placeholder pages.** Give each empty account page its own icon, message, button label and button URL, so it explains itself and points somewhere useful.
* **Counters.** Mirrors the HivePress menu counters (for example unread messages) into the WooCommerce menu when WooCommerce Integration is switched on.
* **Menu styling.** Set the menu item font weight, apply the theme's Heading Font to the sidebar account menus, recolour individual menu item text, hide the theme's navigation arrows, and hide the page header on WooCommerce account pages so they match the HivePress account pages.

All settings live under HivePress, then Settings, then Account Menu.

**Notes**

* Icons are rendered with CSS using Font Awesome codepoints. The icons HivePress bundles work on their own; when you choose a Font Awesome 6 or 7 name, or a brand icon, the plugin loads the copy of Font Awesome 7 it ships with so the icon renders. If you subset Font Awesome yourself, make sure the icons you select here are included in your subset.
* The WooCommerce settings only appear while WooCommerce is active.
* If you use a performance plugin that removes unused CSS (for example Perfmatters or FlyingPress), exclude this plugin's stylesheet from that feature, otherwise the menu counters can render unstyled because they are added to the page after the used CSS is sampled.
* Once an item is given an icon, the icon colour comes from this plugin's settings (or the menu text colour when no colour is set), taking precedence over icon colour rules added by themes or custom CSS.
* Administrators always see every custom menu item, including role-restricted ones, so they can check what they have configured. Use a non-administrator account to see a role restriction in effect.
* Because the plugin is distributed from GitHub rather than wordpress.org, it checks its GitHub releases for updates and shows any new version on the Plugins screen, so you can update it from the dashboard as usual.

== Installation ==

1. Install and activate HivePress.
2. Upload the plugin files to the "/wp-content/plugins/account-menu-enhancer-for-hivepress" directory, or install the plugin ZIP through the WordPress plugins screen.
3. Activate the plugin.
4. Configure it under HivePress, then Settings, then Account Menu.

== Changelog ==

Older entries are in changelog.txt, which ships with the plugin. WordPress truncates this
section at 5,000 characters, so only the most recent releases are repeated here.

= 3.3.14 =
* Changed: the older entries in this changelog have moved to changelog.txt, which ships with the
  plugin. WordPress only shows the first 5,000 characters of a changelog on the update screen, and
  this one had grown well past that, so recent releases were being cut off before you could read
  them. Nothing has been removed.

= 3.3.13 =
* Fixed: the unread counters in the account menus now sit a small, even distance from the wording
  they belong to. In menus your theme lays out as a row the counter was pushed to the far right,
  away from its own item, and in the account dropdown it sat flush against the last letter with no
  gap at all. Both now match the spacing HivePress uses itself.

= 3.3.12 =
* Added: an "Also Hidden from the WooCommerce Menu" setting, which takes a menu item out of the
  WooCommerce account menu while leaving it in the HivePress account menu. The existing Hidden Items
  setting is unchanged and still hides an item from both menus, so nothing you have already saved
  behaves differently. The Live preview panel shows both menus separately whenever the two now
  differ.
* Fixed: the Live preview panel named a menu item the way the settings dropdowns name it rather than
  the way your site renders it, showing "Orders (WooCommerce)" where members see "Placed Orders".
  The panel now draws the wording each menu actually rendered, which can differ between the two
  menus for the same page. The dropdowns keep their wording, where it tells two similar destinations
  apart.

= 3.3.11 =
* Changed: internal tidying only, with no change to how anything looks or behaves. The settings
  screen was being sent a copy of your saved menu arrangement that nothing used, because the
  live preview reads the arrangement from the page itself so that it follows you as you drag.
  The unused copy has been removed.

= 3.3.10 =
* Fixed: the Placed Orders item, which HivePress adds to the account menu once a member has an
  order, appeared at the very bottom of the menu on sites where the menu order had been arranged.
  It now sits in its proper place, and the same applies to the Subscriptions item.
* Fixed: Placed Orders and Subscriptions are now shown in the Live preview panel, where they can be
  dragged into any position you like. They were missing from the preview whenever the WooCommerce
  integration was switched off, so the preview disagreed with the menu your members actually saw.
* Changed: a menu item that appears after you have arranged your menu is now placed in the position
  its own extension gives it, rather than being added below every item you arranged. Your own
  arrangement is unchanged.

= 3.3.9 =
* Changed: the instructions on the Menu Item Styling setting no longer say the cards can be dragged
  into order. The card handles were removed in 3.3.4, and the wording now points to the Live preview
  panel, which is where the menu order is arranged. Anyone translating the plugin should revisit
  this wording.
* Changed: the plugin's record of the menu items your site renders no longer keeps an entry for a
  custom menu item you have deleted. The record exists so that every item can be offered on the
  settings screen, and it grew a little each time a custom item was removed. Your custom items,
  their icons and colours, and your menu order are all unaffected.

= 3.3.8 =
* Fixed: on sites where Account Menu is the first tab under HivePress > Settings, opening Settings
  from the menu could show that tab with none of its controls working - no quick links, no live
  preview and no colour pickers, with nothing to say why. The plugin now recognises its own tab
  from what is actually on the page rather than from the web address, so it works however you
  reach it.
* Fixed: if another extension in this family also adds these controls to a settings tab, you could
  have ended up with two sets of them at once - two rows of quick links, two floating Save tabs,
  two back-to-top buttons. The extensions can now see each other's controls and only one set is
  drawn, whichever extension gets there first.
* Changed: the hover tooltips on the settings screen are wider, so a two-sentence explanation
  reads as a few short lines rather than a tall narrow ribbon of text.

== Upgrade Notice ==

= 2.0.2 =
This release repairs the automatic settings migration from version 1.x and fixes several account menu issues. Upgrading is recommended for all users.
