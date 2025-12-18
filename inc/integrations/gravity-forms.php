<?php
/**
 * Gravity Forms Integration
 */


/**
 * Define Namespaces
 */
namespace PluginRx\FakeUserDetector;
use PluginRx\FakeUserDetector\Flags;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new GravityForms();


/**
 * The class
 */
class GravityForms {

    /**
     * The setting key
     *
     * @var string
     */
    public $setting_key = 'gravity_forms_registration_form';


    /**
     * The registration form ID
     *
     * @var int
     */
    public $registration_form_id = 0;


    /**
     * Instantiate flags
     *
     * @var Flags
     */
    public $FLAGS;


    /**
     * Constructor
     */
    public function __construct() {

        // Stop if we're in the network admin
        if ( is_network_admin() ) {
            return;
        }

        // Stop if Gravity Forms is not active
        if ( !function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( !is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
            return;
        }

        // Instantiate flags
        $this->FLAGS = new Flags();

        // The setting
        add_filter( 'fudetector_integrations_fields', [ $this, 'setting_field' ] );

        // Check for flags at validation
        $this->registration_form_id = absint( get_option( 'fudetector_' . $this->setting_key ) );
        if ( $this->registration_form_id && $this->registration_form_id > 0 ) {
            add_filter( 'gform_field_validation_' . $this->registration_form_id, [ $this, 'validate_registration' ], 10, 4 );
        }

        // Add User Exists column to Gravity Forms entry list
        add_filter( 'gform_entry_list_columns_' . $this->registration_form_id, [ $this, 'add_user_exists_column' ], 10, 2 );
        add_filter( 'gform_entries_field_value', [ $this, 'populate_user_exists_column' ], 10, 4 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_fudetector_retry_user_registration', [ $this, 'ajax_retry_user_registration' ] );
        add_action( 'wp_ajax_nopriv_fudetector_retry_user_registration', '__return_false' );

    } // End __construct()


    /**
     * Add Gravity Forms registration form selector to settings if GF is active.
     *
     * @param array $fields Existing settings fields.
     * @return array Modified settings fields.
     */
    public function setting_field( $fields ) {
        $forms = \GFAPI::get_forms( null );
        $choices = [
            '' => __( 'Select a Form', 'fake-user-detector' )
        ];

        foreach ( $forms as $form ) {
            $title = $form[ 'title' ];
            if ( ! $form[ 'is_active' ] ) {
                $title .= ' (' . __( 'Inactive', 'fake-user-detector' ) . ')';
            }
            $choices[ $form[ 'id' ] ] = $title;
        }

        $fields[] = [
            'key'        => $this->setting_key,
            'title'      => __( 'Gravity Forms Registration Form Validation', 'fake-user-detector' ),
            'comments'   => __( 'Select the Gravity Form used for user registration to enable fake account checks during validation.<br>Keep in mind that if this is too strict, it may block legitimate users.<br>It is recommended to enable the "Check at Registration" option instead and use this only if necessary.', 'fake-user-detector' ),
            'field_type' => 'select',
            'options'    => $choices,
            'sanitize'   => 'absint',
            'section'    => 'integrations',
            'default'    => '',
        ];

        return $fields;
    } // End setting_field()


    /**
     * Validate Gravity Forms registration form for suspicious users.
     *
     * @param array $result
     * @param mixed $value
     * @param array $form
     * @param \GF_Field $field
     * @return array
     */
    public function validate_registration( $result, $value, $form, $field ) {
        if ( empty( $this->registration_form_id ) || $this->registration_form_id !== absint( $form[ 'id' ] ) ) {
            return $result;
        }

        // Validate only specific field types
        $valid_types = [ 'name', 'email', 'text', 'textarea' ];
        if ( ! in_array( $field->type, $valid_types, true ) ) {
            return $result;
        }

        // Initialize errors array
        $errors = [];

        // Name field validation
        if ( $field->type === 'name' || $field->inputName === 'name' ) {
            $names = [];

            if ( $field->type === 'name' && is_array( $value ) ) {
                $names[] = sanitize_text_field( rgar( $value, $field->id . '.3' ) ); // First name
                $names[] = sanitize_text_field( rgar( $value, $field->id . '.6' ) ); // Last name
            } else {
                $names[] = sanitize_text_field( $value );
            }

            foreach ( $names as $name ) {
                if ( get_option( 'fudetector_excessive_uppercase', true ) && $this->FLAGS->check_excessive_uppercase( $name ) ) {
                    $errors[] = 'Excessive uppercase letters';
                }
                if ( get_option( 'fudetector_no_vowels', true ) && $this->FLAGS->check_no_vowels( $name ) ) {
                    $errors[] = 'No vowels';
                }
                if ( get_option( 'fudetector_consonant_cluster', true ) && $this->FLAGS->check_consonant_cluster( $name ) ) {
                    $errors[] = 'Suspicious consonant clusters';
                }
                if ( get_option( 'fudetector_numbers', true ) && $this->FLAGS->check_numbers( $name ) ) {
                    $errors[] = 'Contains numbers';
                }
                if ( get_option( 'fudetector_special_characters', true ) && $this->FLAGS->check_special_characters( $name ) ) {
                    $errors[] = 'Contains special characters';
                }
                if ( get_option( 'fudetector_spam_words', true ) && $this->FLAGS->check_spam_words( $name ) ) {
                    $errors[] = 'Contains spam words';
                }
            }

            // if ( get_option( 'fudetector_similar_first_last_name', true ) && $field->type === 'name' && $this->FLAGS->check_similar_first_last_name( [
            //     'first_name' => sanitize_text_field( rgar( $value, $field->id . '.3' ) ),
            //     'last_name'  => sanitize_text_field( rgar( $value, $field->id . '.6' ) )
            // ] ) ) {
            //     $errors[] = 'First and last names are too similar';
            // }

            $errors = array_unique( $errors );

            if ( !empty( $errors ) ) {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ] = implode( '. ', $errors ) . '.';
            }

            return $result;
        }

        // Username/email logic handling
        $is_username = ( $field->inputName === 'username' || $field->inputName === 'user_name' );
        $is_email_field = ( $field->type === 'email' || $field->inputName === 'email' );
        $is_text_field_email = ( $field->type === 'text' && $is_username );

        if ( $is_email_field || $is_text_field_email ) {
            $email_value = sanitize_email( is_array( $value ) ? rgar( $value, 0 ) : $value );
            $errors = [];

            // Email-specific checks
            if (  get_option( 'fudetector_invalid_email_domain', true ) && $this->FLAGS->check_invalid_email_domain( $email_value ) ) {
                $errors[] = 'Invalid domain';
            }

            if ( get_option( 'fudetector_excessive_periods_email', true ) && $this->FLAGS->check_excessive_periods_email( $email_value ) ) {
                $errors[] = 'Excessive periods';
            }

            // Username check if this is also the username field
            if ( get_option( 'fudetector_url_in_username', true ) && $is_username && $this->FLAGS->check_url_in_username( $email_value ) ) {
                $errors[] = 'Contains URL';
            }

            $errors = array_unique( $errors );

            if ( !empty( $errors ) ) {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ] = 'Email issue(s): ' . implode( '. ', $errors ) . '.';
            }
            return $result;
        }

        // Username field validation (text inputName=username that is not an email)
        if ( $field->type === 'text' && $is_username && !is_email( $value ) ) {
            $username = sanitize_text_field( $value );
            if ( get_option( 'fudetector_url_in_username', true ) && $this->FLAGS->check_url_in_username( $username ) ) {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ] = 'Username contains a URL.';
            }
            return $result;
        }

        // Description / textarea spam words check
        if ( $field->type === 'textarea' || $field->inputName === 'description' ) {
            if ( get_option( 'fudetector_spam_words', true ) && $this->FLAGS->check_spam_words( sanitize_text_field( $value ) ) ) {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ] = 'Description contains spam words.';
            }
        }

        return $result;
    } // End validate_registration()


    /**
     * Add the "User Exists" column.
     *
     * @param array $columns Existing columns.
     * @param int   $form_id Current form ID.
     * @return array Modified columns.
     */
    public function add_user_exists_column( $columns, $form_id ) {
        $new_columns = [];

        foreach ( $columns as $key => $label ) {
            if ( $key === 'column_selector' ) {
                $new_columns[ 'fudetector_user_exists' ] = __( 'User Exists', 'fake-user-detector' );
            }
            $new_columns[ $key ] = $label;
        }

        return $new_columns;
    } // End add_user_exists_column()


    /**
     * Populate the "User Exists" column with Yes/No based on email field.
     *
     * @param string $value Current cell value.
     * @param string $form_id Current form ID.
     * @param array  $field Field object.
     * @param array  $entry Entry data.
     * @return string Updated cell value.
     */
    public function populate_user_exists_column( $value, $form_id, $field_id, $entry ) {
        if ( empty( $this->registration_form_id ) || $this->registration_form_id !== absint( $form_id ) ) {
            return $value;
        }

        // Only handle our custom column
        if ( 'fudetector_user_exists' !== $field_id ) {
            return $value;
        }

        // Get the form
        $form = \GFAPI::get_form( $form_id );
        if ( ! $form ) {
            return '';
        }

        // Find the email field ID
        $email_field_id = null;
        foreach ( $form[ 'fields' ] as $field ) {
            if ( $field->type === 'email' || $field->inputName === 'email' ) {
                $email_field_id = $field->id;
                break;
            }
        }

        // If no email field found, return placeholder
        if ( ! $email_field_id ) {
            return __( 'No Email Field', 'fake-user-detector' );
        }

        // Get the email from entry
        $email = rgar( $entry, $email_field_id );
        if ( empty( $email ) ) {
            return __( 'No Email Provided', 'fake-user-detector' );
        }

        $user = get_user_by( 'email', $email );
        if ( $user ) {
            return '<span class="fudetector-user-exists" style="color:green; font-weight:bold;">' . __( '✅ Yes', 'fake-user-detector' ) . '</span>';
        }

        // If no user, show Retry link only if GF User Registration add-on is active
        $retry_link = '';
        if ( class_exists( '\GFUser' ) ) {
            $retry_link = sprintf(
                '<br><a href="#" class="fudetector-retry-user" data-entry-id="%d" data-form-id="%d">%s</a>',
                absint( $entry['id'] ),
                absint( $form_id ),
                __( 'Retry', 'fake-user-detector' )
            );
        }

        return '<span class="fudetector-user-not-created" style="color:red; font-weight:bold;">' . __( '❌ No', 'fake-user-detector' ) . '</span>' . $retry_link;
    } // End populate_user_exists_column()


    /**
     * Enqueue custom admin CSS only on Gravity Forms entries screen.
     */
    public function enqueue_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'forms_page_gf_entries' ) {
            return;
        }

        // Enqueue Retry User JS
        if ( class_exists( '\GFUser' ) ) {

            $handle = FUDETECTOR_TEXTDOMAIN . 'retry-user-registration-js';
            wp_enqueue_script(
                $handle,
                FUDETECTOR_INCLUDES_DIR . 'integrations/gravity-forms-retry-user-registration.js',
                [ 'jquery' ],
                FUDETECTOR_SCRIPT_VERSION,
                true
            );

            wp_localize_script( $handle, 'fudetector_retry_user_registration', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'fudetector_retry_user' ),
                'creating' => __( 'Creating user...', 'fake-user-detector' ),
                'success'  => __( '✅ User created successfully.', 'fake-user-detector' ),
                'error'    => __( '❌ Failed to create user.', 'fake-user-detector' ),
            ] );
        }

        // Inline CSS
        $css = "
        tr:has(td.fudetector_user_exists .fudetector-user-not-created) {
            background-color: #ffe6e6 !important;
        }
        tr:has(td.fudetector_user_exists .fudetector-user-not-created):hover {
            background-color: #ffcccc !important;
        }";
        wp_add_inline_style( 'gform_admin', $css );
    } // End enqueue_assets()


    /**
     * AJAX handler to retry user creation from Gravity Forms entry.
     */
    public function ajax_retry_user_registration() {
        ob_start();
                
        check_ajax_referer( 'fudetector_retry_user', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( __( 'Permission denied.', 'fake-user-detector' ) );
        }

        $entry_id = absint( $_POST[ 'entry_id' ] ?? 0 );
        $form_id  = absint( $_POST[ 'form_id' ] ?? 0 );

        if ( ! $entry_id || ! $form_id ) {
            ob_end_clean();
            wp_send_json_error( __( 'Missing data.', 'fake-user-detector' ) );
        }

        $entry = \GFAPI::get_entry( $entry_id );
        if ( is_wp_error( $entry ) ) {
            ob_end_clean();
            wp_send_json_error( __( 'Entry not found.', 'fake-user-detector' ) );
        }

        $feeds = \GFAPI::get_feeds( null, $form_id, 'gravityformsuserregistration' );
        if ( empty( $feeds ) ) {
            ob_end_clean();
            wp_send_json_error( __( 'No User Registration feed found.', 'fake-user-detector' ) );
        }

        $feed = $feeds[ 0 ];
        $meta = $feed[ 'meta' ];

        // Standard mapped fields
        $username_field = $meta[ 'username' ] ?? '';
        $email_field    = $meta[ 'email' ] ?? '';
        $password_field = $meta[ 'password' ] ?? '';

        $username = rgar( $entry, $username_field );
        $email    = rgar( $entry, $email_field );
        $password = rgar( $entry, $password_field );

        if ( empty( $email ) ) {
            ob_end_clean();
            wp_send_json_error( __( 'Email missing in entry.', 'fake-user-detector' ) );
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            ob_end_clean();
            wp_send_json_error( __( 'User already exists.', 'fake-user-detector' ) );
        }

        if ( empty( $password ) ) {
            $password = wp_generate_password( 12, true );
        }

        $first_name = rgar( $entry, $meta[ 'first_name' ] ?? '' );
        $last_name  = rgar( $entry, $meta[ 'last_name' ] ?? '' );

        $nickname = '';
        $nickname_field_id = $meta[ 'nickname' ] ?? '';
        if ( $nickname_field_id && strpos( $nickname_field_id, '.' ) === false ) {
            
            $form = \GFAPI::get_form( $form_id );

            if ( $form ) {
                $field = \GFAPI::get_field( $form, $nickname_field_id );

                if ( $field && $field->type === 'name' ) {
                    $first = rgar( $entry, $nickname_field_id . '.3' );
                    $last  = rgar( $entry, $nickname_field_id . '.6' );
                    $nickname = trim( $first . ' ' . $last );
                }
            }
        }
        if ( empty( $nickname ) ) {
            $nickname = rgar( $entry, $nickname_field_id );
        }

        $display_name_reference = $meta[ 'displayname' ] ?? '';
        $display_name = '';

        switch ( $display_name_reference ) {
            case 'nickname':
                $display_name = $nickname;
                break;
            case 'username':
                $display_name = $username;
                break;
            case 'firstname':
                $display_name = $first_name;
                break;
            case 'lastname':
                $display_name = $last_name;
                break;
            case 'firstlast':
                $display_name = trim( $first_name . ' ' . $last_name );
                break;
            case 'lastfirst':
                $display_name = trim( $last_name . ' ' . $first_name );
                break;
        }

        if ( empty( $nickname ) && ! empty( $display_name ) ) {
            $nickname = $display_name;
        } elseif ( empty( $nickname ) && empty( $display_name ) ) {
            $display_name = trim( $first_name . ' ' . $last_name );
            $nickname = $display_name;
        }

        // Collect mapped fields into a preview array
        $user_data = [
            'username'    => $username,
            'email'       => $email,
            'password'    => '(hidden)',
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'nickname'    => $nickname,
            'displayname' => $display_name,
            'role'        => $meta[ 'role' ] ?? '',
            'user_meta'   => [],
        ];

        // Include custom user meta fields
        if ( ! empty( $meta[ 'userMeta' ] ) && is_array( $meta[ 'userMeta' ] ) ) {
            foreach ( $meta[ 'userMeta' ] as $item ) {
                $key         = $item[ 'key' ] ?? '';
                $custom_key  = $item[ 'custom_key' ] ?? '';
                $value_field = $item[ 'value' ] ?? '';
                $value       = rgar( $entry, $value_field );

                if ( empty( $key ) && empty( $custom_key ) ) {
                    continue;
                }

                $meta_key = ( $key === 'gf_custom' && ! empty( $custom_key ) ) ? $custom_key : $key;
                $user_data[ 'user_meta' ][ $meta_key ] = $value;
            }
        }

        // Create the user
        $user_id = wp_create_user(
            $username ?: sanitize_user( current( explode( '@', $email ) ) ),
            $password,
            $email
        );

        if ( is_wp_error( $user_id ) ) {
            ob_end_clean();
            wp_send_json_error( $user_id->get_error_message() );
        }

        // Apply the mapped role if valid
        if ( ! empty( $user_data[ 'role' ] ) && get_role( $user_data[ 'role' ] ) ) {
            $user = new \WP_User( $user_id );
            $user->set_role( $user_data[ 'role' ] );
        }

        // Standard user meta
        update_user_meta( $user_id, 'first_name',  $user_data[ 'first_name' ] );
        update_user_meta( $user_id, 'last_name',   $user_data[ 'last_name' ] );
        update_user_meta( $user_id, 'nickname',    $user_data[ 'nickname' ] );
        update_user_meta( $user_id, 'display_name', $user_data[ 'displayname' ] );

        // Update display name in main user record
        if ( ! empty( $user_data[ 'displayname' ] ) ) {
            wp_update_user( [
                'ID'           => $user_id,
                'display_name' => $user_data[ 'displayname' ],
            ] );
        }

        // Add custom user meta fields
        foreach ( $user_data[ 'user_meta' ] as $meta_key => $meta_value ) {
            update_user_meta( $user_id, $meta_key, $meta_value );
        }

        $current_user = wp_get_current_user();
        $from_name  = $current_user->display_name ?: $current_user->user_login;
        $from_email = $current_user->user_email;

        $subject = __( 'Your account has been created', 'fake-user-detector' );
        $message = sprintf(
            // translators: 1: User display name (or first name fallback), 2: Username, 3: Password, 4: Admin name (current user)
            __( "Hello %1\$s,\n\nWe have pushed your registration through. You should now be able to log in using the following credentials:\n\nUsername: %2\$s\nPassword: %3\$s\n\nIf you have any questions, please reply to this email.\n\nThank you!\n\n%4\$s", 'fake-user-detector' ),
            $user_data[ 'displayname' ] ?: $user_data[ 'first_name' ],
            $username,
            $password,
            $from_name
        );

        // Set email headers to use current user as sender and CC them
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            "From: $from_name <$from_email>",
            "Cc: $from_email",
        ];

        // Send the email
        wp_mail( $email, $subject, $message, $headers );

        ob_end_clean();
        wp_send_json_success( [
            'message'   => __( 'User data processed successfully.', 'fake-user-detector' ),
            'user_id'   => $user_id,
            'user_data' => $user_data,
        ] );
    } // End ajax_retry_user_registration()


}