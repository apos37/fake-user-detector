<?php 
/**
 * Quick scan
 */


/**
 * Define Namespaces
 */
namespace PluginRx\FakeUserDetector;
use PluginRx\FakeUserDetector\Flags;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new QuickScan();


/**
 * The class
 */
class QuickScan {

    /**
     * Scan batch size
     *
     * @var int
     */
    const SCAN_BATCH_SIZE = 100;


    /**
     * Nonces
     *
     * @var string
     */
    private $nonce_scan = 'fudetector_nonce_quick_scan';
    private $nonce_filter = 'fudetector_nonce_filter';


    /**
     * Constructor
     */
    public function __construct() {

        // Hidden scan page
        add_action( 'admin_menu', [ $this, 'page' ] );

        // AJAX handler for full scan
        add_action( 'wp_ajax_fudetector_full_scan', [ $this, 'ajax_full_scan' ] );

        // Scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End __construct()


    /**
     * Register hidden scan page (not shown in menu)
     */
    public function page() {
        add_submenu_page(
            'users.php',
            FUDETECTOR_NAME . ' — ' . __( 'Scan', 'fake-user-detector' ),
            'Account Monitor Scan',
            'manage_options',
            'user_account_monitor_scan',
            [ $this, 'scan_page' ]
        );

        remove_submenu_page( 'users.php', 'user_account_monitor_scan' );
    } // End page()
    

    /**
     * The scan page content
     */
    public function scan_page() {
        global $current_screen;
        if ( $current_screen->id != FUDETECTOR_SCAN_SCREEN_ID ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( FUDETECTOR_NAME . ' — ' . __( 'Quick Scan', 'fake-user-detector' ) ); ?></h1>

            <p><?php esc_html_e( 'Click the button below to run a full scan of all users. Progress will be displayed as the scan runs.', 'fake-user-detector' ); ?></p>

            <button id="fudetector-start-scan" class="button button-primary">
                <?php esc_html_e( 'Run Full Scan', 'fake-user-detector' ); ?>
            </button>

            <button id="fudetector-cancel-scan" class="button" style="margin-left:6px; display:none;">
                <?php esc_html_e( 'Cancel Scan', 'fake-user-detector' ); ?>
            </button>

            <br><br>
            <div id="fudetector-scan-progress" style="margin-top:20px;display:none;">
                <div style="background:#e1e1e1;border:1px solid #ccc;height:22px;max-width:500px;position:relative;">
                    <div id="fudetector-progress-bar" style="background:#46b450;height:100%;width:0;"></div>
                </div>
                <p id="fudetector-progress-text" style="margin-top:8px;"></p>
            </div>
            <br><br>
        </div>
        <?php
    } // End scan_page()


    /**
     * AJAX handler for full scan
     */
    public function ajax_full_scan() {
        check_ajax_referer( $this->nonce_scan, 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'fake-user-detector' ) ] );
        }

        global $wpdb;
        $last_id   = isset( $_POST[ 'last_id' ] ) ? intval( $_POST[ 'last_id' ] ) : 0;
        $batch     = isset( $_POST[ 'batch' ] ) ? intval( $_POST[ 'batch' ] ) : 100;

        // phpcs:ignore 
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID ASC LIMIT %d",
            $last_id,
            $batch
        ) );

        if ( empty( $rows ) ) {
            wp_send_json_success( [
                'done' => true,
            ] );
        }

        $processed     = 0;
        $flagged_count = 0;
        $new_last_id   = $last_id;

        foreach ( $rows as $row ) {
            $processed++;

            $result = (new IndividualUser())->check( $row->ID, false, true );
            if ( is_array( $result ) && !empty( $result ) ) {
                $flagged_count++;
            }
            $new_last_id = (int) $row->ID;
        }

        wp_send_json_success( [
            'done'          => false,
            'last_id'       => $new_last_id,
            'processed'     => $processed,
            'flagged_count' => $flagged_count,
        ] );
    } // End ajax_full_scan()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'users_page_'.FUDETECTOR__TEXTDOMAIN . '_scan' ) {
            return;
        }

        // Enqueue jquery
        $handle = FUDETECTOR_TEXTDOMAIN . 'quick-scan-js';
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle, FUDETECTOR_JS_PATH . 'quick-scan.js', [ 'jquery' ], FUDETECTOR_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'fudetector_quick_scan', [
            'nonce_scan'   => wp_create_nonce( $this->nonce_scan ),
            'nonce_filter' => wp_create_nonce( $this->nonce_filter ),
            'batch_size'   => self::SCAN_BATCH_SIZE,
            'total_users'  => (int) count_users()[ 'total_users' ],
            'button_text'  => __( 'Run Full Scan', 'fake-user-detector' ),
        ] );
    } // End enqueue_scripts()

}
