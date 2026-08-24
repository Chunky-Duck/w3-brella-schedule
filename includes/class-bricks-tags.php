<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks dynamic data tags for Brella schedule sessions.
 */
class Bricks_Tags {

	/** @var array<string,array{label:string,group:string,callback:callable}> */
	private static $tags = array();

	public static function init() {
		self::register_definitions();
		add_filter( 'bricks/dynamic_tags_list', array( __CLASS__, 'add_to_builder' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( __CLASS__, 'render_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( __CLASS__, 'render_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( __CLASS__, 'render_content' ), 20, 2 );
	}

	private static function register_definitions() {
		$sync = function () {
			return Schedule_Sync::instance();
		};

		$session_field = function ( $field ) use ( $sync ) {
			return function () use ( $sync, $field ) {
				$row = $sync()->current_session();
				if ( ! $row || ! isset( $row[ $field ] ) ) {
					return '';
				}
				$value = $row[ $field ];
				if ( is_array( $value ) ) {
					return '';
				}
				return (string) $value;
			};
		};

		$group = __( 'Brella · Schedule', 'cryptocon-brella' );
		$meta  = __( 'Brella · Meta', 'cryptocon-brella' );

		self::$tags = array(
			'brella_session_title'      => array(
				'label'    => __( 'Session title', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'title' ),
			),
			'brella_session_subtitle'   => array(
				'label'    => __( 'Session subtitle', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'subtitle' ),
			),
			'brella_session_start'      => array(
				'label'    => __( 'Session start (ISO)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'start_time' ),
			),
			'brella_session_end'        => array(
				'label'    => __( 'Session end (ISO)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'end_time' ),
			),
			'brella_session_start_local'=> array(
				'label'    => __( 'Session start (local)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'start_local' ),
			),
			'brella_session_time_range' => array(
				'label'    => __( 'Session time range', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'time_range' ),
			),
			'brella_session_day_label'  => array(
				'label'    => __( 'Session day label', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'day_label' ),
			),
			'brella_session_location'   => array(
				'label'    => __( 'Session location', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'location' ),
			),
			'brella_session_tracks'     => array(
				'label'    => __( 'Session tracks (comma-separated)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => function () use ( $sync ) {
					$row = $sync()->current_session();
					if ( ! $row || empty( $row['tracks'] ) || ! is_array( $row['tracks'] ) ) {
						return '';
					}
					return implode( ', ', array_map( 'strval', $row['tracks'] ) );
				},
			),
			'brella_session_speakers'     => array(
				'label'    => __( 'Session speakers (comma-separated)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => $session_field( 'speakers_text' ),
			),
			'brella_session_speakers_list' => array(
				'label'    => __( 'Session speakers (HTML list)', 'cryptocon-brella' ),
				'group'    => $group,
				'callback' => function () use ( $sync ) {
					$row = $sync()->current_session();
					if ( ! $row || empty( $row['speakers'] ) || ! is_array( $row['speakers'] ) ) {
						return '';
					}
					$items = array();
					foreach ( $row['speakers'] as $speaker ) {
						if ( ! is_array( $speaker ) || empty( $speaker['name'] ) ) {
							continue;
						}
						$label = esc_html( $speaker['name'] );
						if ( ! empty( $speaker['role'] ) ) {
							$label .= ' <span class="brella-speaker-role">(' . esc_html( $speaker['role'] ) . ')</span>';
						}
						$items[] = '<li>' . $label . '</li>';
					}
					if ( ! $items ) {
						return '';
					}
					return '<ul class="brella-speakers-list">' . implode( '', $items ) . '</ul>';
				},
			),
			'brella_schedule_last_sync' => array(
				'label'    => __( 'Schedule last sync time', 'cryptocon-brella' ),
				'group'    => $meta,
				'callback' => function () {
					return Cache::last_sync_human();
				},
			),
			'brella_schedule_count'     => array(
				'label'    => __( 'Schedule session count', 'cryptocon-brella' ),
				'group'    => $meta,
				'callback' => function () {
					return (string) count( Cache::get_sessions() );
				},
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $tags Existing tags.
	 * @return array<int,array<string,mixed>>
	 */
	public static function add_to_builder( $tags ) {
		foreach ( self::$tags as $name => $def ) {
			$tags[] = array(
				'name'  => '{' . $name . '}',
				'label' => $def['label'],
				'group' => $def['group'],
			);
		}
		return $tags;
	}

	/**
	 * @param mixed  $tag     Tag.
	 * @param mixed  $post    Post.
	 * @param string $context Context.
	 * @return mixed
	 */
	public static function render_tag( $tag, $post, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		$clean = str_replace( array( '{', '}' ), '', $tag );
		$parts = explode( ':', $clean );
		$name  = $parts[0];

		if ( ! isset( self::$tags[ $name ] ) ) {
			return $tag;
		}

		$value = call_user_func( self::$tags[ $name ]['callback'] );

		if ( 'brella_session_speakers_list' === $name && 'text' === $context ) {
			return wp_strip_all_tags( (string) $value );
		}

		return null === $value ? '' : $value;
	}

	/**
	 * @param string $content Content.
	 * @param mixed  $post    Post.
	 * @param string $context Context.
	 * @return string
	 */
	public static function render_content( $content, $post, $context = 'text' ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{brella_' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/\{(brella_[a-z0-9_]+)(?::[^}]*)?\}/',
			function ( $m ) use ( $post, $context ) {
				$value = self::render_tag( $m[1], $post, $context );
				if ( is_string( $value ) && $value === $m[1] ) {
					return $m[0];
				}
				return (string) $value;
			},
			$content
		);
	}
}
