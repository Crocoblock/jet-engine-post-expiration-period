<?php
/**
 * Plugin Name: JetEngine Post Expiration Period Module
 * Plugin URI:
 * Description:
 * Version:     1.0.1
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * Text Domain: jet-engine-post-expiration-period
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

add_action( 'plugins_loaded', 'jet_engine_post_ep_init' );

function jet_engine_post_ep_init() {

    define( 'JET_ENGINE_POST_EP_VERSION', '1.0.1' );

    define( 'JET_ENGINE_POST_EP__FILE__', __FILE__ );
    define( 'JET_ENGINE_POST_EP_PLUGIN_BASE', plugin_basename( JET_ENGINE_POST_EP__FILE__ ) );
    define( 'JET_ENGINE_POST_EP_PATH', plugin_dir_path( JET_ENGINE_POST_EP__FILE__ ) );
    define( 'JET_ENGINE_POST_EP_URL', plugins_url( '/', JET_ENGINE_POST_EP__FILE__ ) );

    require JET_ENGINE_POST_EP_PATH . 'plugin.php';
}
