<?php
/**
 * WooCommerce menu item options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_wc_menu_items", so the option list is only built on the
 * settings screen and during validation. Filterable via the
 * "hivepress/v1/amehp_wc_menu_items" hook.
 *
 * A shorter list than "amehp_menu_items": only the items that can actually
 * appear in the WooCommerce account menu, since the setting it feeds can do
 * nothing about anything else.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_wc_menu_item_options() : [];
