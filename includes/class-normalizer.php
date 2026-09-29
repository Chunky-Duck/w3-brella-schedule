<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts Brella JSON:API timeslots into flat session arrays for Bricks.
 */
class Normalizer {

	/**
	 * @param array<int,array<string,mixed>> $data     Timeslot resources.
	 * @param array<int,array<string,mixed>> $included Included resources.
	 * @param string                           $timezone Optional IANA timezone.
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_timeslots( array $data, array $included, $timezone = '' ) {
		$index = self::index_included( $included );
		$sessions = array();

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || ( $item['type'] ?? '' ) !== 'timeslot' ) {
				continue;
			}

			$session = self::normalize_timeslot( $item, $index, $timezone );
			if ( null === $session ) {
				continue;
			}

			if ( Settings::exclude_networking() && self::is_networking_slot( $item ) ) {
				continue;
			}

			$sessions[] = $session;
		}

		usort(
			$sessions,
			function ( $a, $b ) {
				return strcmp( (string) ( $a['start_time'] ?? '' ), (string) ( $b['start_time'] ?? '' ) );
			}
		);

		return $sessions;
	}

	/**
	 * @param array<string,mixed>              $item Timeslot resource.
	 * @param array<string,array<string,mixed>> $index Included index.
	 * @param string                           $timezone Timezone.
	 * @return array<string,mixed>|null
	 */
	private static function normalize_timeslot( array $item, array $index, $timezone ) {
		$id     = (string) ( $item['id'] ?? '' );
		$attrs  = isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
		$rels   = isset( $item['relationships'] ) && is_array( $item['relationships'] ) ? $item['relationships'] : array();

		$title    = self::scalar( $attrs['title'] ?? '' );
		$subtitle = self::scalar( $attrs['subtitle'] ?? '' );
		$start    = self::scalar( $attrs['start-time'] ?? '' );
		$end      = self::scalar( $attrs['end-time'] ?? '' );
		$duration = (int) self::scalar( $attrs['duration'] ?? 0 );
		$reservable = self::is_true( $attrs['reservable'] ?? false );

		$location = self::scalar( $attrs['location'] ?? '' );
		if ( '' === $location ) {
			$location = self::resolve_tag_names( $rels, 'locations', $index );
			$location = is_array( $location ) ? implode( ', ', $location ) : '';
		}

		$tracks = self::resolve_tag_names( $rels, 'tags', $index );
		$speakers = self::resolve_speakers( $rels, $index );
		$track    = self::resolve_track( $attrs, $rels, $index );

		$local = self::local_times( $start, $end, $timezone );

		return array(
			'id'            => $id,
			'title'         => $title,
			'subtitle'      => $subtitle,
			'start_time'    => $start,
			'end_time'      => $end,
			'start_local'   => $local['start_local'],
			'end_local'     => $local['end_local'],
			'day_key'       => $local['day_key'],
			'day_label'     => $local['day_label'],
			'time_range'    => $local['time_range'],
			'duration_min'  => $duration > 0 ? $duration : self::duration_from_range( $start, $end ),
			'location'      => $location,
			'tracks'        => $tracks,
			'speakers'      => $speakers,
			'speakers_text' => self::speakers_text( $speakers ),
			'stream_link'   => self::scalar( $attrs['stream-link'] ?? '' ),
			'reservable'    => $reservable,
			'content'       => $attrs['content'] ?? array(),
			// Added in 1.1.0 for the Brella Agenda grid. Existing keys above are unchanged.
			'track_id'       => $track['id'],
			'track'          => $track['name'],
			'track_color'    => $track['color'],
			'track_position' => $track['position'],
			'tags_detail'    => self::resolve_tags_detail( $rels, $index ),
			'color'          => self::scalar( $attrs['color'] ?? '' ),
			'cover_image'    => self::scalar( $attrs['cover-image-url'] ?? '' ),
		);
	}

	/**
	 * @param array<string,mixed> $item Timeslot.
	 * @return bool
	 */
	private static function is_networking_slot( array $item ) {
		$attrs = isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
		$title = trim( self::scalar( $attrs['title'] ?? '' ) );
		return '' === $title && self::is_true( $attrs['reservable'] ?? false );
	}

	/**
	 * Brella sends booleans as true/false or as "true"/"false" strings depending on the endpoint.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_true( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), array( 'true', '1', 'yes' ), true );
	}

	/**
	 * @param array<int,array<string,mixed>> $included Included resources.
	 * @return array<string,array<string,mixed>>
	 */
	private static function index_included( array $included ) {
		$index = array();
		foreach ( $included as $resource ) {
			if ( ! is_array( $resource ) || empty( $resource['type'] ) || ! isset( $resource['id'] ) ) {
				continue;
			}
			$key         = $resource['type'] . ':' . $resource['id'];
			$index[ $key ] = $resource;
		}
		return $index;
	}

	/**
	 * @param array<string,mixed>              $rels Relationships.
	 * @param string                           $key  Relationship key.
	 * @param array<string,array<string,mixed>> $index Included index.
	 * @return array<int,string>
	 */
	private static function resolve_tag_names( array $rels, $key, array $index ) {
		$names = array();
		$refs  = self::relationship_refs( $rels, $key );
		foreach ( $refs as $ref ) {
			if ( ( $ref['type'] ?? '' ) !== 'tag' ) {
				continue;
			}
			$resource = $index[ ( $ref['type'] ?? '' ) . ':' . ( $ref['id'] ?? '' ) ] ?? null;
			if ( ! $resource ) {
				continue;
			}
			$name = self::scalar( $resource['attributes']['name'] ?? '' );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * @param array<string,mixed>              $rels Relationships.
	 * @param array<string,array<string,mixed>> $index Included index.
	 * @return array<int,array<string,string>>
	 */
	private static function resolve_speakers( array $rels, array $index ) {
		$assignments = self::relationship_refs( $rels, 'speaker-assignments' );
		$speakers    = array();

		foreach ( $assignments as $assignment_ref ) {
			$assignment = $index[ ( $assignment_ref['type'] ?? '' ) . ':' . ( $assignment_ref['id'] ?? '' ) ] ?? null;
			if ( ! $assignment ) {
				continue;
			}

			$speaker_ref = $assignment['relationships']['speaker']['data'] ?? null;
			if ( ! is_array( $speaker_ref ) ) {
				continue;
			}

			$speaker = $index[ ( $speaker_ref['type'] ?? '' ) . ':' . ( $speaker_ref['id'] ?? '' ) ] ?? null;
			if ( ! $speaker || ! isset( $speaker['attributes'] ) || ! is_array( $speaker['attributes'] ) ) {
				continue;
			}

			$a = $speaker['attributes'];
			$name = trim(
				implode(
					' ',
					array_filter(
						array(
							self::scalar( $a['honorific'] ?? '' ),
							self::scalar( $a['first-name'] ?? '' ),
							self::scalar( $a['middle-name'] ?? '' ),
							self::scalar( $a['last-name'] ?? '' ),
						)
					)
				)
			);

			$role = self::scalar( $assignment['attributes']['role'] ?? '' );
			$position = (int) self::scalar( $assignment['attributes']['position'] ?? 0 );

			$speakers[] = array(
				'id'       => (string) ( $speaker['id'] ?? '' ),
				'name'     => $name,
				'role'     => $role,
				'position' => $position,
				'photo'    => self::scalar( $a['photo-url'] ?? '' ),
				'company'  => self::scalar( $a['company-name'] ?? '' ),
				'job_title'=> self::scalar( $a['job-title'] ?? '' ),
			);
		}

		usort(
			$speakers,
			function ( $a, $b ) {
				return ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 );
			}
		);

		return $speakers;
	}

	/**
	 * Brella track (the agenda column / theatre) for a timeslot, when the API supplies one.
	 *
	 * @param array<string,mixed>               $attrs Attributes.
	 * @param array<string,mixed>               $rels  Relationships.
	 * @param array<string,array<string,mixed>> $index Included index.
	 * @return array{id:string,name:string,color:string,position:int}
	 */
	private static function resolve_track( array $attrs, array $rels, array $index ) {
		$out = array(
			'id'       => '',
			'name'     => '',
			'color'    => '',
			'position' => 0,
		);

		$refs = self::relationship_refs( $rels, 'track' );
		$id   = ! empty( $refs[0]['id'] ) ? (string) $refs[0]['id'] : self::scalar( $attrs['track-id'] ?? '' );
		if ( '' === $id ) {
			return $out;
		}

		$out['id'] = $id;
		$resource  = $index[ 'track:' . $id ] ?? null;
		if ( $resource && isset( $resource['attributes'] ) && is_array( $resource['attributes'] ) ) {
			$a               = $resource['attributes'];
			$out['name']     = self::scalar( $a['name'] ?? '' );
			$out['color']    = self::scalar( $a['color'] ?? '' );
			$out['position'] = (int) self::scalar( $a['position'] ?? 0 );
		}

		return $out;
	}

	/**
	 * Tags with their Brella colours.
	 *
	 * @param array<string,mixed>               $rels  Relationships.
	 * @param array<string,array<string,mixed>> $index Included index.
	 * @return array<int,array{id:string,name:string,color:string}>
	 */
	private static function resolve_tags_detail( array $rels, array $index ) {
		$tags = array();
		foreach ( self::relationship_refs( $rels, 'tags' ) as $ref ) {
			$resource = $index[ 'tag:' . ( $ref['id'] ?? '' ) ] ?? null;
			if ( ! $resource ) {
				continue;
			}
			$name = self::scalar( $resource['attributes']['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$tags[] = array(
				'id'    => (string) $resource['id'],
				'name'  => $name,
				'color' => self::scalar( $resource['attributes']['color'] ?? '' ),
			);
		}
		return $tags;
	}

	/**
	 * @param array<int,array<string,string>> $speakers Speakers list.
	 * @return string
	 */
	private static function speakers_text( array $speakers ) {
		$names = array();
		foreach ( $speakers as $speaker ) {
			if ( ! empty( $speaker['name'] ) ) {
				$names[] = $speaker['name'];
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * @param array<string,mixed> $rels Relationships.
	 * @param string              $key  Key.
	 * @return array<int,array<string,string>>
	 */
	private static function relationship_refs( array $rels, $key ) {
		if ( empty( $rels[ $key ]['data'] ) || ! is_array( $rels[ $key ]['data'] ) ) {
			return array();
		}

		$data = $rels[ $key ]['data'];
		if ( isset( $data['type'] ) ) {
			return array( $data );
		}

		return $data;
	}

	/**
	 * @param mixed $value Attribute value.
	 * @return string
	 */
	private static function scalar( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return trim( (string) $value );
	}

	/**
	 * @param string $start ISO start.
	 * @param string $end   ISO end.
	 * @param string $timezone IANA timezone.
	 * @return array{start_local:string,end_local:string,day_key:string,day_label:string,time_range:string}
	 */
	private static function local_times( $start, $end, $timezone ) {
		$tz = $timezone ? $timezone : wp_timezone_string();
		try {
			$tz_obj = new \DateTimeZone( $tz );
		} catch ( \Exception $e ) {
			$tz_obj = wp_timezone();
		}

		$start_dt = self::parse_iso( $start, $tz_obj );
		$end_dt   = self::parse_iso( $end, $tz_obj );

		if ( ! $start_dt ) {
			return array(
				'start_local' => '',
				'end_local'   => '',
				'day_key'     => '',
				'day_label'   => '',
				'time_range'  => '',
			);
		}

		$day_key   = $start_dt->format( 'Y-m-d' );
		$day_label = wp_date( 'l j M', $start_dt->getTimestamp(), $tz_obj );
		$start_local = wp_date( 'Y-m-d H:i', $start_dt->getTimestamp(), $tz_obj );
		$end_local   = $end_dt ? wp_date( 'Y-m-d H:i', $end_dt->getTimestamp(), $tz_obj ) : '';

		$time_range = wp_date( 'g:i A', $start_dt->getTimestamp(), $tz_obj );
		if ( $end_dt ) {
			$time_range .= ' – ' . wp_date( 'g:i A', $end_dt->getTimestamp(), $tz_obj );
		}

		return array(
			'start_local' => $start_local,
			'end_local'   => $end_local,
			'day_key'     => $day_key,
			'day_label'   => $day_label,
			'time_range'  => $time_range,
		);
	}

	/**
	 * @param string           $iso ISO8601.
	 * @param \DateTimeZone    $tz  Timezone.
	 * @return \DateTimeImmutable|null
	 */
	private static function parse_iso( $iso, \DateTimeZone $tz ) {
		if ( '' === trim( $iso ) ) {
			return null;
		}
		try {
			$dt = new \DateTimeImmutable( $iso );
			return $dt->setTimezone( $tz );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * @param string $start ISO start.
	 * @param string $end   ISO end.
	 * @return int
	 */
	private static function duration_from_range( $start, $end ) {
		$start_dt = self::parse_iso( $start, wp_timezone() );
		$end_dt   = self::parse_iso( $end, wp_timezone() );
		if ( ! $start_dt || ! $end_dt ) {
			return 0;
		}
		return max( 0, (int) round( ( $end_dt->getTimestamp() - $start_dt->getTimestamp() ) / 60 ) );
	}
}
