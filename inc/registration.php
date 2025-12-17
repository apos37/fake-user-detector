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
if ( !defined( 'ABSPATH' ) ) exit;


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

        // Check for flags on new user registration
        add_action( 'user_register', [ $this, 'check_new_user' ], 10, 1 );

    } // End __construct()


    /**
     * Check the new user after registration
     *
     * @param int $user_id
     * @return void
     */
    public function check_new_user( $user_id ) {
        (new IndividualUser())->check( $user_id, false, false, false );
    } // End check_new_user()

}