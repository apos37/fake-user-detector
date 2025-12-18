<?php
/**
 * Version check, activation, deactivation, uninstallation, etc.
 */


/**
 * Define Namespaces
 */
namespace PluginRx\FakeUserDetector;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new Common();


/**
 * The class
 */
class Common {

    /**
     * Constructor
     */
    public function __construct() {

        // PHP Version check
		$this->check_php_version();

		// Add links to the website and discord
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        
    } // End __construct()


	/**
	 * Prevent loading the plugin if PHP version is not minimum
	 *
	 * @return void
	 */
	public function check_php_version() {
		if ( version_compare( PHP_VERSION, FUDETECTOR_MIN_PHP_VERSION, '<=' ) ) {
			add_action( 'admin_init', function() {
				deactivate_plugins( FUDETECTOR_BASENAME );
			} );
			add_action( 'admin_notices', function() {
				/* translators: 1: Plugin name, 2: Required PHP version */
				$notice = sprintf( __( '%1$s requires PHP %2$s or newer.', 'fake-user-detector' ),
					FUDETECTOR_NAME,
					FUDETECTOR_MIN_PHP_VERSION
				);
				echo wp_kses_post(
					'<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>'
				);
			} );
			return;
		}
	} // End check_php_version()


	/**
     * Add links to the website and discord
     *
     * @param array $links
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        $text_domain = FUDETECTOR_TEXTDOMAIN;
        if ( $text_domain . '/' . $text_domain . '.php' == $file ) {

            $guide_url = FUDETECTOR_GUIDE_URL;
            $docs_url = FUDETECTOR_DOCS_URL;
            $support_url = FUDETECTOR_SUPPORT_URL;
            $plugin_name = FUDETECTOR_NAME;

            $our_links = [
                'guide' => [
                    // translators: Link label for the plugin's user-facing guide.
                    'label' => __( 'How-To Guide', 'fake-user-detector' ),
                    'url'   => $guide_url
                ],
                'docs' => [
                    // translators: Link label for the plugin's developer documentation.
                    'label' => __( 'Developer Docs', 'fake-user-detector' ),
                    'url'   => $docs_url
                ],
                'support' => [
                    // translators: Link label for the plugin's support page.
                    'label' => __( 'Support', 'fake-user-detector' ),
                    'url'   => $support_url
                ],
            ];

            $row_meta = [];
            foreach ( $our_links as $key => $link ) {
                // translators: %1$s is the link label, %2$s is the plugin name.
                $aria_label = sprintf( __( '%1$s for %2$s', 'fake-user-detector' ), $link[ 'label' ], $plugin_name );
                $row_meta[ $key ] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $link[ 'label' ] ) . '</a>';
            }

            // Add the links
            return array_merge( $links, $row_meta );
        }

        // Return the links
        return (array) $links;
    } // End plugin_row_meta()

}