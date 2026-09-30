<?php
namespace CC\Brella;

/**
 * Agenda grid: Bricks element, [brella_agenda] shortcode and front-end assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agenda {

	/**
	 * The standalone plugin this element replaced.
	 */
	const LEGACY_PLUGIN = 'brella-agenda/brella-agenda.php';

	public static function init() {
		// Register after the old standalone "Brella Agenda" plugin (priority 11) so
		// this element and shortcode replace its versions if it is still active.
		add_action( 'init', array( __CLASS__, 'register_element' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_shortcode' ), 20 );

		// Switch the old standalone plugin off; it is now part of this one.
		add_action( 'admin_init', array( __CLASS__, 'deactivate_legacy_plugin' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( CC_BRELLA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'options-general.php?page=cryptocon-brella' );
	}

	public static function enqueue() {
		wp_enqueue_style( 'cc-brella-agenda', CC_BRELLA_URL . 'assets/agenda.css', array(), CC_BRELLA_VERSION );
		wp_enqueue_script( 'cc-brella-agenda', CC_BRELLA_URL . 'assets/agenda.js', array(), CC_BRELLA_VERSION, true );
	}

	public static function register_shortcode() {
		remove_shortcode( 'brella_agenda' );
		add_shortcode( 'brella_agenda', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_element() {
		if ( class_exists( '\Bricks\Elements' ) ) {
			\Bricks\Elements::register_element(
				CC_BRELLA_PATH . 'includes/agenda/element-agenda.php',
				'brella-agenda',
				'\CC\Brella\Agenda_Element'
			);
		}
	}

	/**
	 * [brella_agenda theme="dark" step="5" label_interval="30" tracks="Main Stage|Hall A" group_by="location"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( Agenda_Renderer::defaults(), (array) $atts, 'brella_agenda' );
		self::enqueue();
		return Agenda_Renderer::render_wrapped( $atts );
	}

	/**
	 * @param array<int,string> $links Plugin row links.
	 * @return array<int,string>
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'cryptocon-brella' ) . '</a>' );
		return $links;
	}

	/**
	 * Deactivate the old standalone Brella Agenda plugin if it is still on.
	 */
	public static function deactivate_legacy_plugin() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( self::LEGACY_PLUGIN ) ) {
			deactivate_plugins( self::LEGACY_PLUGIN );
			set_transient( 'cc_brella_legacy_agenda_off', 1, HOUR_IN_SECONDS );
		}
	}

	public static function legacy_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || ! get_transient( 'cc_brella_legacy_agenda_off' ) ) {
			return;
		}
		delete_transient( 'cc_brella_legacy_agenda_off' );
		echo '<div class="notice notice-info is-dismissible"><p>'
			. esc_html__( 'The separate "Brella Agenda for Bricks" plugin has been deactivated because the Brella Agenda element is now built into W3 Brella Integration. You can delete it. Agenda elements on your pages keep their settings.', 'cryptocon-brella' )
			. '</p></div>';
	}
}
