<?php
/**
 * Registration actions
 */


/**
 * Define Namespaces
 */
namespace PluginRx\FakeUserDetector;
use PluginRx\FakeUserDetector\IndividualUser;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new Registration();


/**
 * The class
 */
class Registration {

    /**
     * Constructor
     */
    public function __construct() {

        // Enable check new users at registration
        if ( ! get_option( 'fudetector_check_at_registration', true ) ) {
            return;
        }

        // Schedule a deferred check for new users
        add_action( 'user_register', [ $this, 'schedule_new_user_check' ], 10, 1 );

        // Hook the cron to run the actual check
        add_action( 'fudetector_check_new_user_cron', [ $this, 'check_new_user_cron' ], 10, 1 );

    } // End __construct()


    /**
     * Schedule a one-off cron to check the new user
     *
     * @param int $user_id
     */
    public function schedule_new_user_check( $user_id ) {
        if ( ! wp_next_scheduled( 'fudetector_check_new_user_cron', [ $user_id ] ) ) {
            wp_schedule_single_event( time() + 10, 'fudetector_check_new_user_cron', [ $user_id ] );
        }
    } // End schedule_new_user_check()
    

    /**
     * Run the actual user check via cron
     *
     * @param int $user_id
     */
    public function check_new_user_cron( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $first_name   = get_user_meta( $user_id, 'first_name', true );
        $last_name    = get_user_meta( $user_id, 'last_name', true );
        $display_name = $user->display_name;

        if ( empty( $first_name ) || empty( $last_name ) || empty( $display_name ) ) {
            return;
        }

        ( new IndividualUser() )->check( $user_id, false, false, false );
    } // End check_new_user_cron()

}