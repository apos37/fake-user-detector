<?php
/**
 * Uninstall handler for Fake User Detector
 *
 * Deletes all plugin options and associated usermeta when uninstalled via
 * the WordPress plugin uninstaller.
 */

// Exit if not called by WP uninstall routine
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the cleanup setting is enabled
$cleanup_enabled = get_option( 'fudetector_uninstall_cleanup', false );
if ( ! $cleanup_enabled ) {
    return; // Do nothing if the option is not enabled
}

global $wpdb;

$option_prefix = 'fudetector_';


/**
 * Delete all options
 */
$like = $wpdb->esc_like( $option_prefix ) . '%';
$option_rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
if ( is_array( $option_rows ) ) {
    foreach ( $option_rows as $option_name ) {
        delete_option( $option_name );
        if ( is_multisite() ) {
            delete_site_option( $option_name );
        }
    }
}


/**
 * Delete all transients
 */
$like = $wpdb->esc_like( '_transient_' . $option_prefix ) . '%';
$transient_rows = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $like
    )
);

if ( is_array( $transient_rows ) ) {
    foreach ( $transient_rows as $option_name ) {
        // Remove the transient name (WordPress strips _transient_ prefix automatically)
        $name = preg_replace( '/^_transient_/', '', $option_name );
        delete_transient( $name );
    }
}


/**
 * Delete all user meta keys
 */
$meta_keys = [ 'suspicious' ];
foreach ( $meta_keys as $meta_key ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key ) );
}


// Done.
return;