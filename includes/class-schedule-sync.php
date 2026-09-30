<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch, normalize, and cache Brella schedule data.
 */
class Schedule_Sync {

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_refresh_on_request' ), 20 );
	}

	/**
	 * Refresh stale cache on frontend/admin when credentials exist.
	 */
	public static function maybe_refresh_on_request() {
		if ( ! Settings::credentials_complete() ) {
			return;
		}

		if ( Cache::is_fresh() ) {
			return;
		}

		self::instance()->refresh( false );
	}

	/**
	 * @param bool $force Skip lock and refresh even if fresh when true.
	 * @return true|\WP_Error
	 */
	public function refresh( $force = false ) {
		$creds = Settings::credentials();
		if ( ! $creds ) {
			return new \WP_Error( 'brella_missing_credentials', __( 'Brella credentials are incomplete.', 'cryptocon-brella' ) );
		}

		if ( ! $force && Cache::is_fresh() ) {
			return true;
		}

		if ( ! $force && ! Cache::acquire_refresh_lock() ) {
			return true;
		}

		try {
			$raw = Api_Client::fetch_all_timeslots( $creds );
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}

			$timezone = (string) Settings::get( 'timezone', '' );
			if ( '' === $timezone ) {
				$fetched_tz = Api_Client::fetch_event_timezone( $creds );
				if ( is_string( $fetched_tz ) && '' !== $fetched_tz ) {
					$timezone = $fetched_tz;
				}
			}

			$sessions = Normalizer::normalize_timeslots(
				$raw['data'] ?? array(),
				$raw['included'] ?? array(),
				$timezone
			);

			// Sponsored tracks: fetch logos if the timeslots only carried sponsor ids.
			// A failure here (e.g. no sponsor access on the key) never blocks the sync.
			if ( Normalizer::needs_sponsor_lookup( $sessions ) ) {
				$sponsor_map = Api_Client::fetch_sponsor_map( $creds );
				if ( is_array( $sponsor_map ) && $sponsor_map ) {
					$sessions = Normalizer::fill_sponsors( $sessions, $sponsor_map );
				}
			}

			Cache::save(
				$sessions,
				array(
					'timezone'     => $timezone,
					'raw_count'    => count( $raw['data'] ?? array() ),
					'filtered_count' => count( $sessions ),
				)
			);

			return true;
		} finally {
			Cache::release_refresh_lock();
		}
	}

	/**
	 * Sessions for Bricks query (auto-refresh if stale).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_sessions_for_display() {
		if ( ! Cache::is_fresh() && Settings::credentials_complete() ) {
			$this->refresh( false );
		}
		return Cache::get_sessions();
	}

	/**
	 * Current session from Bricks loop context.
	 *
	 * @return array<string,mixed>|null
	 */
	public function current_session() {
		if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object' ) ) {
			$obj = \Bricks\Query::get_loop_object();
			if ( is_array( $obj ) && isset( $obj['id'] ) && ( isset( $obj['title'] ) || isset( $obj['start_time'] ) ) ) {
				return $obj;
			}
		}
		return null;
	}
}
