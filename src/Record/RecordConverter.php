<?php

namespace GeotWP\Record;
use JsonSerializable;

/**
 * Holds the record that API will return
 * Class GeotRecord
 * @property  city
 * @package app\Http
 */
class RecordConverter implements JsonSerializable{
	protected static $geot_record;
	protected $city;
	protected $continent;
	protected $country;
	protected $state;

	public static function maxmindRecord( $record ) {
		if( isset( $record->error ) )
			return $record;

		self::$geot_record = [];
		self::$geot_record['city']['names']         = isset($record['city']) && isset($record['city']['names'] ) ? $record['city']['names'] : '';
		self::$geot_record['city']['zip']           = isset($record['postal']) && isset($record['postal']['code'] ) ? $record['postal']['code'] : '';
		self::$geot_record['continent']['names']    = isset($record['continent']) && isset($record['continent']['names'] ) ? $record['continent']['names'] : '';
		self::$geot_record['continent']['iso_code'] = isset($record['continent']) && isset($record['continent']['code'] ) ? $record['continent']['code'] : '';
		self::$geot_record['country']['iso_code']   = isset($record['country']) && isset($record['country']['iso_code'] ) ? $record['country']['iso_code'] : '';
		self::$geot_record['country']['names']      = isset($record['country']) && isset($record['country']['names'] ) ? $record['country']['names'] : '';
		self::$geot_record['state']['iso_code']     = isset($record['subdivisions']) && isset($record['subdivisions'][0]) && isset($record['subdivisions'][0]['iso_code']) ? $record['subdivisions'][0]['iso_code']: '';
		self::$geot_record['state']['names']        = isset($record['subdivisions']) && isset($record['subdivisions'][0]) && isset($record['subdivisions'][0]['names']) ? $record['subdivisions'][0]['names']: '';

		return   json_encode(self::$geot_record);
	}

	public function jsonSerialize() {
		return self::$geot_record;
	}
}