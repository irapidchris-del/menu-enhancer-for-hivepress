=== Account Menu Enhancer for HivePress ===
Contributors: chrisb
Tags: hivepress, woocommerce, account, menu, icons
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.13
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
* **Menu styling.** Set the menu item font weight, recolour individual menu item text, hide the theme's navigation arrows, and hide the page header on WooCommerce account pages so they match the HivePress account pages.

All settings live under HivePress, then Settings, then Account Menu.

**Notes**

* Icons are rendered with CSS using Font Awesome codepoints, so they work with the Font Awesome 5 Free files bundled with HivePress as well as self-hosted Font Awesome 6 or 7. If you subset Font Awesome yourself, make sure the icons you select here are included in your subset.
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

= 2.2.13 =
* Fixed: a menu styling row that set a text colour but no icon is no longer lost. If the extension
  providing that menu item was switched off and the tab was saved again, the row was silently
  dropped and could not be recovered by switching the extension back on, while the screen still
  said the settings had saved.
* Fixed: the account menu is no longer remembered in a distorted form. Another plugin building its
  own copy of the account menu could leave this one holding that copy for the rest of the request,
  which affected both the merged menu and the settings screen.
* Fixed: deleting the plugin now also clears the update check's own leftovers and cancels its
  background update check.

= 2.2.12 =
* Fixed: HivePress filters the account menu at two stages, and extensions that add their item at the
  second stage - Vendor Analytics registers this way - could neither be recorded nor actually hidden:
  the hide ran before the item existed. Both now also run at the second stage, so those items appear
  on the settings screen and hiding them genuinely removes them.
* Fixed: the record of seen menu items was read on every page view for every signed-in visitor but
  stored as a non-autoloaded option, costing one extra database query per page forever. It now loads
  with the options WordPress fetches anyway.
* Fixed: Delete All Data now also removes the icon spacing setting and the seen-items record, which
  the uninstaller previously left behind.

= 2.2.11 =
* Fixed: the icon spacing setting only moved the icons this plugin draws. If your theme or a
  customiser stylesheet supplies most of your menu icons, only the handful set up here moved and the
  rest stayed where they were, which looked like the setting being ignored. A spacing you set now
  applies to every item in the account menu, moving the icon closer to or further from its label
  without changing the icon itself. Leaving the box empty still changes nothing.

= 2.2.10 =
* Fixed: the icon spacing setting appeared to do nothing. Themes and site customisers style these
  icons with more specific selectors and simply outranked it. A spacing you have actually typed now
  wins; leaving the box empty still defers to your theme as before.
* Fixed: menu items added by extensions that register them only on the front end - including our own
  Notifications - never appeared in the list of items you can hide. The account menu is now recorded
  as it is really built, so every route-based item a visitor can see is an item you can hide.

= 2.2.9 =
* **Fixed - menu items added by other plugins were missing from Hidden Items.** WooCommerce
  Subscriptions was the clearest case: its Subscriptions link is added only for somebody who
  actually has a subscription, so when the list was built for an administrator who had none, the
  item simply was not there and you could never choose to hide it, even while your members could see
  it plainly. The list now also offers every account endpoint that is registered, which does not
  depend on who is looking. Payment methods reappears for the same reason.
* **Added - an Icon Spacing setting** for the gap between a menu icon and its wording. Leave it
  empty and the gap scales with your theme text size as before; type a number and it is used
  exactly, in pixels.

= 2.2.8 =
* Fixed - checking for updates no longer holds up an admin page. The check ran while WordPress was
  building the Plugins screen, so on a site with several of these extensions one page load made one
  request to GitHub after another and could sit there for many seconds, once, before behaving
  normally again for hours. The check now runs in the background moments later. Pressing Check for
  updates still asks GitHub straight away, because you are waiting for that answer.
* Fixed - "View details" is back on the Plugins screen. WordPress only offers that link for a
  plugin that has told it about itself, and this one stayed quiet whenever there was nothing to
  update to, which is almost always. The details popup, its changelog and the donate link inside
  it were all unreachable from the Plugins screen as a result.

= 2.2.7 =
* Checking for updates no longer reports "Could not reach GitHub" when nothing is wrong. GitHub allows a server only a limited number of anonymous update checks each hour, shared by every plugin on the site and, on shared hosting, by every other site on the same server. Running out is ordinary, but it was reported as though the site could not reach GitHub at all. Update checks now read the release from github.com, which sets no such limit, so the message no longer appears. If the limit is ever reached by some other route, the notice now says so plainly instead of blaming your connection.
* A failed update check no longer hides an update that is genuinely waiting. The last successful answer is kept until a later check succeeds, so a pending update stays on the Plugins screen instead of disappearing for an hour.

= 2.2.6 =
* Fixed: the author shown on the Plugins screen now reads "ChrisB @ HivePress Community", matching every other extension in the range.
* Added: your settings are now kept when the plugin is deleted. A new "Delete All Data" tickbox under Removing the Plugin is the only thing that erases them, and it is off unless you turn it on. Previously deleting the plugin always wiped everything, so reinstalling meant setting it all up again.
* Added: a "Donate" link on the Plugins screen and in the plugin details popup, for anyone who would like to support the work. It appears nowhere else and gates nothing.
* Changed: the plugin's PHP class and one config file are now prefixed, so they cannot collide with HivePress or a future official extension. Your saved settings are unaffected.

= 2.2.5 =
* Fixed a PHP warning that HivePress core logged on every page load while the plugin was active, caused by the way the plugin registered itself with HivePress.
* The counters mirrored into the WooCommerce menu now match the HivePress counter style exactly, and themes that restyle their badges through a class convention can pick these up too.
* The Import Listings page can now be hidden, styled and linked like the other account pages when the Import extension is active.
* The colour fields now fall back to a plain text field on HivePress versions older than 1.7.26, instead of silently disappearing from the settings screen.
* The Appearance settings now warn that replacing or subsetting Font Awesome can remove icons.
* The custom items setting now explains that administrators always see every item, including role-restricted ones.
* The WooCommerce pages shown inside the HivePress account layout now display their own titles (for example Downloads or Account details) in the same style as the Orders page, instead of a generic My account header.
* Fixed a duplicate subscriptions link in the WooCommerce account menu for customers with a single subscription, where WooCommerce Subscriptions links the menu item straight to the subscription details page.
* Fixed browser caching of the plugin styles and scripts. Asset versions now include the file modification time, so updates always load fresh files instead of appearing to revert to earlier behaviour until a hard refresh.
* Shorthand colour values such as #fff are now expanded automatically, instead of quietly preventing the settings page from saving.
* Added a description to the custom menu items setting, explaining which fields are required.
* Added the translation template file, so the plugin can be translated with the usual tools. Translations belong in WordPress's own languages folder, which survives plugin updates.
* The plugin now registers itself with HivePress in a way that works from any folder name, and uses the current HivePress method for adding blocks to the account template.
* Corrected the minimum WordPress version to 5.8, which is what the built-in update mechanism requires.

= 2.2.4 =
* Reworked automatic updates to use WordPress's built-in update mechanism directly instead of a bundled library. The plugin is now much smaller, while keeping the same one-click updates from GitHub releases, the "Check for updates" link and the "View version details" popup on the Plugins screen. Requires WordPress 5.8 or newer for automatic updates.

= 2.2.3 =
* Added automatic updates. New versions are now delivered through the plugin's GitHub releases, so a new release shows the usual "update available" notice on the Plugins screen and can be installed with one click, just like a plugin from wordpress.org.

= 2.2.2 =
* Tidied the settings field labels (for example "Icon" and "Menus" instead of "Select Icon" and "Both Menus").

= 2.2.1 =
* Added a label above each field in the settings repeaters, so every field (including the two colour pickers) is clear at a glance.
* Fixed the colour picker's hex input and Clear button still stacking on separate lines on small screens.

= 2.2.0 =
* Added a per-item text colour for both the built-in menu items and custom items, so the text of an individual menu item can be recoloured. The icon is now optional on each styling row, so a built-in item can be recoloured without adding an icon (the "Menu Item Icons" section is now "Menu Item Styling").
* Fixed the colour picker's hex field and Clear button stacking onto separate lines in the settings.

= 2.1.6 =
* The hide chevrons option now only affects the sidebar account menus, leaving the header account dropdown at its normal position.

= 2.1.5 =
* Renamed the "Default Icon Colour" option to "Icon Colour" and clarified that it colours every menu item icon at once, while a colour set on an individual item still overrides it.

= 2.1.4 =
* The colour pickers now include an editable hex field, so a colour value can be typed in directly as well as picked.

= 2.1.3 =
* Fixed the icon spacing on themes that lay the account menu links out as a flex row, where the icon could be pushed away from its label. The icon now stays next to its label while any counter stays on the right.

= 2.1.2 =
* Hiding the menu chevrons now also removes the space they left behind, so the account menu items sit flush.
* Reworked the settings repeaters so the drag and remove controls sit in a clear bar at the top of each card, with roomier fields on small screens.

= 2.1.1 =
* The hide menu chevrons option is now limited to the account menus, so other navigation menus (for example in the footer) keep their markers.
* Moved the Behaviour section to the top of the settings page.
* Improved the settings repeaters on small screens, with more inset fields and larger drag and remove controls.

= 2.1.0 =
* Added a menu item weight option (Normal to Bold) for the account menus.
* Added an option to hide the theme navigation menu arrows so only the chosen icons show.
* Added an option to hide the page header on WooCommerce account pages so they match the HivePress account pages.
* Reworked the settings repeaters (icons and custom items) to stack their fields vertically on all screen sizes, so every field is readable and the drag and remove controls stay on screen.
* The plugin now loads from any folder name without showing an admin notice.

= 2.0.3 =
* The plugin now loads even when its folder has a non-standard name (for example when installed directly from source) instead of requiring an exact folder name. Using the recommended folder name is still suggested for full compatibility.

= 2.0.2 =
* Fixed the "My Profile" custom link, which pointed to a broken URL because the user profile address was built without the username.
* Fixed the version 1.x settings migration being skipped on some upgrades, which discarded the old hidden items and custom menu items and could switch the account layout unexpectedly.
* The extension now registers reliably regardless of the installation folder name.
* Hiding the WooCommerce Orders item no longer leaves a duplicate Orders link in the WooCommerce menu.
* Custom URLs are now restricted to http and https links.
* Custom links to a page that is later unpublished or trashed are no longer shown.
* Custom links whose target is later removed (for example when WooCommerce or an extension is deactivated) no longer block the settings from saving.
* Kept the account menu working if another plugin errors while building the WooCommerce menu.
* Updated the page title handling for compatibility with newer WooCommerce versions.
* Added translation loading, an uninstall cleanup routine and a bundled licence file.

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

== Upgrade Notice ==

= 2.0.2 =
This release repairs the automatic settings migration from version 1.x and fixes several account menu issues. Upgrading is recommended for all users.
