<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule cache in wp_options + refresh lock transient.
 */
class Cache {

	/**
	 * @return array<string,mixed>|null Full schedule payload.
	 */
	public static function get_payload() {
		$payload = get_option( CC_BRELLA_SCHEDULE_OPTION, null );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_sessions() {
		$payload = self::get_payload();
		if ( ! $payload || empty( $payload['sessions'] ) || ! is_array( $payload['sessions'] ) ) {
			return array();
		}
		return $payload['sessions'];
	}

	/**
	 * @return bool
	 */
	public static function is_fresh() {
		$payload = self::get_payload();
		if ( ! $payload || empty( $payload['expires_at'] ) ) {
			return false;
		}
		return (int) $payload['expires_at'] > time();
	}

	/**
	 * @param array<int,array<string,mixed>> $sessions Normalized sessions.
	 * @param array<string,mixed>              $meta     Sync metadata.
	 */
	public static function save( array $sessions, array $meta = array() ) {
		$ttl = Settings::cache_ttl_seconds();
		$now = time();

		$payload = array(
			'fetched_at'  => $now,
			'expires_at'  => $now + $ttl,
			'sessions'    => array_values( $sessions ),
			'meta'        => array_merge(
				array(
					'count' => count( $sessions ),
				),
				$meta
			),
		);

		update_option( CC_BRELLA_SCHEDULE_OPTION, $payload, false );
	}

	/**
	 * @return bool True if lock acquired.
	 */
	public static function acquire_refresh_lock() {
		if ( get_transient( CC_BRELLA_REFRESH_LOCK ) ) {
			return false;
		}
		set_transient( CC_BRELLA_REFRESH_LOCK, 1, 60 );
		return true;
	}

	public static function release_refresh_lock() {
		delete_transient( CC_BRELLA_REFRESH_LOCK );
	}

	/**
	 * @return string
	 */
	public static function last_sync_human() {
		$payload = self::get_payload();
		if ( ! $payload || empty( $payload['fetched_at'] ) ) {
			return '';
		}
		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $payload['fetched_at']
		);
	}

	/**
	 * @return string
	 */
	public static function expires_human() {
		$payload = self::get_payload();
		if ( ! $payload || empty( $payload['expires_at'] ) ) {
			return '';
		}
		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $payload['expires_at']
		);
	}
}
