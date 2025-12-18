<?php
/**
 * Plugin Name:         Fake User Detector
 * Plugin URI:          https://pluginrx.com/plugin/fake-user-detector/
 * Description:         Detect and flag fake user accounts based on suspicious input patterns.
 * Version:             1.0.2
 * Requires at least:   5.9
 * Tested up to:        6.9
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         fake-user-detector
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Created on:          June 3, 2025
 */


/**
 * Define Namespace
 */
namespace PluginRx\FakeUserDetector;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Defines
 */
$fudetector_plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'version'      => 'Version',
    'plugin_uri'   => 'Plugin URI',
    'requires_php' => 'Requires PHP',
    'textdomain'   => 'Text Domain',
    'author'       => 'Author',
    'author_uri'   => 'Author URI',
    'discord_uri'  => 'Discord URI'
] );

// Versions
define( 'FUDETECTOR_VERSION', $fudetector_plugin_data[ 'version' ] );
define( 'FUDETECTOR_SCRIPT_VERSION', time() );
define( 'FUDETECTOR_MIN_PHP_VERSION', $fudetector_plugin_data[ 'requires_php' ] );

// Names
define( 'FUDETECTOR_NAME', $fudetector_plugin_data[ 'name' ] );
define( 'FUDETECTOR_TEXTDOMAIN', $fudetector_plugin_data[ 'textdomain' ] );
define( 'FUDETECTOR__TEXTDOMAIN', str_replace( '-', '_', FUDETECTOR_TEXTDOMAIN ) );
define( 'FUDETECTOR_AUTHOR', $fudetector_plugin_data[ 'author' ] );
define( 'FUDETECTOR_AUTHOR_URI', $fudetector_plugin_data[ 'author_uri' ] );
define( 'FUDETECTOR_PLUGIN_URI', $fudetector_plugin_data[ 'plugin_uri' ] );
define( 'FUDETECTOR_GUIDE_URL', FUDETECTOR_AUTHOR_URI . 'guide/plugin/' . FUDETECTOR_TEXTDOMAIN . '/' );
define( 'FUDETECTOR_DOCS_URL', FUDETECTOR_AUTHOR_URI . 'docs/plugin/' . FUDETECTOR_TEXTDOMAIN . '/' );
define( 'FUDETECTOR_SUPPORT_URL', FUDETECTOR_AUTHOR_URI . 'support/plugin/' . FUDETECTOR_TEXTDOMAIN . '/' );
define( 'FUDETECTOR_DISCORD_URL', $fudetector_plugin_data[ 'discord_uri' ] );

// Paths
define( 'FUDETECTOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'FUDETECTOR_ABSPATH', plugin_dir_path( __FILE__ ) );
define( 'FUDETECTOR_DIR', plugin_dir_url( __FILE__ ) );
define( 'FUDETECTOR_INCLUDES_ABSPATH', FUDETECTOR_ABSPATH . 'inc/' );
define( 'FUDETECTOR_INCLUDES_DIR', FUDETECTOR_DIR . 'inc/' );
define( 'FUDETECTOR_JS_PATH', FUDETECTOR_INCLUDES_DIR . 'js/' );
define( 'FUDETECTOR_CSS_PATH', FUDETECTOR_INCLUDES_DIR . 'css/' );
define( 'FUDETECTOR_SETTINGS_PATH', admin_url( 'users.php?page=' . FUDETECTOR__TEXTDOMAIN ) );

// Screen IDs
define( 'FUDETECTOR_SETTINGS_SCREEN_ID', 'users_page_' . FUDETECTOR__TEXTDOMAIN );
define( 'FUDETECTOR_SCAN_SCREEN_ID', 'users_page_' . FUDETECTOR__TEXTDOMAIN . '_scan' );

/**
 * Includes
 */
require_once FUDETECTOR_INCLUDES_ABSPATH . 'common.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'user.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'users.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'quick-scan.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'flags.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'indicator.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'registration.php';
require_once FUDETECTOR_INCLUDES_ABSPATH . 'settings.php';

/**
 * Autoload integration classes
 */
$fudetector_integration_files = glob( FUDETECTOR_INCLUDES_ABSPATH . 'integrations/*.php' );
if ( $fudetector_integration_files ) {
    foreach ( $fudetector_integration_files as $fudetector_file ) {
        require_once $fudetector_file;
    }
}
