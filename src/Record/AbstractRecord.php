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
	 * Custom implementation of isset
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		if( $name === 'name' ) {
			return $this->name();
		}
		return isset( $this->data->$name );
	}

	/**
	 * Return name in default locale
	 * @return string
	 */
	public function name() {
		if( empty($this->data->names) )
			return null;
		// check if we have result for locale
		if ( isset( $this->data->names->{$this->default_locale} ) )
            return $this->data->names->{$this->default_locale};
        // otherwise fallback to english
		if ( isset( $this->data->names->en ) )
            return $this->data->names->en;
        // none of the above, return first result
        return $this->data->names[0];
	}

    /**
     * @return array
     */
    public function getLocales() {
        return $this->locales;
    }

    /**
     * @param string $default_locale
     */
    public function setDefaultLocale($default_locale) {
        if (! in_array($default_locale, $this->locales) )
            $default_locale = 'en';
        $this->default_locale = $default_locale;
    }

}