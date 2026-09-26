=== Account Menu Enhancer for HivePress ===
Contributors: chrisb
Tags: hivepress, woocommerce, account, menu, icons
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unifies the HivePress and WooCommerce account areas into one menu, with icons, colours, labels, nested items, custom items and per-menu control.

== Description ==

HivePress renders the WooCommerce Orders page inside its own account layout, but leaves the other WooCommerce account pages (Dashboard, Addresses, Payment methods, Account details and Downloads) using the WooCommerce layout with a different sidebar menu. This plugin fixes that inconsistency and adds full control over the account menus.

**Features**

* **One account layout, one menu.** A single WooCommerce Integration switch renders the remaining WooCommerce account pages inside the HivePress account template, using the same mechanism HivePress core already uses for the Orders page, and lists the WooCommerce account links in the HivePress menu and the HivePress account links in the WooCommerce menu, so every account page shares one sidebar and both menus match wherever they appear.
* **Icons and colours.** Assign an icon to any menu item, each with an optional colour, from a searchable dropdown with previews covering every icon in Font Awesome Free, brands included. Set the icon size, an icon weight that thickens the glyphs, and a round colour chip behind every icon.
* **Labels.** Rename any menu item from the settings screen, without a translation plugin. Leave the box empty to keep the item's usual name.
* **Nested items.** Nest any item under a parent item. The parent gets a button that opens and closes its group, in the header account dropdown, the HivePress account sidebar and the WooCommerce account menu alike, and a group holding the page being viewed starts open.
* **Three menus, told apart.** Limit any item to some of the three account menus: the header account dropdown, the HivePress account sidebar and the WooCommerce account menu. An item can be in the dropdown but not the sidebar, or the other way round.
* **Live preview and drag ordering.** The settings tab shows your real account menus as your site will render them, and you drag the items into the order you want, or move them with arrow buttons. One panel is shown while your menus agree, and a panel per menu as soon as they differ.
* **Custom menu items.** Add your own links (a page, a HivePress account page, a WooCommerce endpoint or a custom URL) with a label, icon, colour, a choice of menus, a parent item and per-role visibility, placed wherever you drag them.
* **Hidden items.** Hide any HivePress or WooCommerce menu item from every account menu, or from the WooCommerce account menu alone.
* **Persistent menu items.** Keep chosen account menu items visible even when their pages are empty, instead of letting them disappear.
* **Placeholder pages.** Give each empty account page its own icon, message, button label and button URL, so it explains itself and points somewhere useful.
* **Counters.** Mirrors the HivePress menu counters (for example unread messages) into the WooCommerce menu when WooCommerce Integration is switched on.
* **Menu styling.** Set the menu item font weight, apply the theme's Heading Font to the sidebar account menus, recolour individual menu item text, hide the theme's navigation arrows, and hide the page header on WooCommerce account pages so they match the HivePress account pages.

All settings live under HivePress, then Settings, then Account Menu.

**Notes**

* Menu icons are drawn from each icon's own shape, so nothing on the front of your site downloads a font file for them. The icon library ships with the plugin and is shared with this author's other extensions, so however many of them you run only one copy is loaded, and only in the admin.
* A parent item must be a top-level item shown in the same menu. An item whose parent is itself nested, hidden, or not shown in a particular menu, sits at the top level of that menu instead of disappearing with it. A custom item can be chosen as a parent once it has been saved.
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

= 3.5.1 =
* Added: each placeholder page has a Show on this page setting, to switch off its page title, icon, message or button where the theme or another plugin already shows its own heading or introduction. Nothing changes until something is unticked.
* Changed: a parent item that also shows a count, such as Notifications with items nested under it, keeps both the count and the button that opens its group visible, whichever theme draws the count.
* Fixed: a custom item whose link is just "#" (a parent that is only a heading for its group) was left out of the menu on the site, so its nested items showed at the top level although the settings preview looked right. It now shows, and clicking its name opens and closes the group.

= 3.5.0 =
* Added: the Coupons item that Marketplace 1.4 adds to the Vendor account menu is now offered on the Menu Items rows straight away, so it can be given an icon, a colour and a label like every other item. Until a vendor had opened their account page it was the one item the settings screen could not list.
* Added: a Label box on every Menu Items row, so any account menu item can be renamed from the settings screen without a translation plugin. Leave it empty to keep the item's usual name, which is shown in the box as a hint.
* Added: menu items can be nested under a parent item. Choose a Parent Item on a Menu Items row or on a custom item, and it folds away under that parent with a button to open the group, in the header account dropdown, the HivePress account sidebar and the WooCommerce account menu alike. A group holding the page being viewed starts open. The Live preview draws the nesting, and a nested item is dragged among the items under the same parent.
* Added: a Menus setting on every Menu Items row, so an item can be shown in some account menus and not others. The header account dropdown, the HivePress account sidebar and the WooCommerce account menu are now told apart, so an item can be in the dropdown but not the sidebar, or the other way round. Custom items gain the same three choices in place of the old "HivePress Menu Only" and "WooCommerce Menu Only"; a saved choice is carried over and means the same as before.
* Changed: the Live preview shows one panel while your menus agree, two when only the WooCommerce menu differs, and three when the header dropdown and the sidebar differ too.
* Changed: the "Menu Item Styling" setting is now called "Menu Items", since a row can now rename, nest and place an item as well as style it. Nothing you have saved there changes.
* Fixed: hiding "Orders (WooCommerce)" from the WooCommerce menu alone left the Placed Orders item in the HivePress account menus with no wording at all, an empty row with only its icon. HivePress reads that item's name from the WooCommerce menu, which the setting had just taken it out of; the name is now restored. The same applies to Subscriptions.
* Fixed: the account page could send a member to a page its own sidebar does not list, when the first menu item is shown in the header dropdown only. The redirect now follows the sidebar.

= 3.4.5 =
* Fixed: updating two of these extensions one after the other could fail on the second with "up to date" until Check for updates was pressed again. WordPress rebuilds its update list after each update by asking wordpress.org first, and gives up on the whole list when that call is slow; the plugin now keeps its own update in the list regardless.
* Changed: a release found more than an hour ago is refreshed in the background whenever the Plugins screen is opened, so the newest release is offered rather than an intermediate one.
* New: a Check for updates bulk action on the Plugins screen, which checks every selected extension in one go, and the row that says Updating no longer shrinks on phones.

Older entries are in changelog.txt, which ships with the plugin. WordPress truncates this
section at 5,000 characters, so only the most recent releases are repeated here.

= 3.4.4 =
* Changed: the counter mirrored into the WooCommerce account menu is now the same 24px circle with bold 12px text as the counters in the HivePress account menus, so every counter in the menu reads as one family.

= 3.4.3 =
* Changed: on the settings tab the help icon now sits directly after each label, and its tooltip opens to the right at full width instead of being cut into a narrow strip to the left. The same placement is used across every extension in this family.

= 3.4.2 =
* Fixed: typing an item's label could draw a broken glyph beside the card's icon, the same fault found in Action Bar. The shared icon library is updated to the version that fixes it.

= 3.4.1 =
* Fixed: choosing an icon for a menu item or custom item no longer changes other rows' icons when
  you save. In 3.4.0 an item with no icon of its own took the icon of the item above it the next
  time the page was saved, and a newly picked icon showed as the previous one, enlarged, until the
  page was reloaded. If 3.4.0 changed any of your icons, open the Account Menu tab, set the affected
  rows back to what you want, and save once. 3.4.0 has been withdrawn.

== Upgrade Notice ==

= 2.0.2 =
This release repairs the automatic settings migration from version 1.x and fixes several account menu issues. Upgrading is recommended for all users.
