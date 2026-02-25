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

// Drop the plugin's custom table on uninstall. Direct query is required for DDL.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lean_redirects" );
