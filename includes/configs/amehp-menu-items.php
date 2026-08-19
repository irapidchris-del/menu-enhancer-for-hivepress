<?php
/**
 * Menu item options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_menu_items", so the option list is only built on the
 * settings screen and during validation. Filterable via the
 * "hivepress/v1/amehp_menu_items" hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_menu_item_options() : [];
