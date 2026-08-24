<?php
namespace CC\Brella;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brella Integration API client.
 */
class Api_Client {

	const BASE_URL = 'https://api.brella.io/api/integration';

	/**
	 * Lightweight connection test (one page, size 1).
	 *
	 * @param array{api_key:string,organization_id:string,event_id:string} $creds Credentials.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function test_connection( array $creds ) {
		$response = self::request(
			$creds,
			sprintf(
				'/organizations/%s/events/%s/timeslots',
				rawurlencode( $creds['organization_id'] ),
				rawurlencode( $creds['event_id'] )
			),
			array(
				'page' => array(
					'size'   => 1,
					'number' => 1,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$meta  = isset( $response['meta'] ) && is_array( $response['meta'] ) ? $response['meta'] : array();
		$data  = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$first = $data[0] ?? null;

		$sample_title = '';
		$sample_start = '';
		if ( is_array( $first ) && isset( $first['attributes'] ) && is_array( $first['attributes'] ) ) {
			$attrs        = $first['attributes'];
			$sample_title = (string) ( $attrs['title'] ?? '' );
			$sample_start = (string) ( $attrs['start-time'] ?? '' );
		}

		return array(
			'message'       => __( 'Connection successful.', 'cryptocon-brella' ),
			'total_count'   => (string) ( $meta['total_count'] ?? count( $data ) ),
			'sample_title'  => $sample_title,
			'sample_start'  => $sample_start,
			'api_version'   => 'v4',
		);
	}

	/**
	 * Fetch all timeslot pages.
	 *
	 * @param array{api_key:string,organization_id:string,event_id:string} $creds Credentials.
	 * @return array{data:array<int,array<string,mixed>>,included:array<int,array<string,mixed>>}|\WP_Error
	 */
	public static function fetch_all_timeslots( array $creds ) {
		$path     = sprintf(
			'/organizations/%s/events/%s/timeslots',
			rawurlencode( $creds['organization_id'] ),
			rawurlencode( $creds['event_id'] )
		);
		$all_data = array();
		$included = array();
		$page     = 1;
		$total    = 1;

		while ( $page <= $total ) {
			$response = self::request(
				$creds,
				$path,
				array(
					'page' => array(
						'size'   => 500,
						'number' => $page,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
				$all_data = array_merge( $all_data, $response['data'] );
			}

			if ( ! empty( $response['included'] ) && is_array( $response['included'] ) ) {
				$included = array_merge( $included, $response['included'] );
			}

			$meta  = isset( $response['meta'] ) && is_array( $response['meta'] ) ? $response['meta'] : array();
			$total = max( 1, (int) ( $meta['total_pages'] ?? 1 ) );
			++$page;
		}

		$included = self::dedupe_included( $included );

		return array(
			'data'     => $all_data,
			'included' => $included,
		);
	}

	/**
	 * @param array{api_key:string,organization_id:string,event_id:string} $creds Credentials.
	 * @return string|\WP_Error Timezone string or empty.
	 */
	public static function fetch_event_timezone( array $creds ) {
		$response = self::request(
			$creds,
			sprintf(
				'/organizations/%s/events/%s',
				rawurlencode( $creds['organization_id'] ),
				rawurlencode( $creds['event_id'] )
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response['data'] ?? null;
		if ( is_array( $data ) && isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$tz = $data['attributes']['timezone'] ?? $data['attributes']['time-zone'] ?? '';
			if ( is_string( $tz ) && '' !== $tz ) {
				return $tz;
			}
		}

		return '';
	}

	/**
	 * @param array{api_key:string,organization_id:string,event_id:string} $creds Credentials.
	 * @param string                                                         $path  Path after base URL.
	 * @param array<string,mixed>                                              $query Query args.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function request( array $creds, $path, array $query = array() ) {
		$url = trailingslashit( self::BASE_URL ) . ltrim( $path, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( self::flatten_query( $query ), $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Brella-Api-Access-Token' => $creds['api_key'],
					'Accept'                   => 'application/vnd.brella.v4+json',
					'Content-Type'             => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return self::error_from_response( $code, $json, $body );
		}

		if ( ! is_array( $json ) ) {
			return new \WP_Error( 'brella_invalid_json', __( 'Invalid JSON response from Brella.', 'cryptocon-brella' ) );
		}

		return $json;
	}

	/**
	 * @param int                  $code HTTP status.
	 * @param array<string,mixed>|null $json Decoded body.
	 * @param string               $body Raw body.
	 * @return \WP_Error
	 */
	private static function error_from_response( $code, $json, $body ) {
		$message = __( 'Brella API request failed.', 'cryptocon-brella' );
		$hint    = '';

		if ( is_array( $json ) ) {
			if ( ! empty( $json['errors'] ) && is_array( $json['errors'] ) ) {
				$parts = array();
				foreach ( $json['errors'] as $err ) {
					if ( is_array( $err ) && ! empty( $err['detail'] ) ) {
						$parts[] = (string) $err['detail'];
					} elseif ( is_array( $err ) && ! empty( $err['title'] ) ) {
						$parts[] = (string) $err['title'];
					}
				}
				if ( $parts ) {
					$message = implode( ' ', $parts );
				}
			} elseif ( ! empty( $json['message'] ) ) {
				$message = (string) $json['message'];
			}
		}

		switch ( $code ) {
			case 401:
				$hint = __( 'Check your API key. It must be generated by a Brella Organization Admin.', 'cryptocon-brella' );
				break;
			case 404:
				$hint = __( 'Check Organization ID and Event ID.', 'cryptocon-brella' );
				break;
			default:
				$hint = sprintf(
					/* translators: %d: HTTP status code */
					__( 'HTTP status %d.', 'cryptocon-brella' ),
					$code
				);
		}

		return new \WP_Error(
			'brella_http_' . $code,
			$message,
			array(
				'status' => $code,
				'hint'   => $hint,
				'body'   => $body,
			)
		);
	}

	/**
	 * @param array<string,mixed> $query Nested query (e.g. page[size]).
	 * @return array<string,string|int>
	 */
	private static function flatten_query( array $query ) {
		$flat = array();
		foreach ( $query as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$flat[ $key . '[' . $sub_key . ']' ] = $sub_value;
				}
			} else {
				$flat[ $key ] = $value;
			}
		}
		return $flat;
	}

	/**
	 * @param array<int,array<string,mixed>> $included Included resources.
	 * @return array<int,array<string,mixed>>
	 */
	private static function dedupe_included( array $included ) {
		$map = array();
		foreach ( $included as $item ) {
			if ( ! is_array( $item ) || empty( $item['type'] ) || empty( $item['id'] ) ) {
				continue;
			}
			$key       = $item['type'] . ':' . $item['id'];
			$map[ $key ] = $item;
		}
		return array_values( $map );
	}
}
