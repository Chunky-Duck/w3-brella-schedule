<?php
namespace CC\Brella;

use WP_Error;

/**
 * Turns the sessions cached by this plugin (Brella Integration API, synced
 * server side) into the shape the agenda grid renders: timezone, dates,
 * tracks keyed by id, and sessions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agenda_Source {

	/**
	 * Colours handed to columns that have none of their own (e.g. locations).
	 */
	const PALETTE = [ 'magenta', 'blue', 'green', 'cyan', 'yellow', 'orange', 'purple', 'red', 'teal', 'pink' ];

	/**
	 * @param array $o Parsed renderer options.
	 * @return array|WP_Error
	 */
	public static function get_schedule( array $o ) {
		if ( ! Settings::credentials_complete() ) {
			return new WP_Error( 'brella_not_configured', 'Add your Brella API key, Organization ID and Event ID first.' );
		}

		$sync = Schedule_Sync::instance();

		// Editors can force a fresh sync with ?brella_refresh=1.
		if ( isset( $_GET['brella_refresh'] ) && current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$sync->refresh( true );
		}

		$rows    = $sync->get_sessions_for_display();
		$payload = Cache::get_payload();

		if ( ! $rows ) {
			return new WP_Error( 'brella_empty', 'No sessions cached yet. Click Refresh now on the W3 Brella Integration settings page.' );
		}

		$timezone = '';
		if ( is_array( $payload ) && ! empty( $payload['meta']['timezone'] ) ) {
			$timezone = (string) $payload['meta']['timezone'];
		} else {
			$timezone = (string) Settings::get( 'timezone', '' );
		}

		return self::convert_rows(
			$rows,
			$o['group_by'],
			$timezone,
			is_array( $payload ) ? (int) ( $payload['fetched_at'] ?? time() ) : time()
		);
	}

	/**
	 * Public so it can be unit tested without WordPress.
	 *
	 * @param array  $rows     W3 normalised sessions.
	 * @param string $group_by auto | track | location | tag.
	 * @param string $timezone IANA timezone.
	 * @param int    $fetched  Unix time of the sync.
	 */
	public static function convert_rows( array $rows, $group_by, $timezone, $fetched ) {
		$tracks   = [];
		$sessions = [];
		$dates    = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$start = strtotime( (string) ( $row['start_time'] ?? '' ) );
			if ( ! $start ) {
				continue;
			}
			$end = strtotime( (string) ( $row['end_time'] ?? '' ) );
			if ( ! $end || $end <= $start ) {
				$end = $start + 60 * max( 5, (int) ( $row['duration_min'] ?? 0 ) );
			}

			// Tags: full detail from W3 1.1.0+, plain names from older versions.
			$tags = [];
			if ( ! empty( $row['tags_detail'] ) && is_array( $row['tags_detail'] ) ) {
				foreach ( $row['tags_detail'] as $t ) {
					$tags[] = [ 'id' => (string) ( $t['id'] ?? '' ), 'name' => (string) ( $t['name'] ?? '' ), 'color' => (string) ( $t['color'] ?? '' ) ];
				}
			} elseif ( ! empty( $row['tracks'] ) && is_array( $row['tracks'] ) ) {
				foreach ( $row['tracks'] as $name ) {
					$tags[] = [ 'id' => '', 'name' => (string) $name, 'color' => '' ];
				}
			}

			$column = self::column_for( $row, $tags, $group_by );
			if ( ! isset( $tracks[ $column['id'] ] ) ) {
				$tracks[ $column['id'] ] = $column;
			}

			$speakers = [];
			foreach ( (array) ( $row['speakers'] ?? [] ) as $sp ) {
				if ( ! is_array( $sp ) || empty( $sp['name'] ) ) {
					continue;
				}
				$speakers[] = [
					'id'       => (string) ( $sp['id'] ?? '' ),
					'name'     => (string) $sp['name'],
					'title'    => (string) ( $sp['job_title'] ?? '' ),
					'company'  => (string) ( $sp['company'] ?? '' ),
					'photo'    => (string) ( $sp['photo'] ?? '' ),
					'role'     => (string) ( $sp['role'] ?? '' ),
					'position' => (int) ( $sp['position'] ?? 0 ),
				];
			}

			if ( ! empty( $row['day_key'] ) ) {
				$dates[ $row['day_key'] ] = true;
			}

			$sessions[] = [
				'id'         => (string) ( $row['id'] ?? md5( $start . ( $row['title'] ?? '' ) ) ),
				'track'      => $column['id'],
				'title'      => (string) ( $row['title'] ?? '' ),
				'subtitle'   => (string) ( $row['subtitle'] ?? '' ),
				'start'      => $start,
				'end'        => $end,
				'location'   => (string) ( $row['location'] ?? '' ),
				'color'      => (string) ( $row['color'] ?? '' ),
				'reservable' => ! empty( $row['reservable'] ),
				'cover'      => (string) ( $row['cover_image'] ?? '' ),
				'content'    => is_array( $row['content'] ?? null ) ? $row['content'] : null,
				'speakers'   => $speakers,
				'tags'       => $tags,
			];
		}

		// Columns without a Brella position are ordered by name ("Theatre 1, 2, 10").
		$named = array_filter( $tracks, function ( $t ) {
			return ! $t['has_position'];
		} );
		if ( $named ) {
			$names = array_map( function ( $t ) {
				return $t['name'];
			}, $named );
			natcasesort( $names );
			$i = 1000;
			foreach ( array_keys( $names ) as $id ) {
				$tracks[ $id ]['position'] = $i++;
			}
		}

		// Give colourless columns a colour from the palette, in column order.
		uasort( $tracks, function ( $a, $b ) {
			return [ $a['position'], $a['name'] ] <=> [ $b['position'], $b['name'] ];
		} );
		$n = 0;
		foreach ( $tracks as $id => $t ) {
			if ( '' === $t['color'] ) {
				$tracks[ $id ]['color'] = self::PALETTE[ $n % count( self::PALETTE ) ];
			}
			unset( $tracks[ $id ]['has_position'] );
			$n++;
		}

		$dates = array_keys( $dates );
		sort( $dates );

		return [
			'timezone' => (string) $timezone,
			'dates'    => $dates,
			'tracks'   => $tracks,
			'sessions' => $sessions,
			'fetched'  => (int) $fetched,
		];
	}

	/**
	 * Which agenda column a session belongs to.
	 *
	 * auto:     Brella track when the API supplies one, otherwise location, otherwise first tag.
	 * track:    Brella track only (sessions without one go under "Sessions").
	 * location: the session's location / room.
	 * tag:      the session's first tag.
	 */
	private static function column_for( array $row, array $tags, $group_by ) {
		$track_id = (string) ( $row['track_id'] ?? '' );
		$track    = (string) ( $row['track'] ?? '' );
		$location = trim( (string) ( $row['location'] ?? '' ) );
		$tag      = $tags ? trim( $tags[0]['name'] ) : '';

		$use_track = '' !== $track_id && '' !== $track;

		if ( 'track' === $group_by || ( 'auto' === $group_by && $use_track ) ) {
			if ( $use_track ) {
				return [
					'id'           => 'k' . preg_replace( '/[^A-Za-z0-9]/', '', $track_id ),
					'name'         => $track,
					'position'     => (int) ( $row['track_position'] ?? 0 ),
					'has_position' => ! empty( $row['track_position'] ),
					'color'        => (string) ( $row['track_color'] ?? '' ),
					'type'         => 'ContentTrack',
					'description'  => '',
				];
			}
			return self::named_column( 'Sessions' );
		}

		if ( 'tag' === $group_by ) {
			return self::named_column( '' !== $tag ? $tag : 'Sessions' );
		}

		// location, or auto without a track.
		if ( '' !== $location ) {
			return self::named_column( $location );
		}
		return self::named_column( '' !== $tag ? $tag : 'Sessions' );
	}

	private static function named_column( $name ) {
		return [
			'id'           => 'n' . substr( md5( strtolower( $name ) ), 0, 10 ),
			'name'         => $name,
			'position'     => 0,
			'has_position' => false,
			'color'        => '',
			'type'         => 'ContentTrack',
			'description'  => '',
		];
	}
}
