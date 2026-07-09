<?php
/**
 * Plugin Name: Podcast Builder Pro
 * Plugin URI:  https://example.com/podcast-builder-pro
 * Description: Biến bài viết WordPress thành podcast tự động — AI script, TTS audio, RSS feed chuẩn iTunes, show notes có backlink và directory tracker.
 * Version:     1.0.1
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: podcast-builder-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// Constants
// ============================================================
define( 'PBP_VERSION',   '1.0.1' );
define( 'PBP_FILE',      __FILE__ );
define( 'PBP_PATH',      plugin_dir_path( __FILE__ ) );
define( 'PBP_URL',       plugin_dir_url( __FILE__ ) );
define( 'PBP_SLUG',      'podcast-builder-pro' );
define( 'PBP_DB_VERSION', '1.0' );

// ============================================================
// Helpers
// ============================================================
require_once PBP_PATH . 'includes/helpers.php';

// ============================================================
// Autoloader
// ============================================================
spl_autoload_register( function ( $class ) {
    $prefix = 'PBP_';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = strtolower( str_replace( [ $prefix, '_' ], [ '', '-' ], $class ) );
    $file = PBP_PATH . 'includes/class-pbp-' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// ============================================================
// Activation / Deactivation
// ============================================================
register_activation_hook( __FILE__, [ 'PBP_Core', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PBP_Core', 'deactivate' ] );

// ============================================================
// Boot
// ============================================================
add_action( 'plugins_loaded', function () {
    // Load text domain
    load_plugin_textdomain( 'podcast-builder-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Boot core
    PBP_Core::instance()->boot();
}, 10 );
