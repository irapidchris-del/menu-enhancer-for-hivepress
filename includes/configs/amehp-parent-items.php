<?php
/**
 * Parent item options configuration.
 *
 * Every menu item another item can be nested under: the built-in items the
 * settings screen already lists plus the saved custom items. Loaded lazily by
 * HivePress whenever a field declares "options" => "amehp_parent_items".
 * Filterable via the "hivepress/v1/amehp_parent_items" hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_parent_item_options() : [];
