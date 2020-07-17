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

	private $api_args;

	private $license;
	private $api_secret;

	/**
	 * Constructor
	 *
	 * @param $acces_token
	 * @param $api_secret
	 *
	 * @throws InvalidLicenseException
	 */
	public function __construct( $acces_token = "", $api_secret = "" ) {
		$this->license = $acces_token;
		$this->api_secret = $api_secret;
	}

	/**
	 * Main function that return User data
	 *
	 * @param array $api_args
	 *
	 * @return mixed
	 * @throws AddressNotFoundException
	 * @throws GeotException
	 * @throws GeotRequestException
	 * @throws InvalidIPException
	 * @throws InvalidLicenseException
	 * @throws OutofCreditsException
	 */
	public function getData( $api_args = [] ){

		$this->api_args = $api_args;

		if( empty( $this->api_args ) || ! isset( $this->api_args['geolocation'] ) ) {
			throw new GeotRequestException(json_encode(['error' => 'No IP or coordinates set for user']));
		}
		// time to call api
		try{
			$request_params = $this->generateRequestParams();
			$res = self::client()->get( self::api_url() . 'data', $request_params);

		} catch ( RequestException $e) {
			if ($e->hasResponse()) {
				throw new GeotRequestException($e->getResponse());
			}
		} catch ( \Exception $e) {
			throw new GeotRequestException($e->getMessage());
		}
		$this->validateResponse( $res );
		return $this->cleanResponse( $res );
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

		$response_string = $res;
		// this is coming from API
		if( method_exists($res,'getBody') )
			$response_string = (string)$res->getBody();

		$response  = json_decode($response_string);

		return $response;
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
		$response = self::client()->get( self::api_url() .'check-subscription', [ 'query' => [ 'license' => $license,  'Geot-Origin' => $_SERVER['HTTP_HOST'] ] ] );

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
				'idn_conversion' => false,
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
		$signature_params = [
			'query' => [
				'ip'        => $this->api_args['data']['ip'], // added for signature verification
				'license'	=> $this->license,
			],
			'headers' => [
				'Geot-Nonce'  => urlencode(base64_encode(makeRandomString())),
				'Geot-Origin' => $_SERVER['HTTP_HOST']
			]
		];
		$request_params = array_merge($signature_params , [
			'query' => [
				'type'		=> $this->api_args['geolocation'], // by_ip|by_html5
				'data'		=> $this->api_args['data'], // [ 'ip' => ''] || [ 'lat' => '', 'lng' => '' ]
				'license'	=> $this->license,
				'ip'        => $this->api_args['data']['ip'], // added for signature verification
			]
		] );

		$base_string = json_encode($signature_params);
		$request_params['query']['signature'] = urlencode(hash_hmac('sha256',$base_string, $this->api_secret ));
		return $request_params;
	}
}