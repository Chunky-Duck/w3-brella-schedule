<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings stored in wp_options.
 */
class Settings {

	const DEFAULTS = array(
		'api_key'             => '',
		'organization_id'     => '',
		'event_id'            => '',
		'cache_ttl_minutes'   => 30,
		'exclude_networking'  => 1,
		'timezone'            => '',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		$stored = get_option( CC_BRELLA_SETTINGS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return null !== $default ? $default : ( self::DEFAULTS[ $key ] ?? null );
	}

	/**
	 * @return bool
	 */
	public static function has_api_key() {
		$key = (string) self::get( 'api_key', '' );
		return '' !== trim( $key );
	}

	/**
	 * @return bool
	 */
	public static function has_organization_id() {
		return '' !== trim( (string) self::get( 'organization_id', '' ) );
	}

	/**
	 * @return bool
	 */
	public static function has_event_id() {
		return '' !== trim( (string) self::get( 'event_id', '' ) );
	}

	/**
	 * @return bool
	 */
	public static function credentials_complete() {
		return self::has_api_key() && self::has_organization_id() && self::has_event_id();
	}

	/**
	 * @return int
	 */
	public static function cache_ttl_seconds() {
		$minutes = (int) self::get( 'cache_ttl_minutes', 30 );
		if ( $minutes < 1 ) {
			$minutes = 30;
		}
		return $minutes * MINUTE_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	public static function exclude_networking() {
		return (bool) self::get( 'exclude_networking', 1 );
	}

	/**
	 * Credentials for API calls (saved settings).
	 *
	 * @return array{api_key:string,organization_id:string,event_id:string}|null
	 */
	public static function credentials() {
		if ( ! self::credentials_complete() ) {
			return null;
		}
		return array(
			'api_key'          => trim( (string) self::get( 'api_key', '' ) ),
			'organization_id'  => trim( (string) self::get( 'organization_id', '' ) ),
			'event_id'         => trim( (string) self::get( 'event_id', '' ) ),
		);
	}

	/**
	 * Merge posted form values with saved settings (for test before save).
	 *
	 * @param array<string,mixed> $posted Posted field values.
	 * @return array{api_key:string,organization_id:string,event_id:string}|null
	 */
	public static function credentials_from_input( array $posted ) {
		$saved = self::get_all();

		$org = isset( $posted['organization_id'] ) ? trim( (string) $posted['organization_id'] ) : '';
		$event = isset( $posted['event_id'] ) ? trim( (string) $posted['event_id'] ) : '';

		$api_key = '';
		if ( ! empty( $posted['api_key'] ) ) {
			$api_key = trim( (string) $posted['api_key'] );
		} elseif ( ! empty( $saved['api_key'] ) ) {
			$api_key = trim( (string) $saved['api_key'] );
		}

		if ( '' === $api_key || '' === $org || '' === $event ) {
			return null;
		}

		return array(
			'api_key'         => $api_key,
			'organization_id' => $org,
			'event_id'        => $event,
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		add_action( 'wp_ajax_cryptocon_brella_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_post_cryptocon_brella_refresh', array( __CLASS__, 'handle_refresh' ) );
	}

	public static function register_menu() {
		add_options_page(
			__( 'Brella Schedule', 'cryptocon-brella' ),
			__( 'Brella Schedule', 'cryptocon-brella' ),
			'manage_options',
			'cryptocon-brella',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'cryptocon_brella',
			CC_BRELLA_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::DEFAULTS,
			)
		);
	}

	/**
	 * @param array<string,mixed>|mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $input ) {
		$current = self::get_all();
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$out = array(
			'organization_id'    => isset( $input['organization_id'] ) ? sanitize_text_field( $input['organization_id'] ) : '',
			'event_id'           => isset( $input['event_id'] ) ? sanitize_text_field( $input['event_id'] ) : '',
			'cache_ttl_minutes'  => isset( $input['cache_ttl_minutes'] ) ? max( 1, (int) $input['cache_ttl_minutes'] ) : 30,
			'exclude_networking' => ! empty( $input['exclude_networking'] ) ? 1 : 0,
			'timezone'           => isset( $input['timezone'] ) ? sanitize_text_field( $input['timezone'] ) : '',
		);

		if ( ! empty( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( $input['api_key'] );
		} else {
			$out['api_key'] = $current['api_key'] ?? '';
		}

		return $out;
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_cryptocon-brella' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cryptocon-brella-admin',
			CC_BRELLA_URL . 'assets/admin.css',
			array(),
			CC_BRELLA_VERSION
		);

		wp_enqueue_script(
			'cryptocon-brella-admin',
			CC_BRELLA_URL . 'assets/admin.js',
			array(),
			CC_BRELLA_VERSION,
			true
		);

		wp_localize_script(
			'cryptocon-brella-admin',
			'cryptoconBrellaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cryptocon_brella_test' ),
				'i18n'    => array(
					'testing'  => __( 'Testing connection…', 'cryptocon-brella' ),
					'missing'  => __( 'Please enter API key, Organization ID, and Event ID.', 'cryptocon-brella' ),
					'error'    => __( 'Connection failed.', 'cryptocon-brella' ),
				),
			)
		);
	}

	public static function ajax_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cryptocon-brella' ) ), 403 );
		}

		check_ajax_referer( 'cryptocon_brella_test', 'nonce' );

		$creds = self::credentials_from_input(
			array(
				'api_key'          => isset( $_POST['api_key'] ) ? wp_unslash( $_POST['api_key'] ) : '',
				'organization_id'  => isset( $_POST['organization_id'] ) ? wp_unslash( $_POST['organization_id'] ) : '',
				'event_id'         => isset( $_POST['event_id'] ) ? wp_unslash( $_POST['event_id'] ) : '',
			)
		);

		if ( ! $creds ) {
			wp_send_json_error(
				array(
					'message' => __( 'API key, Organization ID, and Event ID are all required.', 'cryptocon-brella' ),
					'hint'    => __( 'Enter all three fields. Leave API key blank only if one is already saved.', 'cryptocon-brella' ),
				)
			);
		}

		$result = Api_Client::test_connection( $creds );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$hint       = is_array( $error_data ) && ! empty( $error_data['hint'] ) ? $error_data['hint'] : '';
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'hint'    => $hint,
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cryptocon-brella' ) );
		}

		check_admin_referer( 'cryptocon_brella_refresh' );

		$result = Schedule_Sync::instance()->refresh( true );

		$redirect = add_query_arg(
			array(
				'page'             => 'cryptocon-brella',
				'brella_refreshed' => is_wp_error( $result ) ? '0' : '1',
				'brella_error'     => is_wp_error( $result ) ? rawurlencode( $result->get_error_message() ) : '',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_all();
		$cache    = Cache::get_payload();
		$status   = self::credential_status( $settings );

		if ( isset( $_GET['brella_refreshed'] ) ) {
			if ( '1' === $_GET['brella_refreshed'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule refreshed successfully.', 'cryptocon-brella' ) . '</p></div>';
			} else {
				$error = isset( $_GET['brella_error'] ) ? sanitize_text_field( wp_unslash( $_GET['brella_error'] ) ) : '';
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ? $error : __( 'Refresh failed.', 'cryptocon-brella' ) ) . '</p></div>';
			}
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cryptocon-brella' ) . '</p></div>';
		}

		include CC_BRELLA_PATH . 'includes/views/settings-page.php';
	}

	/**
	 * @param array<string,mixed> $settings Settings array.
	 * @return array<string,bool>
	 */
	private static function credential_status( array $settings ) {
		return array(
			'api_key'          => '' !== trim( (string) ( $settings['api_key'] ?? '' ) ),
			'organization_id'  => '' !== trim( (string) ( $settings['organization_id'] ?? '' ) ),
			'event_id'         => '' !== trim( (string) ( $settings['event_id'] ?? '' ) ),
		);
	}
}
