<?php
/**
 * Lean Redirects uninstall script.
 *
 * Removes the database table when the plugin is deleted via the WordPress admin.
 *
 * @package LeanRedirects
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query(
	$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'lean_redirects' )
);
