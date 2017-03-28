<?php
namespace GeotWP\Record;

/**
 * Response from API
 * Class GeotRecord
 * @package GeotWP
 */
abstract class AbstractRecord {

	public $data;

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
	 * Return name in default locale
	 * @return string
	 */
	public function name() {
		if( empty($this->data->names) )
			return '';
		return isset( $this->data->names[$this->default_locale] ) ? $this->data->names[$this->default_locale] : $this->data->names[0];
	}

	/**
	 * @return mixed
	 */
	public function names() {
		return $this->data->names;
	}

	/**
	 * @return string
	 */
	public function iso_code() {
		return isset( $this->data->iso_code ) ? $this->data->iso_code : '';
	}

}