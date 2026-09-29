<?php
namespace CC\Brella;

/**
 * Agenda grid: Bricks element, [brella_agenda] shortcode and front-end assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agenda {

	public static function init() {
		// The standalone "Brella Agenda" plugin is now part of this one.
		if ( defined( 'BRELLA_AGENDA_VERSION' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'duplicate_notice' ) );
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_element' ), 11 );
		add_shortcode( 'brella_agenda', array( __CLASS__, 'shortcode' ) );
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

	public static function duplicate_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'The Brella Agenda element is now built into W3 Brella Integration. Deactivate and delete the separate "Brella Agenda for Bricks" plugin; your agenda elements keep their settings.', 'cryptocon-brella' )
			. '</p></div>';
	}
}
