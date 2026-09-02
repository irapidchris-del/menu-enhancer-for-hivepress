<?php
/**
 * Icon options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_icons", so the option list is only built on the
 * settings screen and during validation. Unlike core's own "icons" list,
 * this one offers every icon in Font Awesome Free, brands and the version
 * 6 and 7 additions included. Filterable via the "hivepress/v1/amehp_icons"
 * hook.
 *
 * The pickers themselves search the library over AJAX rather than printing
 * this list; see Amehp_Menu_Enhancer::get_icon_options() for why it is still
 * built.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->amehp_menu_enhancer;

return $amehp ? $amehp->get_icon_options() : [];
