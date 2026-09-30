<?php
/**
 * Plugin Name: W3 Brella Integration
 * Plugin URI:  https://chunkyduck.com
 * Description: Sync Brella event schedule into WordPress with caching, Bricks query loops, dynamic tags and a Brella Agenda grid element.
 * Version:     1.2.4
 * Author:      Chunky Duck
 * Author URI:   https://chunkyduck.com
 * Text Domain: cryptocon-brella
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC_BRELLA_VERSION', '1.2.4' );
define( 'CC_BRELLA_FILE', __FILE__ );
define( 'CC_BRELLA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CC_BRELLA_URL', plugin_dir_url( __FILE__ ) );
define( 'CC_BRELLA_SETTINGS_OPTION', 'cryptocon_brella_settings' );
define( 'CC_BRELLA_SCHEDULE_OPTION', 'cryptocon_brella_schedule' );
define( 'CC_BRELLA_REFRESH_LOCK', 'cryptocon_brella_refresh_lock' );

require_once CC_BRELLA_PATH . 'includes/class-settings.php';
require_once CC_BRELLA_PATH . 'includes/class-api-client.php';
require_once CC_BRELLA_PATH . 'includes/class-cache.php';
require_once CC_BRELLA_PATH . 'includes/class-normalizer.php';
require_once CC_BRELLA_PATH . 'includes/class-schedule-sync.php';
require_once CC_BRELLA_PATH . 'includes/class-bricks-query.php';
require_once CC_BRELLA_PATH . 'includes/class-bricks-tags.php';
require_once CC_BRELLA_PATH . 'includes/agenda/class-agenda.php';
require_once CC_BRELLA_PATH . 'includes/agenda/class-agenda-source.php';
require_once CC_BRELLA_PATH . 'includes/agenda/class-agenda-renderer.php';

/**
 * Bootstrap plugin.
 */
function cc_brella_bootstrap() {
	CC\Brella\Settings::init();
	CC\Brella\Schedule_Sync::init();
	CC\Brella\Bricks_Query::init();
	CC\Brella\Bricks_Tags::init();
	CC\Brella\Agenda::init();
}
add_action( 'plugins_loaded', 'cc_brella_bootstrap', 20 );

/**
 * @return CC\Brella\Schedule_Sync
 */
function cc_brella() {
	return CC\Brella\Schedule_Sync::instance();
}
