<?php
/**
 * Link options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_links", so the option list is only built on the
 * settings screen and during validation. Filterable via the
 * "hivepress/v1/amehp_links" hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->account_menu_enhancer;

return $amehp ? $amehp->get_link_options() : [];
