<?php 
/**
 * Users page
 */


/**
 * Define Namespaces
 */
namespace PluginRx\FakeUserDetector;
use PluginRx\FakeUserDetector\Indicator;
use PluginRx\FakeUserDetector\Flags;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
    (new Users())->init();
} );


/**
 * The class
 */
class Users {

    /**
     * Meta keys
     *
     * @var string
     */
    public $meta_key_suspicious = 'suspicious';


    /**
     * Nonce
     *
     * @var string
     */
    private $nonce_scan = 'fudetector_nonce_scan';
    private $nonce_clear = 'fudetector_nonce_clear';
    private $nonce_filter = 'fudetector_nonce_filter';


    /**
     * Available flags
     *
     * @var array
     */
    private $available_flags = [];

    
    /**
     * Constructor
     */
    public function __construct() {
        
        // Get the available flags
        $this->available_flags = array_merge( $this->available_flags, (new Flags())->options() );

    } // End __construct()


    /**
     * Load on init
     */
    public function init() {

        // Filter
        add_action( 'manage_users_extra_tablenav', [ $this, 'add_user_filter_dropdown' ], 9999999 );
        add_action( 'manage_users_network_extra_tablenav', [ $this, 'add_user_filter_dropdown' ], 9999999 );
        add_action( 'pre_get_users', [ $this, 'filter_users_list_query' ] );

        // Add a row action
        add_filter( 'user_row_actions', [ $this, 'add_clear_action_link' ], 10, 2 );
        add_filter( 'ms_user_row_actions', [ $this, 'add_clear_action_link' ], 10, 2 );

        // User column
        add_filter( 'manage_users_columns', [ $this, 'user_column' ] );
        add_filter( 'manage_users-network_columns', [ $this, 'user_column' ] );
        add_action( 'manage_users_custom_column', [ $this, 'user_column_content' ], 10, 3 );

        // Bulk edit
        add_filter( 'bulk_actions-users', [ $this, 'register_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-users', [ $this, 'process_bulk_actions' ], 10, 3 );

        // Scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


    /**
     * Add a dropdown filter to the Users admin screen for suspicious status.
     *
     * Options include: All Users, Not Checked, Cleared, Flagged.
     *
     * @return void
     */
    public function add_user_filter_dropdown( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $value = ( isset( $_GET['fudetector_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['fudetector_filter_nonce'] ) ), $this->nonce_filter ) ) ? ( isset( $_GET[ $this->meta_key_suspicious ] ) ? sanitize_text_field( wp_unslash( $_GET[ $this->meta_key_suspicious ] ) ) : '' ) : '';

        $nonce = wp_create_nonce( $this->nonce_filter );

        printf(
            '<div class="alignleft actions">
                <label class="screen-reader-text" for="fudetector_suspicious">%s</label>
                <select name="%s" id="fudetector_suspicious">
                    <option value="">%s</option>
                    <option value="not_checked"%s>%s</option>
                    <option value="cleared"%s>%s</option>
                    <option value="flagged"%s>%s</option>
                </select>
                <input type="hidden" name="fudetector_filter_nonce" value="%s" />
                <input type="submit" class="button" value="%s" />
            </div>',
            esc_html__( 'Filter by Status', 'fake-user-detector' ),
            esc_attr( $this->meta_key_suspicious ),
            esc_html__( 'All Users', 'fake-user-detector' ),
            selected( $value, 'not_checked', false ),
            esc_html__( 'Not Checked', 'fake-user-detector' ),
            selected( $value, 'cleared', false ),
            esc_html__( 'Cleared', 'fake-user-detector' ),
            selected( $value, 'flagged', false ),
            esc_html__( 'Suspicious', 'fake-user-detector' ),
            esc_attr( $nonce ),
            esc_attr__( 'Filter', 'fake-user-detector' )
        );
    } // End add_user_filter_dropdown()


    /**
     * Modify the user query based on the suspicious filter dropdown.
     *
     * Uses the 'suspicious' usermeta key:
     * - 'cleared'     ⇢ user has been manually cleared.
     * - anything else ⇢ user is considered flagged.
     * - not set       ⇢ user has not been checked.
     *
     * @param WP_User_Query $query The current user query object.
     * @return void
     */
    public function filter_users_list_query( $query ) {
        if ( ! is_admin() ) {
            return;
        }

        if ( ! $query instanceof \WP_User_Query ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'users' ) {
            return;
        }

        global $wpdb;

        $meta_query = [];

        // Suspicious filter
        $filter = isset( $_GET[ $this->meta_key_suspicious ] ) ? sanitize_text_field( wp_unslash( $_GET[ $this->meta_key_suspicious ] ) ) : '';
        if ( $filter !== '' ) {
            $nonce = isset( $_GET['fudetector_filter_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['fudetector_filter_nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, $this->nonce_filter ) ) {
                return;
            }

            if ( $filter === 'cleared' ) {
                $meta_query[] = [
                    'key'     => 'suspicious',
                    'value'   => 'cleared',
                    'compare' => '=',
                ];
            } elseif ( $filter === 'flagged' ) {
                $meta_query[] = [
                    'key'     => 'suspicious',
                    'value'   => 'cleared',
                    'compare' => '!=',
                ];
            } elseif ( $filter === 'not_checked' ) {
                $meta_query[] = [
                    'key'     => 'suspicious',
                    'compare' => 'NOT EXISTS',
                ];
            }
        }

        // Search box
        $search = '';
        if ( ! empty( $query->query_vars['search'] ) ) {
            $search = trim( $query->query_vars['search'], '*' );
        }

        if ( $search !== '' ) {
            $addt_columns = $this->get_addt_columns();
            if ( ! empty( $addt_columns ) ) {
                $meta_subqueries = [];
                foreach ( $addt_columns as $column ) {
                    $meta_key = esc_sql( $column['meta_key'] );
                    $like     = esc_sql( $wpdb->esc_like( $search ) );
                    $meta_subqueries[] = "ID IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key='{$meta_key}' AND meta_value LIKE '%{$like}%'
                    )";
                }

                if ( ! empty( $meta_subqueries ) ) {
                    $meta_query_sql = implode( ' OR ', $meta_subqueries );
                    // Inject into WP_User_Query's SQL
                    add_filter( 'pre_user_query', function( $uqi ) use ( $meta_query_sql ) {
                        $uqi->query_where = str_replace(
                            'WHERE 1=1 AND (',
                            "WHERE 1=1 AND ({$meta_query_sql} OR ",
                            $uqi->query_where
                        );
                    });
                }
            }
        }

        // Merge with existing meta_query
        if ( ! empty( $meta_query ) ) {
            if ( ! empty( $query->query_vars['meta_query'] ) ) {
                $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
            }
            $query->set( 'meta_query', $meta_query );
        }
    } // End filter_users_list_query()
    

    /**
     * Add clear action link
     *
     * @param array $actions
     * @param WP_User $user
     * @return array
     */
    public function add_clear_action_link( $actions, $user ) {
        // Allow manual scan per user
        $actions[ 'fudetector_scan' ] = sprintf(
            '<a href="#" class="fudetector-scan" data-userid="%d" data-method="scan">%s</a>',
            $user->ID,
            esc_html__( 'Check for Flags', 'fake-user-detector' )
        );

        // Allow manual marking
        $suspicious = get_user_meta( $user->ID, $this->meta_key_suspicious, true );
        if ( $suspicious !== 'cleared' ) {
            $actions[ 'fudetector_clear' ] = sprintf(
                '<a href="#" class="fudetector-clear" data-userid="%d" data-method="clear">%s</a>',
                $user->ID,
                esc_html__( 'Mark Not Suspicious', 'fake-user-detector' )
            );
        } else {
            $actions[ 'fudetector_flag' ] = sprintf(
                '<a href="#" class="fudetector-flag" data-userid="%d" data-method="flag">%s</a>',
                $user->ID,
                esc_html__( 'Mark Suspicious', 'fake-user-detector' )
            );
        }

        return $actions;
    } // End add_clear_action_link()


    /**
     * Get additional columns from settings
     *
     * @return array
     */
    private function get_addt_columns() {
        $columns = [];
        $addt_columns = get_option( 'fudetector_columns', '' );

        if ( $addt_columns ) {
            $addt_columns = array_map( 'trim', explode( ',', $addt_columns ) );

            foreach ( $addt_columns as $addt_column ) {
                if ( preg_match( '/^([^\(]+)\((.+)\)$/', $addt_column, $matches ) ) {
                    $meta_key = sanitize_key( trim( $matches[1] ) );
                    $title = sanitize_text_field( trim( $matches[2] ) );
                } else {
                    $meta_key = sanitize_key( $addt_column );
                    $title = ucwords( str_replace( [ '_', '-' ], ' ', $addt_column ) );
                }

                $column_id = 'fudetector_' . $meta_key;
                if ( ! array_key_exists( $column_id, $columns ) ) {
                    $columns[ $column_id ] = [
                        'meta_key' => $meta_key, // phpcs:ignore
                        'title'    => $title,
                    ];
                }
            }
        }

        return $columns;
    } // End get_addt_columns()


    /**
     * Add the user column
     *
     * @param array $columns
     * @return array
     */
    public function user_column( $columns ) {
        // Custom columns
        $addt_columns = $this->get_addt_columns();
        if ( ! empty( $addt_columns ) ) {
            foreach ( $addt_columns as $column_id => $data ) {
                if ( ! array_key_exists( $column_id, $columns ) ) {
                    $columns[ $column_id ] = esc_html( $data[ 'title' ] );
                }
            }
        }

        // Our column
        $columns[ 'suspicious' ] = __( 'Suspicious', 'fake-user-detector' );
        return $columns;
    } // End user_column()


    /**
     * Column content
     *
     * @param string $value
     * @param string $column_name
     * @param int $user_id
     * @return string
     */
    public function user_column_content( $value, $column_name, $user_id ) {
        // Custom columns
        $addt_columns = $this->get_addt_columns();
        if ( ! empty( $addt_columns ) && array_key_exists( $column_name, $addt_columns ) ) {
            $meta_value = filter_var( get_user_meta( $user_id, $addt_columns[ $column_name ][ 'meta_key' ], true ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            return esc_html( $meta_value );

        // Our column
        } elseif ( $column_name == 'suspicious' ) {
            $suspicious = get_user_meta( $user_id, $this->meta_key_suspicious, true );

            // They have been cleared - not suspicious
            if ( $suspicious === 'cleared' ) {
                return '<em data-suspicious-status="cleared" style="color: green">' . esc_html__( 'Cleared', 'fake-user-detector' ) . '</em>';
                
            // They have been flagged - suspicious
            } elseif ( is_array( $suspicious ) && !empty( $suspicious ) ) {
                $flag_names = [];
                foreach ( $suspicious as $flag ) {
                    foreach ( $this->available_flags as $available_flag ) {
                        if ( $available_flag[ 'key' ] == $flag ) {
                            $flag_names[] = $available_flag[ 'title' ];
                        }
                    }
                }
                return '<strong data-suspicious-status="flagged" style="color: red;">' . esc_html( implode( ', ', $flag_names ) ) . '</strong>';
            } else {
                return esc_html__( 'Not Checked', 'fake-user-detector' );
            }
        }
    } // End column_content()


    /**
     * Register bulk actions
     *
     * @param array $bulk_actions
     * @return array
     */
    public function register_bulk_actions( $bulk_actions ) {
        $bulk_actions[ 'mark_suspicious' ] = __( 'Mark Suspicious', 'fake-user-detector' );
        $bulk_actions[ 'mark_not_suspicious' ] = __( 'Mark Not Suspicious', 'fake-user-detector' );
        $bulk_actions[ 'mark_unchecked_suspicious' ] = __( 'Mark Unchecked for Suspicions', 'fake-user-detector' );
        return $bulk_actions;
    } // End register_bulk_actions()


    /**
     * Process bulk actions
     *
     * @param string $redirect_to
     * @param string $action
     * @param array $user_ids
     * @return string
     */
    public function process_bulk_actions( $redirect_to, $action, $user_ids ) {
        if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
            return $redirect_to;
        }

        foreach ( $user_ids as $user_id ) {
            switch ( $action ) {
                case 'mark_suspicious':
                    update_user_meta( $user_id, $this->meta_key_suspicious, [ 'admin_flag' ] );
                    break;
                case 'mark_not_suspicious':
                    update_user_meta( $user_id, $this->meta_key_suspicious, 'cleared' );
                    break;
                case 'mark_unchecked_suspicious':
                    delete_user_meta( $user_id, $this->meta_key_suspicious );
                    break;
            }
        }

        return $redirect_to;
    } // End process_bulk_actions()


    /**
	 * Enqueue JQuery
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
        // Only on the users page
        if ( $hook !== 'users.php' ) {
            return;
        }

        // Enqueue jquery
        $handle = FUDETECTOR_TEXTDOMAIN . 'users-js';
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle, FUDETECTOR_JS_PATH . 'users.js', [ 'jquery' ], FUDETECTOR_SCRIPT_VERSION, true );
        wp_localize_script( $handle, 'fudetector_users', [
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'nonce_scan'      => wp_create_nonce( $this->nonce_scan ),
            'nonce_clear'     => wp_create_nonce( $this->nonce_clear ),
            'hide_cleared'    => filter_var( get_option( 'fudetector_hide_cleared', false ), FILTER_VALIDATE_BOOLEAN ),
            'recheck_cleared' => filter_var( get_option( 'fudetector_recheck_cleared', false ), FILTER_VALIDATE_BOOLEAN ),
            'already_flagged' => (new Indicator())->count_flagged_users(),
            'text'            => [
                'start'      => __( 'Scan This Page for Suspicious Accounts', 'fake-user-detector' ),
                'stop'       => __( 'Stop Scanning', 'fake-user-detector' ),
                'scanning'   => __( 'Scanning', 'fake-user-detector' ),
                'cleared'    => __( 'Cleared', 'fake-user-detector' ),
                'complete'   => __( 'Scan Complete', 'fake-user-detector' ),
                'error'      => __( 'Error - Could Not Complete Scan', 'fake-user-detector' ),
                'mark_clear' => __( 'Mark Not Suspicious', 'fake-user-detector' ),
                'mark_flag'  => __( 'Mark Suspicious', 'fake-user-detector' ),
                'manual'     => __( 'Manually Flagged', 'fake-user-detector' )
            ]
        ] );
        
        // Enqueue css
        wp_enqueue_style( FUDETECTOR_TEXTDOMAIN . '-css', FUDETECTOR_CSS_PATH . 'users.css', [], FUDETECTOR_SCRIPT_VERSION );
    } // End enqueue_scripts()

}