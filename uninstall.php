<?php
/**
 * Uninstall handler for Fake User Detector
 *
 * Deletes all plugin options, transients, and specific usermeta when uninstalled via
 * the WordPress plugin uninstaller.
 */

// Exit if not called by WP uninstall routine
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the cleanup setting is enabled
$fudetector_cleanup_enabled = get_option( 'fudetector_uninstall_cleanup', false );
if ( ! $fudetector_cleanup_enabled ) {
    return; // Do nothing if the option is not enabled
}

global $wpdb;

$fudetector_option_prefix = 'fudetector_';

/**
 * Delete all options
 */
$fudetector_like = $wpdb->esc_like( $fudetector_option_prefix ) . '%';
$fudetector_option_rows = $wpdb->get_col( // phpcs:ignore
    $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $fudetector_like ) // phpcs:ignore
);
if ( is_array( $fudetector_option_rows ) ) {
    foreach ( $fudetector_option_rows as $fudetector_option_name ) {
        delete_option( $fudetector_option_name );
        if ( is_multisite() ) {
            delete_site_option( $fudetector_option_name );
        }
    }
}

/**
 * Delete all transients
 */
$fudetector_like = $wpdb->esc_like( '_transient_' . $fudetector_option_prefix ) . '%';
$fudetector_transient_rows = $wpdb->get_col( // phpcs:ignore
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $fudetector_like
    )
);

if ( is_array( $fudetector_transient_rows ) ) {
    foreach ( $fudetector_transient_rows as $fudetector_option_name ) {
        // Remove the transient name (WordPress strips _transient_ prefix automatically)
        $fudetector_name = preg_replace( '/^_transient_/', '', $fudetector_option_name );
        delete_transient( $fudetector_name );
    }
}

/**
 * Delete all usermeta keys
 */
$fudetector_meta_keys = [ 'suspicious' ];
foreach ( $fudetector_meta_keys as $fudetector_meta_key ) {
    $wpdb->query( // phpcs:ignore
        $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $fudetector_meta_key ) // phpcs:ignore
    );
}

// Done.
return;