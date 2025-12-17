<?php 
/**
 * Plugin settings
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
add_action( 'init', function() {
	(new Settings())->init();
} );


/**
 * The class
 */
class Settings {
   
    /**
     * Load on init
     */
    public function init() {
        
		// Submenu
        add_action( 'admin_menu', [ $this, 'submenu' ] );
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'submenu_network' ] );
        }

		// Settings fields
        add_action( 'admin_init', [  $this, 'settings_fields' ] );

    } // End init()


	/**
     * Submenu
     *
     * @return void
     */
    public function submenu() {
        add_submenu_page(
            'users.php',
            FUDETECTOR_NAME . ' — ' . __( 'Settings', 'fake-user-detector' ),
            __( 'Account Monitor', 'fake-user-detector' ),
            'manage_options',
            FUDETECTOR__TEXTDOMAIN,
            [ $this, 'page' ]
        );
    } // End submenu()


    /**
     * Submenu for network admin.
     *
     * @return void
     */
    public function submenu_network() {
        $page = is_network_admin() ? [ $this, 'redirect_to_main_site_settings' ] : [ $this, 'page' ];
        add_submenu_page(
            'users.php', // still 'users.php' in network admin
            FUDETECTOR_NAME . ' — ' . __( 'Settings', 'fake-user-detector' ),
            __( 'Account Monitor', 'fake-user-detector' ),
            'manage_options',
            FUDETECTOR__TEXTDOMAIN,
            $page
        );
    } // End submenu_network()


    /**
     * Redirect to main site settings if in network admin.
     *
     * This is to ensure that the settings page is only accessible from the main site in a multisite setup.
     */
    public function redirect_to_main_site_settings() {
        if ( is_multisite() && is_network_admin() ) {
            $url = add_query_arg( [
                'page' => FUDETECTOR__TEXTDOMAIN
            ], get_admin_url( get_main_site_id(), 'users.php' ) );
            wp_safe_redirect( $url );
            exit;
        }
    } // End redirect_to_main_site_settings()

    
    /**
     * The page
     *
     * @return void
     */
    public function page() {
        global $current_screen;
        if ( $current_screen->id != FUDETECTOR_SETTINGS_SCREEN_ID ) {
            return;
        }
        ?>
		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ) ?></h1>

            <a href="/wp-admin/users.php?page=user_account_monitor_scan" id="fudetector-run-scan" class="button button-primary" style="margin-top: 1rem;">
                <?php esc_html_e( 'Run Quick Scan Now', 'fake-user-detector' ); ?>
            </a>

            <!-- Settings Form -->
            <br><br><br><br>
			<form method="post" action="options.php">
				<?php
					settings_fields( FUDETECTOR_TEXTDOMAIN );
					do_settings_sections( FUDETECTOR_TEXTDOMAIN );
                    submit_button();
				?>
			</form>
		</div>
        <?php
    } // End page()


    /**
     * The options
     *
     * @param boolean $return_keys_only
     * @param null|array $sections
     * @return array
     */
    public function options( $return_keys_only = false ) {
        // Settings
        $fields = [
            [
                'key'        => 'hide_cleared',
                'title'      => __( 'Temporarily Hide Cleared Users', 'fake-user-detector' ),
                'comments'   => __( 'Temporarily hide users that are cleared during the scan so viewing the flagged list is easier.', 'fake-user-detector' ),
                'field_type' => 'checkbox',
                'sanitize'   => 'sanitize_checkbox',
                'section'    => 'general',
                'default'    => FALSE,
            ],
            [
                'key'        => 'recheck_cleared',
                'title'      => __( 'Recheck Cleared Users', 'fake-user-detector' ),
                'comments'   => __( 'Recheck all previously cleared users.', 'fake-user-detector' ),
                'field_type' => 'checkbox',
                'sanitize'   => 'sanitize_checkbox',
                'section'    => 'general',
                'default'    => FALSE,
            ],
            [
                'key'        => 'log_flags',
                'title'      => __( 'Log Each Flagged User', 'fake-user-detector' ),
                'comments'   => __( 'Logs each user that is flagged to the Debug Log.', 'fake-user-detector' ),
                'field_type' => 'checkbox',
                'sanitize'   => 'sanitize_checkbox',
                'section'    => 'general',
                'default'    => FALSE,
            ],
            [
                'key'        => 'columns',
                'title'      => __( 'Additional Columns', 'fake-user-detector' ),
                'comments'   => __( 'Enter user meta keys (separated by commas) that you would like to display in the Users admin list table.', 'fake-user-detector' ),
                'field_type' => 'text',
                'sanitize'   => 'sanitize_text_field',
                'section'    => 'developer',
            ],
            [
                'key'        => 'profile_fields',
                'title'      => __( 'Edit Profile Fields', 'fake-user-detector' ),
                'comments'   => __( 'Enter user meta keys (separated by commas) that you would like to edit on the Users admin profile page.', 'fake-user-detector' ),
                'field_type' => 'textarea',
                'sanitize'   => 'sanitize_text_field',
                'section'    => 'developer',
            ],
        ];

        // Conditionally add auto-delete setting
        if ( apply_filters( 'fudetector_enable_auto_delete_option', false ) ) {
            $fields[] = [
                'key'        => 'auto_delete',
                'title'      => __( 'Auto-Delete Flagged Users', 'fake-user-detector' ),
                'comments'   => __( 'Automatically delete users flagged by any check.', 'fake-user-detector' ),
                'field_type' => 'checkbox',
                'sanitize'   => 'sanitize_checkbox',
                'section'    => 'general',
                'default'    => FALSE,
            ];
        }

        // Add the flags to check
        $fields = array_merge( $fields, (new Flags())->options() );

        // Integrations
        $fields = apply_filters( 'fudetector_integrations_fields', $fields );

        // Allow developers to customize options
        $fields = apply_filters( 'fudetector_settings_fields', $fields );

        if ( $return_keys_only ) {
            $field_keys = [];
            foreach ( $fields as $field ) {
                $field_keys[] = $field[ 'key' ];
            }
            return $field_keys;
        }
        return $fields;
    } // End options()


    /**
     * Settings fields
     *
     * @return void
     */
    public function settings_fields() {
        // Slug
        $slug = FUDETECTOR_TEXTDOMAIN;

        // Fields
        $fields = $this->options();

        /**
         * Sections
         */
        $sections = [
            [ 'general', __( 'General', 'fake-user-detector' ), '' ],
            [ 'checks', __( 'What do you want to check for?', 'fake-user-detector' ), '' ],
            [ 'integrations', __( 'Integrations', 'fake-user-detector' ), '' ],
            [ 'developer', __( 'Developer Settings', 'fake-user-detector' ), '' ],
        ];

        // Allow developers to customize sections
        $sections = apply_filters( 'fudetector_settings_sections', $sections );

        // Only include sections with fields
        $sections_to_add = [];
        foreach ( $sections as $section ) {
            $section_key = $section[0];
            
            // Check if any fields exist for the section
            $section_fields = array_filter( $fields, function( $field ) use ( $section_key ) {
                return isset( $field[ 'section' ] ) && $field[ 'section' ] === $section_key;
            } );

            // If there are fields for this section, add the section to the settings sections
            if ( !empty( $section_fields ) ) {
                $sections_to_add[] = $section;
            }
        }

        // Iter the filtered sections
        foreach ( $sections_to_add as $section ) {
            add_settings_section(
                $section[0],
                $section[1] . ':',
                $section[2],
                $slug,
                [ 'after_section' => '<br><br>' ]
            );
        }
        
        /**
         * Fields
         */
        // Iter the fields
        foreach ( $fields as $field ) {
            $option_name = 'fudetector_'.$field[ 'key' ];
            $callback = 'settings_field_'.$field[ 'field_type' ];

            if ( ! method_exists( $this, $callback ) ) {
                $full_callback = get_class( $this ) . '::' . $callback;
                error_log( FUDETECTOR_NAME . ': ' . sprintf( __( 'Method "%s" does not exist', 'fake-user-detector' ), $full_callback ) ); // phpcs:ignore
                continue;
            }

            // Hidden
            $incl_hidden_class = ( $field[ 'field_type' ] == 'hidden' ) ? ' hidden' : '';
            
            // Start the args
            $args = [
                'id'    => $option_name,
                'class' => $option_name . $incl_hidden_class,
                'name'  => $option_name,
            ];

            // Add comments
            if ( isset( $field[ 'comments' ] ) ) {
                $args[ 'comments' ] = $field[ 'comments' ];
            }
            
            // Add select options
            if ( isset( $field[ 'options' ] ) ) {
                $args[ 'options' ] = $field[ 'options' ];
            }

            // Add default
            if ( isset( $field[ 'default' ] ) ) {
                $args[ 'default' ] = $field[ 'default' ];
            }

            // Add revert
            if ( isset( $field[ 'revert' ] ) ) {
                $args[ 'revert' ] = $field[ 'revert' ];
            }

            $sanitize_callback = $field[ 'sanitize' ] === 'sanitize_checkbox'
                ? [ __CLASS__, 'sanitize_checkbox' ]
                : $field[ 'sanitize' ];

            $type = in_array( $field[ 'field_type' ], [ 'checkbox' ] ) ? 'boolean' : 'string';

            // Register the setting
            register_setting(
                $slug,
                $option_name,
                [
                    'sanitize_callback' => $sanitize_callback,
                    'type'              => $type,
                ]
            );

            // Add the field
            add_settings_field( $option_name, $field[ 'title' ], [ $this, $callback ], $slug, $field[ 'section' ], $args );
        }
    } // End settings_fields()
  
    
    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_text( $args ) {
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '43rem';
        $default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        $comments = isset( $args[ 'comments' ] ) ? '<br><p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            '<input type="text" id="%s" name="%s" value="%s" style="width: %s;" />%s',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( $value ),
            esc_attr( $width ),
            wp_kses_post( $comments )
        );
    } // settings_field_text()


    /**
     * Custom callback function to print textarea field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_textarea( $args ) {
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '43rem';
        $height = isset( $args[ 'height' ] ) ? $args[ 'height' ] : '10rem';
        $default = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] === true && trim( $value ) === '' ) {
            $value = $default;
        }
        
        $comments = isset( $args[ 'comments' ] ) ? '<br><p class="description">' . $args[ 'comments' ] . '</p>' : '';
        
        printf(
            '<textarea id="%s" name="%s" style="width: %s; height: %s;">%s</textarea>%s',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( $width ),
            esc_attr( $height ),
            esc_textarea( $value ),
            wp_kses_post( $comments )
        );
    } // settings_field_textarea()


    /**
     * Custom callback function to print hidden field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_hidden( $args ) {
        $value = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';

        printf(
            '<input type="hidden" id="%s" name="%s" value="%s" />',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( $value )
        );
    } // settings_field_hidden()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_checkbox( $args ) {
        $value = get_option( $args[ 'name' ] );
        if ( false === $value && isset( $args[ 'default' ] ) ) {
            $value = $args[ 'default' ];
        }
        $value = $this->sanitize_checkbox( $value );
        
        $comments = isset( $args[ 'comments' ] ) ? ' <p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s/>%s',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            checked( 1, $value, false ),
            wp_kses_post( $comments )
        );
    } // End settings_field_checkbox()    


    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return boolean
     */
    public static function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


    /**
     * Custom callback function to print select field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_select( $args ) {
        $width   = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '20rem';
        $options = isset( $args[ 'options' ] ) ? $args[ 'options' ] : [];
        $default = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';
        $value   = sanitize_key( get_option( $args[ 'name' ], $default ) );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] === true && trim( $value ) === '' ) {
            $value = $default;
        }
        $comments = isset( $args[ 'comments' ] ) ? '<br><p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            // Translators: %1$s is the select field id, %2$s is the select field name, %3$s is the CSS width style, %4$s is the rendered <option> tags, %5$s is comments HTML.
            '<select id="%1$s" name="%2$s" style="width: %3$s;">%4$s</select>%5$s',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( $width ),
            wp_kses(
                $this->render_select_options( $options, $value ),
                [
                    'option' => [
                        'value'    => true,
                        'selected' => true,
                    ],
                ]
            ),
            wp_kses_post( $comments )
        );
    } // End settings_field_select()


    /**
     * Renders <option> tags for a select field
     *
     * @param array  $options
     * @param string $selected
     * @return string
     */
    private function render_select_options( $options, $selected ) {
        $html = '';
        foreach ( $options as $val => $label ) {
            $html .= sprintf(
                // Translators: %1$s is the option value, %2$s is 'selected' if this is the current value, %3$s is the label text.
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr( $val ),
                selected( $selected, $val, false ),
                esc_html( $label )
            );
        }
        return $html;
    } // End render_select_options()

}
