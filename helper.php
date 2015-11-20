<?php
/**
 * Jive Plugin: allows interaction with a Jive-n intranet social server
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author     Ronan Viel <ronan.viel@orange.com>
 */

Class helper_plugin_jive extends DokuWiki_Plugin {
	
	public function getMethods() {
		$result = array();
		$result[] = array(
				'name' => 'jiveLastErrorMsg',
				'desc' => 'returns last error message string.',
				'params' => NULL,
				'return' => array('errorMsg' => 'string')
		);
		$result[] = array(
				'name' => 'jiveGetServerConf',
				'desc' => 'returns an array with the API URL and credentials to access it on success
	 					or NULL on error a with message set in jiveErrMsg',
				'params' => NULL,
				'return' => array('jiveServer' => 'array')
		);
		$result[] = array(
				'name' => 'jiveRequestAPI',
				'desc' => 'returns JSON data from API on success or FALSE on error with a message set in jiveErrMsg.',
				'params' => array('method' => 'string',
								'service' => 'string',
								'data' => 'string'),
				'return' => array('json' => 'string')
		);
		$result[] = array(
				'name' => 'jiveGetDiscussionGroup',
				'desc' => 'returns a JSON encoded string containing \'placeID\' and \'resources\'.\'html\' 
							or NULL on error',
				'params' => array('json' => 'string'),
				'return' => array('placeID' => 'string')
		);
		return $result;
	}
	

	private $jiveErrMsg = NULL;
	
	/**
	 * Get the last error message from method of this class
	 *
	 * @return Last error message string or NULL if not set.
	 */
	public function jiveLastErrorMsg() {
		return $this->jiveErrMsg;
	}
	
	
	/**
	 * Get the Jive server configuration.
	 *
	 * @return An array with the API URL and credentials to access it on success
	 * 			or NULL on error a with message set in jiveErrMsg.
	 */
	public function jiveGetServerConf() {
	
		$jiveServer = array("apiUrl" => NULL, "credentials" => NULL);
		
		// Get and check the server URL
		if (($jiveServer["apiUrl"] = $this->getConf( 'jiveServerAPIURL' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find jiveServerAPIURL in configuration';
			return NULL;
		}
		if (! substr_compare( $jiveServer["apiUrl"], '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that jiveServerAPIURL is not set in configuration';
			return NULL;
		}
		// check that the server URL is for HTTP protocol
		if (preg_match('/^https?:\/\//i', $jiveServer["apiUrl"]) != 1) {
			$this->jiveErrMsg = 'Invalid jiveServerAPIURL in configuration (should start with http:// or https://)';
			return NULL;
		}
	
		// Get and check the user and password
		if (($user = $this->getConf( 'jiveServerUser' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find "jiveServerUser" in configuration';
			return NULL;
		}
		if (! substr_compare( $user, '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that "jiveServerUser" is not set';
			return NULL;
		}
		if (($pass = $this->getConf( 'jiveServerPassword' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find "jiveServerPassword" in configuration';
			return NULL;
		}
		if (! substr_compare( $pass, '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that "jiveServerPassword" is not set';
			return FALSE;
		}
		$jiveServer["credentials"] = $user . ":" . $pass;
		
		return $jiveServer;
	}
	
	
	/**
	 * Send a request to the Jive server
	 *
	 * @param 	A string containing the HTTP method to use,
	 * 			a string containing the API service to use - this must start with a '/', and
	 * 			a string containing the JSON data to add to the request.
	 * @return JSON data on success or FALSE on error with a message set in jiveErrMsg.
	 */
	public function jiveRequestAPI($method, $svc, $data) {
			
		if ($jiveServer = $this->jiveGetServerConf() === NULL) return FALSE;
		
		if ($svc === NULL || $svc == '') {
			$this->jiveErrMsg = 'requestJiveAPI() error: missing service';
			return FALSE;
		}
		
		$curl = curl_init();
		curl_reset($curl);
	
		$curlOptions = array(
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_MAXREDIRS => 3,
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_SSL_VERIFYPEER => FALSE,
				//CURLOPT_HEADER => TRUE,
				CURLOPT_URL => $jiveServer["apiUrl"].$svc
				);
	
		switch ($method) {
			case 'GET' :
				$curlOptions[CURLOPT_HTTPGET] = TRUE;
				break;
			case 'POST' :
				if ($data === NULL || $data == '') {
					$this->jiveErrMsg = 'requestJiveAPI() error: POST method requires data';
					return FALSE;
				} else {
					$curlOptions[CURLOPT_CUSTOMREQUEST] = "POST";
					$curlOptions[CURLOPT_HTTPHEADER] = 
						array('Content-type: application/json', 'Content-length: '.strlen($data));
					$curlOptions[CURLOPT_POSTFIELDS] = $data;
				}
				break;
			default: $this->jiveErrMsg = 'requestJiveAPI() Error: method not implemented';
		}
		
		// Options and parameters for basic authentication
		$curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
		$curlOptions[CURLOPT_USERPWD] = $this->jiveUserPwd;
			
		global $conf;
		// Options and parameters for proxy tunnelling
		if (isset($conf['proxy']['host']) && ($conf['proxy']['host'] != '') ) {
			$curlOptions[CURLOPT_PROXY] = $conf['proxy']['host'];
			$option[CURLOPT_HTTPPROXYTUNNEL] = TRUE;
			if (isset($conf['proxy']['port']) && ($conf['proxy']['port'] != '') )
				$curlOptions[CURLOPT_PROXYPORT] = $conf['proxy']['port'];
			if (isset($conf['proxy']['user']) && ($conf['proxy']['user'] != '') ) {
				$curlOptions[CURLOPT_PROXYUSERPWD] = $conf['proxy']['user'].':'.$conf['proxy']['pass'];
				$curlOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
			}
			if (isset($conf['proxy']['ssl']) && ($conf['proxy']['ssl'] == 1) ) {
				$this->jiveErrMsg = 'getJiveData() does not support SSL proxy';
				curl_close($curl);
				return FALSE;
			}
		}
	
		if (curl_setopt_array($curl, $curlOptions) === FALSE) {
			$this->jiveErrMsg = 'Cannot set option(s) for cURL (Error '.curl_errno($curl)
			.': '.curl_error($curl).')';
			curl_close($curl);
			return FALSE;
		}
	
		$connectionTentatives = 2;	// Retry 1 time on operation timeout
		do {
			$result = curl_exec($curl);
	
			if ($result === FALSE) {
				if (curl_errno($curl) == 'CURLE_OPERATION_TIMEDOUT') {
					$connectionTentatives -= 1;
				} else {
					$this->jiveErrMsg = 'cURL cannot open URL '.$url.'(Error '.curl_errno($curl)
					.': '.curl_error($curl).')';
					if (isset($curlOptions[CURLOPT_PROXY]))
						$this->jiveErrMsg .= ' using proxy '.$curlOptions[CURLOPT_PROXY].':'
											.$curlOptions[CURLOPT_PROXYPORT];
					curl_close($curl);
					return FALSE;
				}
			} else $connectionTentatives = 0;
		} while($connectionTentatives != 0);
			
		if ($result == '') {
			$this->jiveErrMsg = 'Unknown error. Is the URL correct?';
			curl_close($curl);
			return FALSE;
		}
		curl_close($curl);
	
		// Strip the JSON security string
		// see https://developers.jivesoftware.com/api/v3/cloud/rest/index.html#security
		$response = preg_replace('/^throw.*;\s*/', '', $result);
	
		return $response;
	}
	
	
	/**
	 * Get the Jive Discussion Group 'placeID' for this DokuWiki installation
	 *
	 * @param String with JSON formatted answer to the API call "create group"
	 * @return A string with the 'placeID' or NULL on error with a message set in jiveErrMsg
	 */
	public function jiveGetDiscussionGroup($json) {
				
		$pluginInfo = $this->getInfo();
		if (!defined ('JIVEPLUGIN_DATA') && $pluginInfo['date'] != '0000-00-00') //see /inc/plugin.php @ line 44
			define('JIVEPLUGIN_DATA',DOKU_PLUGIN.$pluginInfo['base'].'/placeID');
		
		if ($json === NULL)
			if (file_exists(JIVEPLUGIN_DATA)) {
				if (($content = file_get_contents(JIVEPLUGIN_DATA)) === FALSE) {
					$this->jiveErrMsg = 'Cannot read file "'.JIVEPLUGIN_DATA.'"';
					return NULL;
				}
				return $content;
			} else {
				$this->jiveErrMsg = 'Cannot find file "'.JIVEPLUGIN_DATA.'"';
				return NULL;
			}
		
		// $json is not NULL, so we must extract the placeID, store it to file and return it
		$jiveInfo = json_decode($json, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			$this->jiveErrMsg = 'JSON error: '.json_last_error_msg();
			return NULL;
		}
		if (!isset($jiveInfo['placeID']))
			// look for 'error', search for same name existing group and get its placeID
			if (isset($jiveInfo['error'])) {
				if (isset($jiveInfo['error']['status']) && $jiveInfo['error']['status'] == 409) {
					// error status = 409 indicates that group name already exists
					global $conf;
					if ( ($data = $this->jiveRequestAPI('GET',
														'/places?filter=search('.$conf['title'].')',
														NULL)) === FALSE)
						return NULL;
					$info = json_decode($data, TRUE);
					if (isset($info['list'][0]['placeID'])) {
						$jiveInfo['placeID'] = $info['list'][0]['placeID'];
					} else {
						$this->jiveErrMsg = 'Cannot find placeID for Jive group with same name';
						return;
					}
				} else {
					$this->jiveErrMsg = 'Unknown error from Jive server';
					return NULL;
				}
			} else {
				$this->jiveErrMsg = 'Cannot understand JSON data';
				return NULL;
			}
		
		$nb = file_put_contents(JIVEPLUGIN_DATA, $jiveInfo['placeID']);
		if ($nb == 0 || $nb === FALSE) {
			$this->jiveErrMsg = 'Error writing placeID to file "'.JIVEPLUGIN_DATA.'"';
			return NULL;
		}
		return $jiveInfo['placeID'];
		
	}
	
}
