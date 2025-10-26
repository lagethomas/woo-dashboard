<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   DashboardLojaVirtual
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Deleta a option principal do plugin.
delete_option( 'lv_dash_settings' );

// Limpa todos os transients criados pelo plugin.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lv\_%' OR option_name LIKE '\_transient\_timeout\_lv\_%'" );