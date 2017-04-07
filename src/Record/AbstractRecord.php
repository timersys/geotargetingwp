<?php
namespace GeotWP\Record;

/**
 * Response from API
 * Class GeotRecord
 * @package GeotWP
 */
abstract class AbstractRecord {

	private $data;

	/**
	 * Valid locales
	 * @var array
	 */
	private $locales = [
		"pt-BR",
		"ru",
		"zh-CN",
		"de",
		"en",
		"es",
		"fr",
		"ja",
	];

	public $default_locale = 'en';

	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Magic method to get Record properties
	 * @param $name
	 *
	 * @return null
	 */
	public function __get($name) {
		if( $name === 'name' )
			return $this->name();

		if ( property_exists($this->data, $name) ) {
			return $this->data->$name;
		}

		return null;
	}
	/**
	 * Return name in default locale
	 * @return string
	 */
	public function name() {
		if( empty($this->data->names) )
			return null;
		return isset( $this->data->names->{$this->default_locale} ) ? $this->data->names->{$this->default_locale} : $this->data->names[0];
	}

}