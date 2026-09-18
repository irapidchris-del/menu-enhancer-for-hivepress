<?php
/**
 * Menu options configuration.
 *
 * The three account menus an item can be shown in or kept out of. Loaded
 * lazily by HivePress whenever a field declares "options" => "amehp_menus",
 * so the list is only built on the settings screen and during validation.
 * Filterable via the "hivepress/v1/amehp_menus" hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_menu_options() : [];
