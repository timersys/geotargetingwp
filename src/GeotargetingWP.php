<?php namespace GeotWP;
use GeotWP\Exception\AddressNotFoundException;
use GeotWP\Exception\GeotException;
use GeotWP\Exception\GeotRequestException;
use GeotWP\Exception\InvalidIPException;
use GeotWP\Exception\InvalidLicenseException;
use GeotWP\Exception\OutofCreditsException;
use GeotWP\Record\GeotRecord;
use GeotWP\Record\RecordConverter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use IP2Location\Database;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use MaxMind\Db\Reader;
use stdClass;

class GeotargetingWP{

	/**
	 * @var Client
	 */
	private static $client;

	private $ip;
	private $cache_key;

	private $license;
	private $api_secret;

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
	public function __construct( $acces_token = "", $args = array() ) {

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
	 * @throws InvalidLicenseException
	 */
	public function getData( $ip = "", $force ){

		if( ! empty( $ip ) )
			$this->ip = $ip;

		if( empty( $this->license ) )
			throw new InvalidLicenseException(json_encode(['error'=>'License is missing']));
		$this->cache_key = md5( $this->ip . $ip );


		if( ! empty ( $this->user_data[$this->cache_key] ) )
			return $this->user_data[$this->cache_key];

		$this->initUserData();

		// Start sessions if needed
		if( is_session_started() === FALSE && $this->opts['cache_mode'] && ! ( isset($_GET['page']) && 'geot-debug-data' == $_GET['page'] ) )
			session_start();

		// Easy debug
		if( isset( $_GET['geot_debug'] ) )
			return $this->debugData();

		// If user set cookie and not in debug mode. If we pass ip we are forcing to use ip instead of cookies. Eg in dropdown widget
		if(  ! $this->opts['debug_mode']  &&  ! empty( $_COOKIE[$this->opts['cookie_name']] ) && ! $force )
			return $this->setUserData('country' , 'iso_code', $_COOKIE[$this->opts['cookie_name']] );

		// If we already calculated on session return (if we are not calling by IP & if cache mode (sessions) is turned on)
		if( $this->opts['cache_mode'] && !empty ( $_SESSION['geot_data'] ) )
			return $this->user_data[$this->cache_key] = new GeotRecord(unserialize( $_SESSION['geot_data'] ) );


		// check for crawlers
		$CD = new CrawlerDetect();
		if( $CD->isCrawler() )
			return $this->setUserData('country' , 'iso_code', !empty($this->opts['bots_country'])? $this->opts['bots_country'] :'US');

		// WP Engine ?
		if( getenv( 'HTTP_GEOIP_COUNTRY_CODE' ) !== false ){
			return $this->wpengine();
		}

		// maxmind ?
		if( isset($this->opts['maxmind'] ) && $this->opts['maxmind'] ){
			return $this->maxmind();
		}
		// ip2location ?
		if( isset($this->opts['ip2location'] ) && $this->opts['ip2location'] ){
			return $this->ip2location();
		}

		// time to call api
		try{
			$request_params = $this->generateRequestParams();
			$res = self::client()->get( self::api_url() . 'data', $request_params);
		} catch ( RequestException $e) {
			if ($e->hasResponse()) {
				throw new GeotRequestException($e->getResponse());
			}
		}
		$this->validateResponse( $res );
		return $this->cleanResponse( $res );
	}


	/**
	 * Set some default options for the class
	 * Most of these params are passed on class construction because
	 * we don't have access to wordpress filters or similar functions here
	 * as we want to keep GeotWP class separate from WP
	 * @param $args
	 */
	private function set_defaults( $args ) {

		$this->opts = [
			'cache_mode'        => false, // cache mode turned on by default
			'debug_mode'        => false, // similar to disable sessions but also invalidates cookies
			'bots_country'      => '', // a default country to return if a bot is detected
			'api_secret'        => '', // a default country to return if a bot is detected
			'cookie_name'       => 'geot_country', // cookie_name to store country iso code
			'maxmind'           => 0, // check if maxmind is enabled
			'maxmind_db'        => '', // path to db
			'ip2location'       => 0, // check if ip2location is enabled
			'ip2location_db'    => '', // path to db
			'ip2location_method'=> '', // wheter we use io disk or memory for lookups


		];
		if( !empty($args) ) {
			foreach ( $args as $key => $value ) {
				if ( isset( $this->opts[ $key ] ) ) {
					$this->opts[ $key ] = $value;
				}
			}
		}
	}

	/**
	 * Return debug data set in query vars
	 */
	private function debugData() {

		$state = new stdClass;
		$state->names = isset( $_GET['geot_state'] ) ? [filter_var($_GET['geot_state'],FILTER_SANITIZE_FULL_SPECIAL_CHARS)] : '';
		$state->iso_code = isset( $_GET['geot_state_code'] ) ? filter_var($_GET['geot_state_code'],FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

		$country = new stdClass;

		$country->names  =  [ filter_var($_GET['geot_debug'],FILTER_SANITIZE_FULL_SPECIAL_CHARS)];
		$continent = new stdClass;

		$continent->names  =  isset($_GET['geot_continent']) ? [ filter_var($_GET['geot_continent'],FILTER_SANITIZE_FULL_SPECIAL_CHARS)] : '';
		$country->iso_code  = isset($_GET['geot_debug_iso']) ? filter_var($_GET['geot_debug_iso'],FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
		$city = new stdClass;

		$city->names  = isset($_GET['geot_city']) ? [filter_var($_GET['geot_city'],FILTER_SANITIZE_FULL_SPECIAL_CHARS)] : '';
		$city->zip  = isset($_GET['geot_zip']) ? [filter_var($_GET['geot_zip'],FILTER_SANITIZE_FULL_SPECIAL_CHARS)] : '';

		$this->user_data[$this->cache_key] = new GeotRecord((object)[
			'country' => $country,
			'city'    => $city,
			'state'   => $state,
			'continent'   => $continent,
		]);

		return $this->user_data[$this->cache_key];
	}


	/**
	 * Init empty Object of user data
	 */
	private function initUserData() {
		$this->user_data[$this->cache_key] =  (object) [
			'continent' => new StdClass(),
			'country' =>  new StdClass(),
			'state'   =>  new StdClass(),
			'city'    =>  new StdClass(),
		];
	}

	/**
	 * Add new values or update in user data
	 *
	 * @param $key
	 * @param $property
	 * @param $value
	 *
	 * @return mixed
	 */
	private function setUserData( $key, $property, $value ) {
		$this->initUserData();
		$this->user_data[$this->cache_key]->$key->$property = $value;
		$this->user_data[$this->cache_key] = new GeotRecord($this->user_data[$this->cache_key]);
		return $this->user_data[$this->cache_key];
	}

	/**
	 * Check returned response
	 *
	 * @param $res
	 *
	 * @throws AddressNotFoundException
	 * @throws GeotException
	 * @throws InvalidIPException
	 * @throws InvalidLicenseException
	 * @throws OutofCreditsException
	 */
	private function validateResponse( $res ) {
		if( null === $res )
			throw new GeotException(json_encode(['error' => 'Null reponse from guzzle']));
		
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
				break;
			default:
				throw new GeotException((string)$res->getBody());
				break;
		}
	}

	/**
	 * Save user data, save to session and create record
	 * and create GeotRecord class
	 * @param $res
	 *
	 * @return GeotRecord
	 */
	private function cleanResponse( $res ) {
		// maxmind
		$response_string = $res;
		// this is coming from API
		if( method_exists($res,'getBody') )
			$response_string = (string)$res->getBody();

		$response  = json_decode($response_string);

		if ( $this->opts['cache_mode'] )
			$_SESSION['geot_data'] = serialize( $response );
		$this->user_data[$this->cache_key]  = new GeotRecord( $response );

		return $this->user_data[$this->cache_key];
	}

	/**
	 * Helper function that let users check if license is valid
	 * @param $license
	 *
	 * @return array|mixed|\Psr\Http\Message\ResponseInterface
	 */
	public static function checkLicense( $license ) {
		$response = self::client()->get( self::api_url() .'check-license', [ 'query' => [ 'license' => $license ] ] );

		if( $response->getStatusCode() != '200')
			return json_encode(['error' => 'Something wrong happened' . strip_tags((string) $response->getBody())]);

		$response = (string)$response->getBody();
		return $response;
	}

	/**
	 * Helper function that let users check if license is valid
	 * @param $license
	 *
	 * @return array|mixed|\Psr\Http\Message\ResponseInterface
	 */
	public static function checkSubscription( $license ) {
		$response = self::client()->get( self::api_url() .'check-subscription', [ 'query' => [ 'license' => $license ] ] );

		if( $response->getStatusCode() != '200')
			return json_encode(['error' => 'Something wrong happened'. strip_tags((string) $response->getBody())]);

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
		$response = self::client()->get( self::api_url() .'cities', [ 'query' => [ 'iso_code' => $iso_code ] ] );

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
				'base_uri' => self::api_url(),
				'http_errors' => false,
				'headers' => [
					'Content-Type' => 'application/json'
				]
			]
		);
	}

	/**
	 * Return API URL
	 * @return mixed
	 */
	public static function api_url() {
		return env('GEOT_ENDPOINT','https://geotargetingwp.com/api/v1/');
	}

	/**
	 * Generates signature
	 * @return array
	 */
	private function generateRequestParams() {
		$request_params = [
			'query' => [
				'ip'        => $this->ip,
				'license'   => $this->license,
			],
			'headers' => [
				'Geot-Nonce'  => urlencode(base64_encode(makeRandomString())),
				'Geot-Origin' => $_SERVER['HTTP_HOST']
			]
		];

		$base_string = json_encode($request_params);
		$request_params['query']['signature'] = urlencode(hash_hmac('sha256',$base_string, $this->opts['api_secret']));
		return $request_params;
	}

	/**
	 * Use maxmind local db
	 * @return GeotRecord
	 * @throws AddressNotFoundException
	 * @throws GeotException
	 */
	private function maxmind() {

		$reader = new Reader($this->opts['maxmind_db']);
		try{
			$record = $reader->get($this->ip);
			if( empty($record) )
				throw new AddressNotFoundException('Ip Address not found');
			$reader->close();
			return $this->cleanResponse(RecordConverter::maxmindRecord($record));
		} catch( AddressNotFoundException $e) {
			throw new AddressNotFoundException((string)$e->getMessage());
		} catch( \Exception $e) {
			throw new GeotException($e->getMessage());
		}


	}

	/**
	 * Use ip2location database
	 * @return GeotRecord
	 * @throws GeotException
	 */
	private function ip2location() {
		$db = new Database($this->opts['ip2location_db'], $this->opts['ip2location_method']);
		try{
			$record = $db->lookup($this->ip, Database::ALL);
			return $this->cleanResponse(RecordConverter::ip2locationRecord($record));
		} catch( \Exception $e) {
			throw new GeotException($e->getMessage());
		}
	}

	/**
	 * Use WpEngine variables (enterprise plans only)
	 * @return GeotRecord
	 * @throws GeotException
	 */
	private function wpengine() {
		try{
			return $this->cleanResponse(RecordConverter::wpEngine());
		} catch( \Exception $e) {
			throw new GeotException($e->getMessage());
		}
	}
}