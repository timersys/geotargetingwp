<?php
namespace GeotWP\Record;

class GeotRecord {

	public static $valid_records = [
		'city',
		'country',
		'continent',
		'state',
		'geolocation'
	];
	/**
	 * Record constructor.
	 *
	 * @param $response
	 */
	public function __construct( $response ) {
		if( !empty( $response ) ) {
			foreach ( $response as $class => $data ) {
				if( in_array( $class, self::$valid_records ) ){
					$record_name = 'GeotWP\Record\\'.ucfirst($class);
					$this->$class = new $record_name($data);
				}
			}
		}
	}

	/**
	 * @return mixed
	 */
	public static function getValidRecords(){
		return self::$valid_records;
	}
}