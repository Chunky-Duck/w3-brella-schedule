<?php
namespace CC\Brella;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Renders the Brella schedule as a CSS grid.
 *
 * X axis: tracks (one column per track, split into lanes when sessions in the
 * same track overlap). Y axis: start time, one grid row per "step" minutes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agenda_Renderer {

	/**
	 * Default options. Shared by the shortcode and the Bricks element.
	 */
	public static function defaults() {
		return [
			'group_by'           => 'auto',
			'step'               => 5,
			'label_interval'     => 30,
			'time_format'        => 'g:i a',
			'day_format'         => 'D j M',
			'tracks'             => '',
			'include_networking' => 'false',
			'hide_empty_tracks'  => 'true',
			'show_speakers'      => 'true',
			'show_avatars'       => 'true',
			'max_avatars'        => 3,
			'show_location'      => 'true',
			'show_subtitle'      => 'true',
			'show_excerpt'       => 'false',
			'show_timezone'      => 'true',
			'details'            => 'modal',
			'mobile'             => 'list',
			'breakpoint'         => 768,
			'theme'              => 'dark',
			'heading_tag'        => 'h3',
			'show_filters'       => 'true',
			'filters'            => 'track,speaker,tag,type',
			'track_label'        => 'Theatre',
			'hscroll'            => 'true',
			'breakout'           => 'false',
			'breakout_min'       => 991,
			'height'             => '',
			'freeze'             => 'both',
			'view_toggle'        => 'true',
			'show_track_sponsors' => 'true',
			'track_widths'       => '',
			'track_settings'     => [],
			'sponsor_position'   => 'above',
			'track_fixed'        => 'false',
			'default_view'       => 'calendar',
			'freeze_offset'      => '',
		];
	}

	/**
	 * Shortcode output, wrapped in its own root element.
	 */
	public static function render_wrapped( array $atts ) {
		$o     = self::parse( $atts );
		$attrs = '';
		foreach ( self::root_attributes( $o ) as $k => $v ) {
			$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		if ( $o['height'] ) {
			$attrs .= ' style="' . esc_attr( '--ba-h:' . $o['height'] ) . '"';
		}
		return sprintf(
			'<div class="%s"%s>%s</div>',
			esc_attr( self::root_classes( $o ) ),
			$attrs,
			self::render( $o )
		);
	}

	/**
	 * Data attributes the front-end script reads from the root element.
	 */
	public static function root_attributes( array $o ) {
		$attrs = [
			'data-breakpoint'   => (string) $o['breakpoint'],
			'data-mobile'       => $o['mobile'],
			'data-default-view' => $o['default_view'],
		];
		if ( $o['breakout'] ) {
			$attrs['data-breakout']     = '1';
			$attrs['data-breakout-min'] = (string) $o['breakout_min'];
		}
		if ( '' !== $o['freeze_offset'] ) {
			$attrs['data-freeze-offset'] = (string) $o['freeze_offset'];
		}
		return $attrs;
	}

	/**
	 * Classes for the root element.
	 */
	public static function root_classes( array $o ) {
		$classes = [ 'brella-agenda' ];
		if ( in_array( $o['theme'], [ 'dark', 'light' ], true ) ) {
			$classes[] = 'ba-theme-' . $o['theme'];
		}
		if ( isset( $o['hscroll'] ) && ! $o['hscroll'] ) {
			$classes[] = 'ba-no-hscroll';
		}
		if ( 'beside' === ( $o['sponsor_position'] ?? 'above' ) ) {
			$classes[] = 'ba-sponsor-beside';
		}
		$freeze = $o['freeze'] ?? 'both';
		if ( ! in_array( $freeze, [ 'both', 'time' ], true ) ) {
			$classes[] = 'ba-unfreeze-time';
		}
		if ( in_array( $freeze, [ 'both', 'headers' ], true ) ) {
			$classes[] = 'ba-freeze-head';
		} else {
			$classes[] = 'ba-unfreeze-head';
		}
		return implode( ' ', $classes );
	}

	/**
	 * Normalise raw options into typed values.
	 */
	public static function parse( array $raw ) {
		$o = array_merge( self::defaults(), array_filter( $raw, function ( $v ) {
			return null !== $v && '' !== $v;
		} ) );

		foreach ( [ 'include_networking', 'hide_empty_tracks', 'show_speakers', 'show_avatars', 'show_location', 'show_subtitle', 'show_excerpt', 'show_timezone', 'show_filters', 'hscroll', 'breakout', 'view_toggle', 'show_track_sponsors', 'track_fixed' ] as $k ) {
			$o[ $k ] = is_bool( $o[ $k ] ) ? $o[ $k ] : filter_var( $o[ $k ], FILTER_VALIDATE_BOOLEAN );
		}

		$o['step']           = max( 1, min( 60, (int) $o['step'] ) );
		$o['label_interval'] = max( $o['step'], (int) round( max( 1, (int) $o['label_interval'] ) / $o['step'] ) * $o['step'] );
		$o['breakpoint']     = max( 0, (int) $o['breakpoint'] );
		$o['max_avatars']    = max( 1, min( 12, (int) $o['max_avatars'] ) );
		$o['breakout_min']   = max( 0, (int) $o['breakout_min'] );
		$o['default_view']   = 'list' === $o['default_view'] ? 'list' : 'calendar';
		$o['track_settings'] = self::parse_track_settings( $o['track_settings'], $o['track_widths'] );
		$o['freeze']         = in_array( $o['freeze'], [ 'both', 'time', 'headers', 'none' ], true ) ? $o['freeze'] : 'both';
		$o['freeze_offset']  = is_numeric( $o['freeze_offset'] ) ? (string) max( 0, (int) $o['freeze_offset'] ) : '';
		$o['height']         = preg_match( '/^\d+(\.\d+)?(px|rem|em|vh|dvh|svh|lvh|%)$/', trim( (string) $o['height'] ) ) ? trim( $o['height'] ) : '';
		$o['group_by']       = in_array( $o['group_by'], [ 'auto', 'track', 'location', 'tag' ], true ) ? $o['group_by'] : 'auto';
		$o['details']        = in_array( $o['details'], [ 'modal', 'none' ], true ) ? $o['details'] : 'modal';
		$o['mobile']         = in_array( $o['mobile'], [ 'list', 'scroll' ], true ) ? $o['mobile'] : 'list';
		$o['heading_tag']    = in_array( $o['heading_tag'], [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ], true ) ? $o['heading_tag'] : 'h3';

		$o['filters'] = array_values( array_intersect(
			array_map( 'trim', explode( ',', strtolower( (string) $o['filters'] ) ) ),
			[ 'track', 'speaker', 'tag', 'type' ]
		) );

		return $o;
	}

	/**
	 * Stable filter keys, used both in the dropdowns and on each session card.
	 */
	public static function speaker_key( array $sp ) {
		return 's' . ( ! empty( $sp['id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $sp['id'] ) : substr( md5( strtolower( $sp['name'] ) ), 0, 10 ) );
	}

	public static function tag_key( array $t ) {
		return 't' . substr( md5( strtolower( trim( $t['name'] ) ) ), 0, 10 );
	}

	public static function type_key( $subtitle ) {
		return 'y' . substr( md5( strtolower( trim( (string) $subtitle ) ) ), 0, 10 );
	}

	/**
	 * Filter dropdowns built from every session on every day.
	 */
	private static function filters_html( array $sessions, array $tracks, array $o, $uid ) {
		if ( ! $o['show_filters'] || ! $o['filters'] ) {
			return '';
		}

		$opts = [ 'track' => [], 'speaker' => [], 'tag' => [], 'type' => [] ];
		foreach ( $sessions as $s ) {
			$opts['track'][ $s['track'] ] = $tracks[ $s['track'] ]['name'];
			foreach ( $s['speakers'] as $sp ) {
				$opts['speaker'][ self::speaker_key( $sp ) ] = $sp['name'];
			}
			foreach ( $s['tags'] as $t ) {
				$opts['tag'][ self::tag_key( $t ) ] = $t['name'];
			}
			if ( '' !== trim( $s['subtitle'] ) ) {
				$opts['type'][ self::type_key( $s['subtitle'] ) ] = trim( $s['subtitle'] );
			}
		}

		// Tracks keep the event's own order; everything else is alphabetical.
		$ordered = [];
		foreach ( $tracks as $id => $t ) {
			if ( isset( $opts['track'][ $id ] ) ) {
				$ordered[ $id ] = $t['name'];
			}
		}
		$opts['track'] = $ordered;
		foreach ( [ 'speaker', 'tag', 'type' ] as $k ) {
			natcasesort( $opts[ $k ] );
		}

		$label_plural = [
			'track'   => self::plural( $o['track_label'] ),
			'speaker' => 'speakers',
			'tag'     => 'tags',
			'type'    => 'session types',
		];
		$label_single = [
			'track'   => $o['track_label'],
			'speaker' => 'Speaker',
			'tag'     => 'Tag',
			'type'    => 'Session type',
		];

		$out = '';
		foreach ( $o['filters'] as $k ) {
			if ( ! $opts[ $k ] ) {
				continue; // Nothing to filter by, e.g. no tags set up in Brella.
			}
			$id   = $uid . '-filter-' . $k;
			$out .= '<div class="ba-filter" data-filter-wrap="' . esc_attr( $k ) . '">';
			$out .= '<label class="ba-visually-hidden" for="' . esc_attr( $id ) . '">' . esc_html( $label_single[ $k ] ) . '</label>';
			$out .= '<select class="ba-filter__select" id="' . esc_attr( $id ) . '" data-filter="' . esc_attr( $k ) . '">';
			$out .= '<option value="">' . esc_html( 'All ' . strtolower( $label_plural[ $k ] ) ) . '</option>';
			foreach ( $opts[ $k ] as $value => $label ) {
				$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			$out .= '</select></div>';
		}

		if ( '' === $out ) {
			return '';
		}

		return '<div class="ba-filters" role="group" aria-label="Filter the agenda">' . $out
			. '<button type="button" class="ba-filters__clear" hidden>Clear</button></div>';
	}

	/**
	 * Per-track settings: width, fixed width, sponsor logo override, sponsor
	 * link and hiding the logo. From the Bricks "Tracks" repeater, and/or the
	 * shortcode's track_widths="Main Stage:20rem|Hall A:300px!" (! = fixed).
	 *
	 * @param mixed  $rows   Repeater rows.
	 * @param string $widths Shortcode widths.
	 * @return array<int,array<string,mixed>>
	 */
	private static function parse_track_settings( $rows, $widths ) {
		$out = [];

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! is_array( $row ) || '' === trim( (string) ( $row['track'] ?? '' ) ) ) {
				continue;
			}
			$out[] = [
				'track'     => trim( (string) $row['track'] ),
				'width'     => self::css_length( $row['width'] ?? '' ),
				'fixed'     => ! empty( $row['fixed'] ),
				'logo'      => (string) ( $row['logo'] ?? '' ),
				'link'      => (string) ( $row['link'] ?? '' ),
				'hide_logo' => ! empty( $row['hide_logo'] ),
			];
		}

		foreach ( array_filter( array_map( 'trim', explode( '|', (string) $widths ) ) ) as $pair ) {
			$parts = array_map( 'trim', explode( ':', $pair, 2 ) );
			if ( 2 !== count( $parts ) || '' === $parts[0] ) {
				continue;
			}
			$fixed = '!' === substr( $parts[1], -1 );
			$out[] = [
				'track'     => $parts[0],
				'width'     => self::css_length( rtrim( $parts[1], '!' ) ),
				'fixed'     => $fixed,
				'logo'      => '',
				'link'      => '',
				'hide_logo' => false,
			];
		}

		return $out;
	}

	/**
	 * Accept only plain CSS lengths such as 18rem, 300px or 25%.
	 */
	private static function css_length( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
			$value .= 'px';
		}
		return preg_match( '/^\d+(\.\d+)?(px|rem|em|%|vw|ch)$/', $value ) ? $value : '';
	}

	/**
	 * The settings row for a column, matched by track name or Brella track id.
	 */
	private static function track_setting( $tid, array $track, array $o ) {
		$found = null;
		foreach ( $o['track_settings'] as $row ) {
			$want = strtolower( $row['track'] );
			if ( strtolower( trim( $track['name'] ) ) === $want
				|| ( ! empty( $track['brella_id'] ) && strtolower( (string) $track['brella_id'] ) === $want )
				|| strtolower( (string) $tid ) === $want ) {
				// Later rows fill in anything earlier rows left blank.
				$found = $found ? array_merge( $found, array_filter( $row ) ) : $row;
			}
		}
		return $found;
	}

	/**
	 * Sponsor logo(s) for a track header.
	 */
	private static function track_sponsor_html( array $track, $setting, array $o ) {
		if ( ! $o['show_track_sponsors'] || ( $setting && $setting['hide_logo'] ) ) {
			return '';
		}

		$logos = [];
		if ( $setting && '' !== $setting['logo'] ) {
			$logos[] = [
				'logo' => $setting['logo'],
				'name' => '',
				'link' => $setting['link'],
			];
		} else {
			foreach ( (array) ( $track['sponsors'] ?? [] ) as $sp ) {
				if ( ! empty( $sp['logo'] ) ) {
					$logos[] = [
						'logo' => $sp['logo'],
						'name' => (string) ( $sp['name'] ?? '' ),
						'link' => $setting && '' !== $setting['link'] ? $setting['link'] : (string) ( $sp['website'] ?? '' ),
					];
				}
			}
		}

		if ( ! $logos ) {
			return '';
		}

		$html = '<span class="ba-track-sponsor">';
		foreach ( array_slice( $logos, 0, 3 ) as $l ) {
			$alt = '' !== $l['name'] ? 'Sponsored by ' . $l['name'] : 'Track sponsor';
			$img = '<img src="' . esc_url( $l['logo'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
			$html .= '' !== $l['link']
				? '<a class="ba-track-sponsor__logo" href="' . esc_url( $l['link'] ) . '" target="_blank" rel="noopener sponsored">' . $img . '</a>'
				: '<span class="ba-track-sponsor__logo">' . $img . '</span>';
		}
		return $html . '</span>';
	}

	/**
	 * Calendar / List switch for visitors.
	 */
	private static function view_toggle_html( array $o ) {
		$cal_icon  = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M1 2h4v5H1zm5 0h4v8H6zm5 0h4v4h-4zM1 8h4v6H1zm5 3h4v3H6zm5-4h4v7h-4z"/></svg>';
		$list_icon = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M1 2h14v3H1zm0 4.5h14v3H1zM1 11h14v3H1z"/></svg>';
		$is_list   = 'list' === $o['default_view'];

		return '<div class="ba-view" role="group" aria-label="View">'
			. '<button type="button" class="ba-view__btn" data-view="calendar" aria-pressed="' . ( $is_list ? 'false' : 'true' ) . '">' . $cal_icon . '<span>Calendar</span></button>'
			. '<button type="button" class="ba-view__btn" data-view="list" aria-pressed="' . ( $is_list ? 'true' : 'false' ) . '">' . $list_icon . '<span>List</span></button>'
			. '</div>';
	}

	private static function plural( $word ) {
		$word = trim( (string) $word );
		if ( preg_match( '/(s|x|ch|sh)$/i', $word ) ) {
			return $word . 'es';
		}
		if ( preg_match( '/[^aeiou]y$/i', $word ) ) {
			return substr( $word, 0, -1 ) . 'ies';
		}
		return $word . 's';
	}

	/**
	 * Inner markup (tabs, day grids, dialog).
	 *
	 * @param array      $o        Parsed options.
	 * @param array|null $schedule Pre-fetched schedule (used for testing).
	 */
	public static function render( array $o, $schedule = null ) {
		if ( null === $schedule ) {
			$schedule = Agenda_Source::get_schedule( $o );
		}

		if ( is_wp_error( $schedule ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return '';
			}
			$link = current_user_can( 'manage_options' )
				? ' <a href="' . esc_url( Agenda::settings_url() ) . '">Open W3 Brella Integration settings</a>'
				: '';
			return '<p class="ba-notice">Brella Agenda: ' . esc_html( $schedule->get_error_message() ) . $link . '</p>';
		}

		try {
			$tz = new DateTimeZone( $schedule['timezone'] ?: wp_timezone_string() );
		} catch ( Exception $e ) {
			$tz = wp_timezone();
		}

		$tracks   = self::select_tracks( $schedule['tracks'], $o['tracks'] );
		$sessions = array_filter(
			$schedule['sessions'],
			function ( $s ) use ( $tracks, $o ) {
				if ( ! isset( $tracks[ $s['track'] ] ) ) {
					return false;
				}
				if ( $s['reservable'] && ! $o['include_networking'] ) {
					return false;
				}
				return true;
			}
		);

		if ( ! $sessions ) {
			return '<p class="ba-notice">The agenda will be published soon.</p>';
		}

		usort(
			$sessions,
			function ( $a, $b ) {
				return [ $a['start'], $b['end'] ] <=> [ $b['start'], $a['end'] ];
			}
		);

		// Group by local calendar date.
		$days = [];
		foreach ( $sessions as $s ) {
			$date            = ( new DateTime( '@' . $s['start'] ) )->setTimezone( $tz )->format( 'Y-m-d' );
			$days[ $date ][] = $s;
		}
		ksort( $days );

		$uid    = 'ba-' . substr( md5( wp_json_encode( $o ) ), 0, 8 );
		$today  = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d' );
		$active = isset( $days[ $today ] ) ? $today : array_key_first( $days );

		$html = '<div class="ba-bar">';

		// Day tabs (left of the bar).
		if ( count( $days ) > 1 ) {
			$html .= '<div class="ba-tabs" role="tablist" aria-label="Event days">';
			foreach ( array_keys( $days ) as $date ) {
				$label = wp_date( $o['day_format'], ( new DateTime( $date . ' 12:00', $tz ) )->getTimestamp(), $tz );
				$html .= sprintf(
					'<button type="button" class="ba-tab" role="tab" id="%1$s-tab-%2$s" aria-controls="%1$s-day-%2$s" aria-selected="%3$s" tabindex="%4$s">%5$s</button>',
					esc_attr( $uid ),
					esc_attr( $date ),
					$date === $active ? 'true' : 'false',
					$date === $active ? '0' : '-1',
					esc_html( $label )
				);
			}
			$html .= '</div>';
		} else {
			$html .= '<div class="ba-tabs ba-tabs--single" aria-hidden="true"></div>';
		}

		// Filters and the calendar/list switch (right of the bar).
		$html .= self::filters_html( $sessions, $tracks, $o, $uid );
		if ( $o['view_toggle'] ) {
			$html .= self::view_toggle_html( $o );
		}
		$html .= '</div>';
		$html .= '<p class="ba-filter-status" aria-live="polite"></p>';

		foreach ( $days as $date => $day_sessions ) {
			$html .= self::render_day( $date, $day_sessions, $tracks, $tz, $o, $uid, $date === $active, count( $days ) > 1 );
		}

		if ( 'modal' === $o['details'] ) {
			$html .= '<dialog class="ba-dialog" aria-labelledby="' . esc_attr( $uid ) . '-dialog-title">'
				. '<button type="button" class="ba-dialog__close" aria-label="Close">&times;</button>'
				. '<div class="ba-dialog__body" id="' . esc_attr( $uid ) . '-dialog-body"></div>'
				. '</dialog>';
		}

		return $html;
	}

	/**
	 * Pick and order tracks. $filter is a pipe separated list of names or IDs.
	 */
	private static function select_tracks( array $tracks, $filter ) {
		$filter = array_values( array_filter( array_map( 'trim', explode( '|', (string) $filter ) ) ) );

		if ( $filter ) {
			$picked = [];
			foreach ( $filter as $want ) {
				foreach ( $tracks as $id => $t ) {
					if ( (string) $id === $want || 0 === strcasecmp( $t['name'], $want ) ) {
						$picked[ $id ] = $t;
					}
				}
			}
			return $picked;
		}

		uasort(
			$tracks,
			function ( $a, $b ) {
				return [ $a['position'], $a['name'] ] <=> [ $b['position'], $b['name'] ];
			}
		);
		return $tracks;
	}

	/**
	 * One day's grid.
	 */
	private static function render_day( $date, array $sessions, array $tracks, DateTimeZone $tz, array $o, $uid, $active, $tabbed ) {
		$midnight = ( new DateTime( $date . ' 00:00', $tz ) )->getTimestamp();
		$step     = $o['step'];
		$label    = $o['label_interval'];

		$to_min = function ( $ts ) use ( $midnight ) {
			return (int) round( ( $ts - $midnight ) / 60 );
		};

		// Vertical range, snapped to the label interval.
		$first = min( array_map( function ( $s ) use ( $to_min ) {
			return $to_min( $s['start'] );
		}, $sessions ) );
		$last  = max( array_map( function ( $s ) use ( $to_min ) {
			return $to_min( $s['end'] );
		}, $sessions ) );
		$last  = min( $last, 24 * 60 );
		$start = (int) floor( $first / $label ) * $label;
		$end   = (int) ceil( $last / $label ) * $label;
		$rows  = max( 1, (int) ( ( $end - $start ) / $step ) );

		// Which tracks appear today.
		$by_track = [];
		foreach ( $sessions as $s ) {
			$by_track[ $s['track'] ][] = $s;
		}
		$day_tracks = $o['hide_empty_tracks']
			? array_intersect_key( $tracks, $by_track )
			: $tracks;

		// Lane layout per track, so overlapping sessions sit side by side.
		$layout = []; // session id => [lane, span].
		$lanes  = []; // track id => lane count.
		foreach ( $day_tracks as $tid => $t ) {
			$list        = $by_track[ $tid ] ?? [];
			$lane_ends   = [];
			$lane_items  = [];
			foreach ( $list as $s ) {
				$placed = false;
				foreach ( $lane_ends as $i => $lane_end ) {
					if ( $s['start'] >= $lane_end ) {
						$lane_ends[ $i ]    = $s['end'];
						$lane_items[ $i ][] = $s;
						$layout[ $s['id'] ] = [ $i, 1 ];
						$placed             = true;
						break;
					}
				}
				if ( ! $placed ) {
					$i                  = count( $lane_ends );
					$lane_ends[ $i ]    = $s['end'];
					$lane_items[ $i ]   = [ $s ];
					$layout[ $s['id'] ] = [ $i, 1 ];
				}
			}
			$count          = max( 1, count( $lane_ends ) );
			$lanes[ $tid ]  = $count;

			// Stretch each session across free lanes to its right.
			foreach ( $list as $s ) {
				list( $lane ) = $layout[ $s['id'] ];
				$span         = 1;
				for ( $next = $lane + 1; $next < $count; $next++ ) {
					foreach ( $lane_items[ $next ] ?? [] as $other ) {
						if ( $other['start'] < $s['end'] && $other['end'] > $s['start'] ) {
							break 2;
						}
					}
					$span++;
				}
				$layout[ $s['id'] ] = [ $lane, $span ];
			}
		}

		// Grid columns: time gutter, then every lane of every track.
		$cols      = [ 'var(--ba-time-w)' ];
		$col_track = [ '' ];
		$col_start = [];
		$col       = 2;
		foreach ( $day_tracks as $tid => $t ) {
			$col_start[ $tid ] = $col;
			$n                 = $lanes[ $tid ];
			$fr                = rtrim( rtrim( number_format( 1 / $n, 4, '.', '' ), '0' ), '.' );
			$setting           = self::track_setting( $tid, $t, $o );
			$width             = $setting ? $setting['width'] : '';
			$min               = '' !== $width ? $width : 'var(--ba-track-min)';

			// Fixed: this track's own width with Fixed ticked, or the global
			// "Fixed width (all tracks)" for tracks without their own width.
			// The global option is ignored without horizontal scroll, where the
			// shared width is zero and columns share the container instead.
			$fixed = '' !== $width
				? ! empty( $setting['fixed'] )
				: ( $o['track_fixed'] && $o['hscroll'] );

			for ( $i = 0; $i < $n; $i++ ) {
				$lane_min = 1 === $n ? $min : "calc({$min} / {$n})";
				if ( $fixed ) {
					$cols[] = $lane_min; // Fixed: never stretches.
				} else {
					$cols[] = 1 === $n ? "minmax({$min}, 1fr)" : "minmax({$lane_min}, {$fr}fr)";
				}
				$col_track[] = (string) $tid;
			}
			$col += $n;
		}

		$grid_style = sprintf(
			'grid-template-columns:%s;grid-template-rows:auto repeat(%d, var(--ba-slot-h));',
			implode( ' ', $cols ),
			$rows
		);

		$day_label = wp_date( $o['day_format'], $midnight + 43200, $tz );

		$html  = sprintf(
			'<div class="ba-day%5$s" id="%1$s-day-%2$s" data-date="%2$s"%3$s%4$s>',
			esc_attr( $uid ),
			esc_attr( $date ),
			$tabbed ? ' role="tabpanel" aria-labelledby="' . esc_attr( $uid . '-tab-' . $date ) . '"' : '',
			$active ? '' : ' hidden',
			$active ? ' is-active' : ''
		);
		$html .= '<p class="ba-empty" hidden>No sessions on this day match your filters.</p>';
		$html .= '<div class="ba-scroll"><div class="ba-grid" role="list" aria-label="' . esc_attr( 'Agenda for ' . $day_label ) . '" style="' . esc_attr( $grid_style ) . '" data-rows="' . (int) $rows . '" data-cols="' . esc_attr( wp_json_encode( $cols ) ) . '" data-col-tracks="' . esc_attr( wp_json_encode( $col_track ) ) . '">';

		// Corner cell.
		$tz_label = $o['show_timezone'] ? ( new DateTime( '@' . ( $midnight + 43200 ) ) )->setTimezone( $tz )->format( 'T' ) : '';
		$html    .= '<div class="ba-corner" aria-hidden="true">' . esc_html( $tz_label ) . '</div>';

		// Track headers.
		foreach ( $day_tracks as $tid => $t ) {
			$sponsor = self::track_sponsor_html( $t, self::track_setting( $tid, $t, $o ), $o );
			$html   .= sprintf(
				'<div class="ba-track-head%s" style="grid-column:%d / span %d;%s" data-track="%s"><span class="ba-track-head__name">%s</span>%s</div>',
				'' !== $sponsor ? ' has-sponsor' : '',
				$col_start[ $tid ],
				$lanes[ $tid ],
				esc_attr( self::accent_css( $t['color'] ) ),
				esc_attr( $tid ),
				esc_html( $t['name'] ),
				$sponsor
			);
		}

		// Hour lines and time labels.
		$per_label = (int) ( $label / $step );
		for ( $m = $start; $m < $end; $m += $label ) {
			$row   = 2 + (int) ( ( $m - $start ) / $step );
			$major = 0 === $m % 60 ? ' is-hour' : '';
			$ts    = $midnight + $m * 60;
			$html .= sprintf( '<div class="ba-line%s" style="grid-row:%d / span %d" aria-hidden="true"></div>', $major, $row, $per_label );
			$html .= sprintf(
				'<div class="ba-time%s" style="grid-row:%d / span %d" aria-hidden="true"><time datetime="%s">%s</time></div>',
				$major,
				$row,
				$per_label,
				esc_attr( gmdate( 'c', $ts ) ),
				esc_html( wp_date( $o['time_format'], $ts, $tz ) )
			);
		}

		// Sessions, in chronological order so the mobile list reads correctly.
		foreach ( $sessions as $s ) {
			if ( ! isset( $col_start[ $s['track'] ] ) ) {
				continue;
			}
			$r1 = 2 + (int) floor( ( $to_min( $s['start'] ) - $start ) / $step );
			$r2 = 2 + (int) ceil( ( min( $to_min( $s['end'] ), $end ) - $start ) / $step );
			$r2 = max( $r1 + 1, $r2 );

			list( $lane, $span ) = $layout[ $s['id'] ];
			$c                   = $col_start[ $s['track'] ] + $lane;

			$html .= self::render_session( $s, $tracks[ $s['track'] ], $r1, $r2, $c, $span, $tz, $o );
		}

		$html .= '</div></div></div>';
		return $html;
	}

	/**
	 * One session card.
	 */
	private static function render_session( array $s, array $track, $r1, $r2, $col, $span, DateTimeZone $tz, array $o ) {
		$color = $s['color'] ?: $track['color'];
		$rows  = $r2 - $r1;
		$tag   = $o['heading_tag'];

		// "12:05 – 12:30 pm" rather than "12:05 pm – 12:30 pm" when both share am/pm,
		// which leaves room for the speaker avatars on the same line.
		$start_format = $o['time_format'];
		if ( $rows > 2 && preg_match( '/\s*[aA]$/', $start_format ) && wp_date( 'a', $s['start'], $tz ) === wp_date( 'a', $s['end'], $tz ) ) {
			$start_format = preg_replace( '/\s*[aA]$/', '', $start_format );
		}

		$time = sprintf(
			'<time datetime="%s">%s</time> &ndash; <time datetime="%s">%s</time>',
			esc_attr( gmdate( 'c', $s['start'] ) ),
			esc_html( wp_date( $start_format, $s['start'], $tz ) ),
			esc_attr( gmdate( 'c', $s['end'] ) ),
			esc_html( wp_date( $o['time_format'], $s['end'], $tz ) )
		);

		$title = '' !== $s['title'] ? $s['title'] : ( $s['reservable'] ? '1:1 meetings' : 'Session' );

		// Avatars sit in the top row beside the time, so even short talks show them.
		$avatars_html = '';
		if ( $o['show_avatars'] && $s['speakers'] ) {
			$shown         = array_slice( $s['speakers'], 0, $o['max_avatars'] );
			$extra         = count( $s['speakers'] ) - count( $shown );
			$avatars_html  = '<span class="ba-avatars">';
			foreach ( $shown as $sp ) {
				$avatars_html .= self::avatar_html( $sp, 'ba-avatar' );
			}
			if ( $extra > 0 ) {
				$avatars_html .= '<span class="ba-avatar ba-avatar--more" title="' . esc_attr( $extra . ' more' ) . '">+' . (int) $extra . '</span>';
			}
			$avatars_html .= '</span>';
		}

		$speakers_html = '';
		if ( $o['show_speakers'] && $s['speakers'] ) {
			$names         = array_map( function ( $sp ) {
				return esc_html( $sp['name'] );
			}, $s['speakers'] );
			$speakers_html = '<p class="ba-session__speakers"><span class="ba-session__speaker-names">' . implode( ', ', $names ) . '</span></p>';
		}

		$classes = [ 'ba-session' ];
		// Size tiers, so small cards drop secondary detail instead of clipping it.
		// Hover or keyboard focus expands any card to show everything.
		$minutes = $rows * $o['step'];
		if ( $rows <= 2 ) {
			$classes[] = 'is-short';
		} elseif ( $rows <= 4 || $minutes <= 20 ) {
			$classes[] = 'is-compact';
		} elseif ( $rows <= 6 || $minutes <= 30 ) {
			$classes[] = 'is-medium';
		}
		if ( $s['reservable'] ) {
			$classes[] = 'is-networking';
		}

		$body  = '<div class="ba-session__inner">';
		$body .= '<div class="ba-session__top"><p class="ba-session__time">' . $time . '</p>' . $avatars_html . '</div>';
		$body .= '<p class="ba-session__track">' . esc_html( $track['name'] ) . '</p>';
		if ( $o['show_subtitle'] && '' !== $s['subtitle'] ) {
			$body .= '<p class="ba-session__subtitle">' . esc_html( $s['subtitle'] ) . '</p>';
		}
		$title_inner = 'modal' === $o['details']
			? '<button type="button" class="ba-session__open">' . esc_html( $title ) . '</button>'
			: esc_html( $title );
		$body       .= sprintf( '<%1$s class="ba-session__title">%2$s</%1$s>', $tag, $title_inner );
		if ( $o['show_location'] && '' !== $s['location'] ) {
			$body .= '<p class="ba-session__location">' . esc_html( $s['location'] ) . '</p>';
		}
		$body .= $speakers_html;
		if ( $o['show_excerpt'] ) {
			$body .= '<div class="ba-session__excerpt">' . self::content_html( $s['content'] ) . '</div>';
		}
		$body .= '</div>';

		if ( 'modal' === $o['details'] ) {
			$body .= '<template class="ba-session__detail">' . self::detail_html( $s, $track, $title, $time, $o ) . '</template>';
		}

		return sprintf(
			'<article class="%s" role="listitem" style="grid-row:%d / %d;grid-column:%d / span %d;%s" data-track="%s" data-start="%d" data-end="%d" data-speakers="%s" data-tags="%s" data-type="%s">%s</article>',
			esc_attr( implode( ' ', $classes ) ),
			$r1,
			$r2,
			$col,
			$span,
			esc_attr( self::accent_css( $color ) ),
			esc_attr( $s['track'] ),
			$s['start'] * 1000,
			$s['end'] * 1000,
			esc_attr( implode( ' ', array_map( [ __CLASS__, 'speaker_key' ], $s['speakers'] ) ) ),
			esc_attr( implode( ' ', array_map( [ __CLASS__, 'tag_key' ], $s['tags'] ) ) ),
			esc_attr( '' !== trim( $s['subtitle'] ) ? self::type_key( $s['subtitle'] ) : '' ),
			$body
		);
	}

	/**
	 * Full detail shown in the modal.
	 */
	private static function detail_html( array $s, array $track, $title, $time, array $o ) {
		$h  = '<p class="ba-detail__meta"><span class="ba-detail__track">' . esc_html( $track['name'] ) . '</span>';
		$h .= '<span class="ba-detail__time">' . $time . '</span>';
		if ( '' !== $s['location'] ) {
			$h .= '<span class="ba-detail__location">' . esc_html( $s['location'] ) . '</span>';
		}
		$h .= '</p>';
		if ( '' !== $s['subtitle'] ) {
			$h .= '<p class="ba-detail__subtitle">' . esc_html( $s['subtitle'] ) . '</p>';
		}
		$h .= '<h2 class="ba-detail__title" data-dialog-title>' . esc_html( $title ) . '</h2>';
		if ( $s['cover'] ) {
			$h .= '<img class="ba-detail__cover" src="' . esc_url( $s['cover'] ) . '" alt="" loading="lazy">';
		}
		$h .= '<div class="ba-detail__content">' . self::content_html( $s['content'] ) . '</div>';

		if ( $s['speakers'] ) {
			$h .= '<ul class="ba-detail__speakers">';
			foreach ( $s['speakers'] as $sp ) {
				$h .= '<li class="ba-speaker">';
				$h .= self::avatar_html( $sp, 'ba-speaker__photo' );
				$meta = implode( ', ', array_filter( [ $sp['title'], $sp['company'] ] ) );
				$h   .= '<span class="ba-speaker__text"><strong>' . esc_html( $sp['name'] ) . '</strong>';
				if ( $sp['role'] ) {
					$h .= ' <em>' . esc_html( $sp['role'] ) . '</em>';
				}
				if ( $meta ) {
					$h .= '<span>' . esc_html( $meta ) . '</span>';
				}
				$h .= '</span></li>';
			}
			$h .= '</ul>';
		}

		if ( $s['tags'] ) {
			$h .= '<p class="ba-detail__tags">';
			foreach ( $s['tags'] as $t ) {
				$h .= '<span class="ba-tag">' . esc_html( $t['name'] ) . '</span>';
			}
			$h .= '</p>';
		}

		return $h;
	}

	/**
	 * Speaker photo from Brella, or their initials when no photo is uploaded.
	 */
	private static function avatar_html( array $sp, $class ) {
		$name = (string) ( $sp['name'] ?? '' );

		if ( ! empty( $sp['photo'] ) ) {
			return sprintf(
				'<img class="%1$s" src="%2$s" alt="%3$s" title="%3$s" loading="lazy" decoding="async" width="48" height="48">',
				esc_attr( $class ),
				esc_url( $sp['photo'] ),
				esc_attr( $name )
			);
		}

		return sprintf(
			'<span class="%1$s %1$s--initials" role="img" aria-label="%2$s" title="%2$s">%3$s</span>',
			esc_attr( $class ),
			esc_attr( $name ),
			esc_html( self::initials( $name ) )
		);
	}

	/**
	 * "Dr Jeilin Chang" becomes "JC". Honorifics are skipped.
	 */
	private static function initials( $name ) {
		$words = preg_split( '/\s+/u', trim( (string) $name ) );
		$words = array_values( array_filter( $words, function ( $w ) {
			return '' !== $w && ! preg_match( '/^(dr|mr|mrs|ms|mx|prof|professor|sir|dame|capt|rev|hon)\.?$/i', $w );
		} ) );
		if ( ! $words ) {
			return '?';
		}
		$first = mb_substr( $words[0], 0, 1 );
		$last  = count( $words ) > 1 ? mb_substr( end( $words ), 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * Brella stores descriptions as Draft.js blocks. Convert to simple HTML.
	 */
	private static function content_html( $content ) {
		if ( ! is_array( $content ) || empty( $content['blocks'] ) ) {
			return '';
		}

		$out  = '';
		$list = null;

		foreach ( $content['blocks'] as $block ) {
			$text = trim( (string) ( $block['text'] ?? '' ) );
			$type = (string) ( $block['type'] ?? 'unstyled' );

			$list_type = 'unordered-list-item' === $type ? 'ul' : ( 'ordered-list-item' === $type ? 'ol' : null );
			if ( $list !== $list_type ) {
				if ( $list ) {
					$out .= "</{$list}>";
				}
				if ( $list_type ) {
					$out .= "<{$list_type}>";
				}
				$list = $list_type;
			}

			if ( '' === $text ) {
				continue;
			}

			$text = nl2br( esc_html( $text ) );

			if ( $list_type ) {
				$out .= "<li>{$text}</li>";
			} elseif ( 0 === strpos( $type, 'header-' ) ) {
				$out .= "<h4>{$text}</h4>";
			} elseif ( 'blockquote' === $type ) {
				$out .= "<blockquote>{$text}</blockquote>";
			} else {
				$out .= "<p>{$text}</p>";
			}
		}

		if ( $list ) {
			$out .= "</{$list}>";
		}

		return $out;
	}

	/**
	 * Map a Brella colour ("green", "magenta" or a hex) to a CSS custom property.
	 */
	private static function accent_css( $color ) {
		$color = strtolower( trim( (string) $color ) );
		if ( preg_match( '/^#[0-9a-f]{3,8}$/', $color ) ) {
			return '--ba-accent:' . $color . ';';
		}
		$color = preg_replace( '/[^a-z0-9\-]/', '', $color );
		return $color ? '--ba-accent:var(--ba-c-' . $color . ', var(--ba-accent-default));' : '';
	}
}
