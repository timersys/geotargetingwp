<?php
namespace GeotWP\Record;

class GeotRecord {

	private $valid_classes = [
		'city',
		'country',
		'continent',
		'state'
	];
	/**
	 * Record constructor.
	 *
	 * @param $response
	 */
	public function __construct( $response ) {
		if( !empty( $response ) ) {
			foreach ( $response as $class => $data ) {
				if( in_array( $class, $this->valid_classes ) ){
					$record_name = ucfirst($class);
					$this->$class = new $record_name($data);
				}
			}
		}
	}
}