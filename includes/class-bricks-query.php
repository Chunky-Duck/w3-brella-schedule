<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks custom query: Brella Schedule.
 */
class Bricks_Query {

	public static function init() {
		add_filter( 'bricks/setup/control_options', array( __CLASS__, 'add_control_options' ) );
		add_filter( 'bricks/query/run', array( __CLASS__, 'run_query' ), 10, 2 );
		add_filter( 'bricks/query/loop_object', array( __CLASS__, 'set_loop_object' ), 10, 3 );
		add_filter( 'bricks/query/loop_object_type', array( __CLASS__, 'set_loop_object_type' ), 10, 3 );
		add_filter( 'bricks/query/loop_object_id', array( __CLASS__, 'set_loop_object_id' ), 10, 3 );
	}

	/**
	 * @param array<string,mixed> $control_options Control options.
	 * @return array<string,mixed>
	 */
	public static function add_control_options( $control_options ) {
		if ( ! isset( $control_options['queryTypes'] ) || ! is_array( $control_options['queryTypes'] ) ) {
			$control_options['queryTypes'] = array();
		}
		$control_options['queryTypes']['brellaSchedule'] = esc_html__( 'Brella Schedule', 'cryptocon-brella' );
		return $control_options;
	}

	/**
	 * @param array<int,mixed> $results Query results.
	 * @param \Bricks\Query    $query   Query instance.
	 * @return array<int,mixed>
	 */
	public static function run_query( $results, $query ) {
		if ( ! is_object( $query ) || ! isset( $query->object_type ) || 'brellaSchedule' !== $query->object_type ) {
			return $results;
		}

		$sessions = Schedule_Sync::instance()->get_sessions_for_display();
		return array_values( $sessions );
	}

	/**
	 * @param mixed       $loop_object Loop object.
	 * @param string|int  $loop_key    Loop key.
	 * @param \Bricks\Query $query     Query instance.
	 * @return mixed
	 */
	public static function set_loop_object( $loop_object, $loop_key, $query ) {
		if ( ! is_object( $query ) || ! isset( $query->object_type ) || 'brellaSchedule' !== $query->object_type ) {
			return $loop_object;
		}
		return $loop_object;
	}

	/**
	 * @param string     $object_type Object type.
	 * @param mixed      $object      Loop object.
	 * @param string|int $query_id    Query ID.
	 * @return string
	 */
	public static function set_loop_object_type( $object_type, $object, $query_id ) {
		if ( ! class_exists( '\Bricks\Query' ) ) {
			return $object_type;
		}

		$query_object_type = \Bricks\Query::get_query_object_type( $query_id );
		if ( 'brellaSchedule' !== $query_object_type ) {
			return $object_type;
		}

		return 'brellaSession';
	}

	/**
	 * @param string|int $object_id Object ID.
	 * @param mixed      $object    Loop object.
	 * @param string|int $query_id  Query ID.
	 * @return string|int
	 */
	public static function set_loop_object_id( $object_id, $object, $query_id ) {
		if ( ! class_exists( '\Bricks\Query' ) ) {
			return $object_id;
		}

		$query_object_type = \Bricks\Query::get_query_object_type( $query_id );
		if ( 'brellaSchedule' !== $query_object_type ) {
			return $object_id;
		}

		if ( is_array( $object ) && ! empty( $object['id'] ) ) {
			return (string) $object['id'];
		}

		return $object_id;
	}
}
