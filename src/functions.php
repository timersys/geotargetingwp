<?php
namespace GeotWP;

/**
 * Grab user IP from different possible sources
 * @return string
 */
function getUserIP() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '1.1.1.1';
	// cloudflare
	$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $ip;
	// reblaze
	$ip = isset( $_SERVER['X-Real-IP'] ) ? $_SERVER['X-Real-IP'] : $ip;
	// gtranslate
	$ip = isset( $_SERVER['HTTP_X_REAL_IP'] ) ? $_SERVER['HTTP_X_REAL_IP'] : $ip;
	// Sucuri
	$ip = isset( $_SERVER['HTTP_X_SUCURI_CLIENTIP'] ) ? $_SERVER['HTTP_X_SUCURI_CLIENTIP'] : $ip;
	// Ezoic
	$ip = isset( $_SERVER['X-FORWARDED-FOR'] ) ? $_SERVER['X-FORWARDED-FOR'] : $ip;
	// akamai
	$ip = isset( $_SERVER['True-Client-IP'] ) ? $_SERVER['True-Client-IP'] : $ip;
	// Clouways
	$ip = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $ip;
	// get varnish first ip
	$ip = strstr( $ip, ',') === false ? $ip : strstr( $ip, ',', true);

	return $ip;
}

/**
 * Check if session is running
 * @return bool
 */
function is_session_started() {
	if ( php_sapi_name() !== 'cli' ) {
		if ( version_compare(phpversion(), '5.4.0', '>=') ) {
			return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
		} else {
			return session_id() === '' ? FALSE : TRUE;
		}
	}
	return FALSE;
}

/**
 * Gets the value of an environment variable.
 *
 * @param  string  $key
 * @param  mixed   $default
 * @return mixed
 */
function env($key, $default = null) {
	$value = getenv($key);

	if ($value === false)
		return $default;

	switch (strtolower($value)) {
		case 'true':
		case '(true)':
			return true;
		case 'false':
		case '(false)':
			return false;
		case 'empty':
		case '(empty)':
			return '';
		case 'null':
		case '(null)':
			return;
	}

	return $value;

}

/**
 * Debug function
 * @return string
 */
function generateCallTrace() {
	$e = new \Exception();
	$trace = explode("\n", $e->getTraceAsString());
	// reverse array to make steps line up chronologically
	$trace = array_reverse($trace);
	array_shift($trace); // remove {main}
	array_pop($trace); // remove call to this method
	$length = count($trace);
	$result = array();

	for ($i = 0; $i < $length; $i++)
	{
		$result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
	}

	return "\t" . implode("\n\t", $result);
}

function makeRandomString($bits = 256) {
	$bytes = ceil($bits / 8);
	$return = '';
	for ($i = 0; $i < $bytes; $i++) {
		$return .= chr(mt_rand(0, 255));
	}
	return $return;
}