<?php
/**
 * User role options configuration.
 *
 * Loaded lazily by HivePress whenever a field declares
 * "options" => "amehp_roles". Filterable via the "hivepress/v1/amehp_roles"
 * hook.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp = hivepress()->account_menu_enhancer;

return $amehp ? $amehp->get_role_options() : [];
