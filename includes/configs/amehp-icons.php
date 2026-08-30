<?php
/**
 * Icon options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_icons", so the option list is only built on the
 * settings screen and during validation. Unlike core's own "icons" list,
 * this one also offers the Font Awesome 6/7 and brand icons from the
 * amehp-icon-codes config. Filterable via the "hivepress/v1/amehp_icons"
 * hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_icon_options() : [];
