<?php

namespace CommonsBooking\Service;

/**
 * Checks whether this instance's GBFS auto-discovery feed is listed in public
 * mobility-data directories, so operators can see if their feed is discoverable
 * (e.g. by consumers that read these registries).
 *
 * Only the MobilityData `systems.csv` catalog is checked automatically, because
 * it is a plain, unauthenticated CSV. Transitland and the German Mobilithek
 * require an API key / client-certificate auth respectively and are therefore
 * surfaced as manual-check links instead of an automated boolean.
 *
 * The computed result is stored as a single WordPress option together with the
 * time it was last checked. It is (re)computed once per day via the
 * {@see Scheduler} and on demand via a button on the dashboard. Outbound
 * requests send conditional cache headers (If-None-Match / If-Modified-Since)
 * so the (large) catalog is not re-downloaded when it has not changed.
 */
class GBFSDiscoveryCheck {

	/**
	 * Option key under which the computed status blob is stored.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'commonsbooking_gbfs_discovery_status';

	/**
	 * Raw URL of the MobilityData systems catalog.
	 *
	 * @var string
	 */
	const SYSTEMS_CSV_URL = 'https://raw.githubusercontent.com/MobilityData/gbfs/master/systems.csv';

	/**
	 * Total number of directories we report on (1 automated + 2 manual).
	 *
	 * @var int
	 */
	const TOTAL_SOURCES = 3;

	/**
	 * Returns the URL of this instance's GBFS auto-discovery feed.
	 *
	 * @return string
	 */
	public static function getFeedUrl(): string {
		return get_rest_url( null, 'commonsbooking/v1/gbfs.json' );
	}

	/**
	 * Returns the last computed status blob, or null if never checked yet.
	 *
	 * @return array|null
	 */
	public static function getStatus() {
		$status = get_option( self::OPTION_KEY );
		return is_array( $status ) ? $status : null;
	}

	/**
	 * Recomputes the discovery status, persists it and returns it.
	 *
	 * @return array
	 */
	public static function refresh(): array {
		$previous = self::getStatus();
		$feedUrl  = self::getFeedUrl();

		$systemsCsv = self::checkSystemsCsv( $feedUrl, $previous );

		$sources = array(
			'systems_csv' => $systemsCsv,
			'transitland' => array(
				'label'  => 'Transitland Atlas',
				'type'   => 'manual',
				'status' => 'manual',
				'link'   => 'https://www.transit.land/feeds',
			),
			'mobilithek'  => array(
				'label'  => 'Mobilithek (NAP)',
				'type'   => 'manual',
				'status' => 'manual',
				'link'   => 'https://mobilithek.info/offers',
			),
		);

		$includedCount = 0;
		foreach ( $sources as $source ) {
			if ( isset( $source['status'] ) && 'included' === $source['status'] ) {
				++$includedCount;
			}
		}

		$status = array(
			'feed_url'       => $feedUrl,
			'sources'        => $sources,
			'included_count' => $includedCount,
			'total'          => self::TOTAL_SOURCES,
			'summary'        => $includedCount . '/' . self::TOTAL_SOURCES,
			'last_checked'   => time(),
		);

		update_option( self::OPTION_KEY, $status, false );

		return $status;
	}

	/**
	 * Checks whether the feed is listed in the MobilityData systems.csv catalog.
	 *
	 * Sends conditional cache headers based on the previously stored ETag /
	 * Last-Modified. On a 304 response the previous status is kept and only the
	 * check timestamp is refreshed. On a network error the previous status is
	 * kept (stale) rather than flapping to an error.
	 *
	 * @param string     $feedUrl  The auto-discovery URL of this instance.
	 * @param array|null $previous The previously stored status blob.
	 *
	 * @return array Source result: label, type, status, link, etag, last_modified, checked_at.
	 */
	private static function checkSystemsCsv( string $feedUrl, $previous ): array {
		$prev = ( is_array( $previous ) && isset( $previous['sources']['systems_csv'] ) )
			? $previous['sources']['systems_csv']
			: array();

		$result = array(
			'label'         => 'MobilityData systems.csv',
			'type'          => 'auto',
			'status'        => isset( $prev['status'] ) ? $prev['status'] : 'unknown',
			'link'          => 'https://github.com/MobilityData/gbfs/blob/master/systems.csv',
			'etag'          => isset( $prev['etag'] ) ? $prev['etag'] : '',
			'last_modified' => isset( $prev['last_modified'] ) ? $prev['last_modified'] : '',
			'checked_at'    => time(),
		);

		$headers = array();
		if ( ! empty( $prev['etag'] ) ) {
			$headers['If-None-Match'] = $prev['etag'];
		}
		if ( ! empty( $prev['last_modified'] ) ) {
			$headers['If-Modified-Since'] = $prev['last_modified'];
		}

		$response = wp_remote_get(
			self::SYSTEMS_CSV_URL,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			commonsbooking_write_log( 'GBFS discovery check failed: ' . $response->get_error_message() );
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Not modified since last check: keep previous status and cache validators.
		if ( 304 === $code ) {
			return $result;
		}

		if ( 200 !== $code ) {
			commonsbooking_write_log( 'GBFS discovery check: unexpected HTTP status ' . $code );
			return $result;
		}

		$body               = wp_remote_retrieve_body( $response );
		$result['status']   = self::isListedInCsv( $body, $feedUrl ) ? 'included' : 'not_included';
		$etag               = wp_remote_retrieve_header( $response, 'etag' );
		$lastModified       = wp_remote_retrieve_header( $response, 'last-modified' );
		$result['etag']          = is_string( $etag ) ? $etag : '';
		$result['last_modified'] = is_string( $lastModified ) ? $lastModified : '';

		return $result;
	}

	/**
	 * Determines whether the given feed URL's host appears in the auto-discovery
	 * column of the systems.csv body. A host match is used because one instance
	 * maps to one domain; this tolerates minor path/scheme differences.
	 *
	 * @param string $body    The raw CSV content.
	 * @param string $feedUrl This instance's auto-discovery URL.
	 *
	 * @return bool
	 */
	public static function isListedInCsv( string $body, string $feedUrl ): bool {
		$ourHost = wp_parse_url( $feedUrl, PHP_URL_HOST );
		if ( empty( $ourHost ) ) {
			return false;
		}
		$ourHost = strtolower( $ourHost );

		$lines = preg_split( '/\r\n|\r|\n/', $body );
		if ( ! is_array( $lines ) || empty( $lines ) ) {
			return false;
		}

		$header = str_getcsv( (string) array_shift( $lines ), ',', '"', '' );
		$colIdx = null;
		foreach ( $header as $i => $name ) {
			if ( preg_match( '/auto-?discovery/i', (string) $name ) ) {
				$colIdx = $i;
				break;
			}
		}

		// If the expected column is missing, fall back to a host substring scan.
		if ( null === $colIdx ) {
			return false !== stripos( $body, $ourHost );
		}

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$row = str_getcsv( $line, ',', '"', '' );
			if ( ! isset( $row[ $colIdx ] ) ) {
				continue;
			}
			$rowHost = wp_parse_url( trim( $row[ $colIdx ] ), PHP_URL_HOST );
			if ( ! empty( $rowHost ) && strtolower( $rowHost ) === $ourHost ) {
				return true;
			}
		}

		return false;
	}
}
