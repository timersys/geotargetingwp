<?php namespace GeotWP;
use GeotWP\Exception\AddressNotFoundException;
use GeotWP\Exception\GeotRequestException;
use GeotWP\Exception\InvalidIPException;
use GeotWP\Exception\InvalidLicenseException;
use GeotWP\Exception\OutofCreditsException;
use GeotWP\Record\GeotRecord;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class GeotargetingWP{

	/**
	 * @var Client
	 */
	private static $client;

	private $ip;

	private $license;

	private $user_data;
	/**
	 * @var Array of settings
	 */
	private $opts;

	/**
	 * Constructor
	 *
	 * @param $acces_token
	 * @param array $args
	 *
	 * @throws InvalidLicenseException
	 */
	public function __construct( $acces_token, $args = array() ) {

		if( empty( $acces_token ) )
			throw new InvalidLicenseException('License is missing');

		$this->license = $acces_token;
		$this->ip = getUserIP();
		$this->set_defaults($args);

	}

	/**
	 * Main function that return User data
	 *
	 * @param string $ip
	 *
	 * @return mixed
	 * @throws GeotRequestException
	 */
	public function getData( $ip = "" ){
		if( ! empty( $ip ) )
			$this->ip = $ip;

		if( ! empty ( $this->user_data ) && empty( $ip ) )
			return $this->user_data;

		$this->initUserData();

		// Start sessions if needed
		if( is_session_started() === FALSE && $this->opts['cache_mode'] )
			session_start();

		// Easy debug
		if( isset( $_GET['geot_debug'] ) )
			return $this->debugData();

		// If user set cookie and not in debug mode
		if(  ! $this->opts['debug_mode']  &&  ! empty( $_COOKIE[$this->opts['cookie_name']] ) )
			return $this->setUserData('geot_cookie' , $_COOKIE[$this->opts['cookie_name']] );

		// If we already calculated on session return (if we are not calling by IP & if cache mode (sessions) is turned on)
		if( empty( $ip ) && $this->opts['cache_mode'] && !empty ( $_SESSION['geot_data'] ) && ! $this->opts['debug_mode'] )
			return  unserialize( $_SESSION['geot_data'] ) ;

		// check for crawlers
		$CD = new CrawlerDetect();
		if( $CD->isCrawler() && ! empty( $this->opts['bots_country'] ) )
			return $this->setUserData('country' , $this->opts['bots_country']);

		// time to call api
		try{
			$res = self::client()->request('GET', 'data', [
				'query' => [
					'ip' => $this->ip,
					'license' => $this->license,
				]
			]);
		} catch ( RequestException $e) {
			if ($e->hasResponse()) {
				echo Psr7\str($e->getResponse());
				throw new GeotRequestException($e->getResponse());
			}
		}

		$this->validateResponse( $res );
		return $this->cleanResponse( $res );
	}


	/**
	 * Set some default options for the class
	 * @param $args
	 */
	private function set_defaults( $args ) {

		$this->opts = [
			'cache_mode'        => false, // cache mode turned on by default
			'debug_mode'        => false, // similar to disable sessions but also invalidates cookies
			'bots_country'      => '', // a default country to return if a bot is detected
			'cookie_name'       => 'geot_cookie' // cookie_name to store country iso code
		];

		foreach ($args as $key => $value )
			if ( isset( $this->opts[$key] ) )
				$this->opts[$key] = $value;
	}

	/**
	 * Return debug data set in query vars
	 */
	private function debugData() {
		if( isset( $_GET['geot_state'] ) ) {
			$state = new stdClass;
			$state->name = esc_attr( $_GET['geot_state'] );
			$state->isoCode = isset( $_GET['geot_state_code'] ) ? esc_attr( $_GET['geot_state_code'] ) : '';
		}

		$this->user_data = array(
			'country' => ( $_GET['geot_debug'] ),
			'city'    => isset( $_GET['geot_city'] ) ? filter_var( $_GET['geot_city'], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '',
			'zip'     => isset( $_GET['geot_zip'] ) ? filter_var( $_GET['geot_zip'], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '',
			'state'   => isset( $state ) ? $state : '',
		);

		return $this->user_data;
	}


	/**
	 * Init empty array of user data
	 */
	private function initUserData() {
		$this->user_data =  [
			'continent' => '',
			'country' => '',
			'state'   => '',
			'city'    => '',
		];
	}

	/**
	 * Add new values or update in user data
	 * @param $key
	 * @param $value
	 *
	 * @return mixed
	 */
	private function setUserData( $key, $value ) {
		$this->user_data[$key] = $value;
		return $this->user_data;
	}

	/**
	 * Check returned response
	 *
	 * @param $res
	 *
	 * @throws AddressNotFoundException
	 * @throws InvalidIPException
	 * @throws InvalidLicenseException
	 * @throws OutofCreditsException
	 */
	private function validateResponse( $res ) {
		$code = $res->getStatusCode();
		switch ($code) {
			case '404':
				throw new AddressNotFoundException((string)$res->getBody());
			case '500':
				throw new InvalidIPException((string)$res->getBody());
			case '401':
				throw new InvalidLicenseException((string)$res->getBody());
			case '403':
				throw new OutofCreditsException((string)$res->getBody());
			case '200':
			default:
				break;
		}
	}

	/**
	 * For now it just convert json data to object
	 * and create GeotRecord class
	 * @param $res
	 *
	 * @return GeotRecord
	 */
	private function cleanResponse( $res ) {
		$response = json_decode((string)$res->getBody());
		return new GeotRecord( $response );
	}

	/**
	 * Helper function that let users check if license is valid
	 * @param $license
	 *
	 * @return array|mixed|\Psr\Http\Message\ResponseInterface
	 */
	public static function checkLicense( $license ) {
		$response = self::client()->request('GET','check-license', [ 'query' => [ 'license' => $license ] ] );

		if( $response->getStatusCode() != '200')
			return ['error' => 'Something wrong happened'];

		$response = (string)$response->getBody();
		return $response;
	}

	/**
	 * Helper function that get cities for given country
	 *
	 * @param $iso_code
	 *
	 * @return array|mixed|\Psr\Http\Message\ResponseInterface
	 *
	 */
	public static function getCities( $iso_code ) {
		$response = self::client()->request('GET','cities', [ 'query' => [ 'iso_code' => $iso_code ] ] );

		if( $response->getStatusCode() != '200')
			return ['error' => 'Something wrong happened'];

		$response = (string)$response->getBody();
		return $response;
	}

	/**
	 * Create a client instance
	 * @return Client
	 */
	private static function client() {
		return new Client(
			[
				'base_uri' => env('GEOT_ENDPOINT','https://geotargetingwp.com/api/v1/'),
				'http_errors' => false,
				'headers' => [
					'Content-Type' => 'application/json'
				]
			]
		);
	}

}