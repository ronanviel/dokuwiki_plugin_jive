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
				'name' => 'initJiveServer',
				'desc' => 'returns JSON answer to API check call, or FALSE on error
							a with message set in jiveErrMsg.',
				'params' => NULL,
				'return' => array('result' => 'array')
		);
		$result[] = array(
				'name' => 'jiveLastErrorMsg',
				'desc' => 'returns last error message string.',
				'params' => NULL,
				'return' => array('errorMsg' => 'string')
		);
		$result[] = array(
				'name' => 'getJiveServerURL',
				'desc' => 'returns a string containing the Jive server URL or NULL if not set.',
				'params' => NULL,
				'return' => array('jiveServerURL' => 'string')
		);
		$result[] = array(
				'name' => 'getJiveData',
				'desc' => 'returns JSON data on success or FALSE on error with a message set in jiveErrMsg.',
				'params' => array('service' => 'string'),
				'return' => array('json' => 'string')
		);
		$result[] = array(
				'name' => 'getJiveGroup',
				'desc' => 'returns a JSON encoded string containing \'placeID\' and \'resources\'.\'html\' 
							or NULL on error',
				'params' => NULL,
				'return' => array('json' => 'string')
		);
		return $result;
	}
	

	private $jiveErrMsg = NULL;
	private $jiveUserPwd = NULL;
	private $jiveServerURL = NULL;
	private $jiveAPI = NULL;
	private $jiveVersion = NULL;
	
	/**
	 * Initialize the Jive server variables from the plugin configuration and a call
	 * to the version API, so we check availability of API v3 which has been the target
	 * version for this plugin.
	 *
	 * @return JSON-formatted string with all the information about the Jive API on the
	 * 			the server, or FALSE on error a with message set in jiveErrMsg.
	 */
	public function initJiveServer() {
	
	//	if (($this->jiveServerURL === NULL) || ($this->jiveUserPwd === NULL)) {
			// Get and check the server URL
			if (($this->jiveServerURL = $this->getConf( 'jiveServerURL' )) === NULL) {
				$this->jiveErrMsg = 'Cannot find "jiveServerURL" in configuration';
				return FALSE;
			}
			if (! substr_compare( $this->jiveServerURL, '!!', 0, 2, TRUE )) {
				$this->jiveErrMsg = 'Seems that "jiveServerURL" is not set';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
			// check that the server url start with "http"
			if (substr_compare( $this->jiveServerURL, 'http', 0, 4, TRUE )) {
				$this->jiveErrMsg = 'Invalid Jive Server URL (should start with http:// or https://)';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
	
			// Get and check the user and password
			if (($user = $this->getConf( 'jiveServerUser' )) === NULL) {
				$this->jiveErrMsg = 'Cannot find "jiveServerUser" in configuration';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
			if (! substr_compare( $user, '!!', 0, 2, TRUE )) {
				$this->jiveErrMsg = 'Seems that "jiveServerUser" is not set';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
			if (($pass = $this->getConf( 'jiveServerPassword' )) === NULL) {
				$this->jiveErrMsg = 'Cannot find "jiveServerPassword" in configuration';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
			if (! substr_compare( $pass, '!!', 0, 2, TRUE )) {
				$this->jiveErrMsg = 'Seems that "jiveServerPassword" is not set';
				$this->jiveServerURL = NULL;
				return FALSE;
			}
			$this->jiveUserPwd = $user . ":" . $pass;
	//	}
		
		//Check availability of Core API v3 and set the API URI
		if (($data = $this->getJiveData(NULL)) === FALSE) {
			$this->jiveServerURL = NULL;
			$this->jiveUserPwd = NULL;
			return FALSE;
		}
	//	if ($this->jiveAPI === NULL) {
			$jiveInfo = json_decode($data, TRUE);
			if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
				$this->jiveErrMsg = 'JSON error: '.json_last_error_msg();
				$this->jiveServerURL = NULL;
				$this->jiveUserPwd = NULL;
				return FALSE;
			}
			if (isset($jiveInfo['jiveCoreVersions'])) {
				foreach ($jiveInfo['jiveCoreVersions'] as $elem)
					if ($elem['version'] == 3 && isset($elem['uri']))
						// Append 'version.uri' to the API URI
						$this->jiveAPI = $elem['uri'];
			}
			else {
				$this->jiveErrMsg = 'Cannot find any Core API version for this Jive server';
				$this->jiveServerURL = NULL;
				$this->jiveUserPwd = NULL;
				return FALSE;
			}
			if ($this->jiveAPI === NULL) {
				$this->jiveErrMsg = 'Cannot find Core API v3 URI for this Jive server';
				$this->jiveServerURL = NULL;
				$this->jiveUserPwd = NULL;
				return FALSE;
			}
	//	}
			
		return $data;
	}
	
	
	/**
	 * Get the last error message from method of this class
	 * 
	 * @return Last error message string or NULL if not set.
	 */
	public function jiveLastErrorMsg() {
		return $this->jiveErrMsg;
	}
	
	
	/**
	 * Get the Jive server URL
	 *
	 * @return A string with the URL or NULL if not set.
	 */
	public function getJiveServerURL() {
		return $this->jiveServerURL;
	}
	
	
	/**
	 * Get data from the Jive server
	 * 
	 * @param A string containing the API service to use - this must start with a '/'. 
	 * @return JSON data on success or FALSE on error with a message set in jiveErrMsg.
	 */
	public function getJiveData($svc) {
			
		if ($svc === NULL) {
			$url = $this->jiveServerURL.'/api/version';
		}
		elseif ($this->jiveServerURL === NULL ||
				$this->jiveAPI === NULL ||
				$this->jiveUserPwd === NULL) {
			$this->jiveErrMsg = 'Internal plugin error: call initJiveServer() first!';
			return FALSE;
		}
		else {
			$url = $this->jiveServerURL.$this->jiveAPI.$svc;
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $this->jiveUserPwd);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	
		$result = curl_exec($curl);
	
		// Check the result
		if ($result === FALSE) {
			$this->jiveErrMsg = 'curl error "'.curl_error($curl).'" for URL ('.$url.')';
			curl_close($curl);
			return FALSE;
		}
		if ($result == '') {
			$this->jiveErrMsg = 'Unknown error. Is the URL correct?';
			curl_close($curl);
			return FALSE;
		}
		curl_close($curl);
	
		// Strip the JSON security string
		// see https://developers.jivesoftware.com/api/v3/cloud/rest/index.html#security
		$data = preg_replace('/^throw.*;\s*/', '', $result);
	
		return $data;
	}
	
	
	/**
	 * Post data on the Jive server
	 *
	 * @param A string containing the API service to use - this must start with a '/', and
	 * 			A string containing the JSON data to post.
	 * @return JSON data on success or FALSE on error with a message set in jiveErrMsg.
	 */
	public function postJiveData($svc, $json) {
			
		// Build the full URL
		if ($svc === NULL) {
			$this->jiveErrMsg = 'Internal plugin error: missing service on postJiveData()';
		}
		elseif ($this->jiveServerURL === NULL ||
				$this->jiveAPI === NULL ||
				$this->jiveUserPwd === NULL) {
			$this->jiveErrMsg = 'Internal plugin error: call initJiveServer() first!';
			return FALSE;
		}
		else {
			$url = $this->jiveServerURL.$this->jiveAPI.$svc;
		}
	
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_HTTPHEADER, 
					array('Content-type: application/json', 'Content-length: '.strlen($json)));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $this->jiveUserPwd);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
	
		$result = curl_exec($curl);
	
		// Check the result
		if ($result === FALSE) {
			$this->jiveErrMsg = 'curl error "'.curl_error($curl).'" for URL ('.$url.')';
			curl_close($curl);
			return FALSE;
		}
		if ($result == '') {
			$this->jiveErrMsg = 'Unknown error. Is the URL correct?';
			curl_close($curl);
			return FALSE;
		}
		curl_close($curl);
	
		// Strip the JSON security string
		// see https://developers.jivesoftware.com/api/v3/cloud/rest/index.html#security
		$data = preg_replace('/^throw.*;\s*/', '', $result);
	
		return $data;
	}
	
	
	/**
	 * Get the Jive Group 'placeID' for this DokuWiki installation
	 *
	 * @param String with JSON formatted answer to the API call "create group"
	 * @return A string with the 'placeID' or NULL on error with a message set in jiveErrMsg
	 */
	public function getJiveGroup($json) {
		global $DOKU_PLUGIN;
		if (!defined ('JIVEPLUGIN_PLACEID'))
			define('JIVEPLUGIN_PLACEID','JIVEPLUGIN_PLACEID');
		
		if ($json === NULL)
			if (file_exists($DOKU_PLUGIN.JIVEPLUGIN_PLACEID)) {
				if (($content = file_get_contents($DOKU_PLUGIN.JIVEPLUGIN_PLACEID)) === FALSE) {
					$this->jiveErrMsg = 'Cannot read JIVEPLUGIN_PLACEID file';
					return NULL;
				}
				return $content;
			}
			else {
				$this->jiveErrMsg = 'Cannot find JIVEPLUGIN_PLACEID file';
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
					if ($this->initJiveServer() === NULL)
						return NULL;	
					global $conf;
					if ( ($data = $this->getJiveData('/places?filter=search('.$conf['title'].')')) === FALSE)
						return NULL;
					$info = json_decode($data, TRUE);
					if (isset($info['list'][0]['placeID'])) {
						$jiveInfo['placeID'] = $info['list'][0]['placeID'];
					}
					else {
						$this->jiveErrMsg = 'Cannot find placeID for Jive group with same name';
						return;
					}
				}
				else {
					$this->jiveErrMsg = 'Unknown error from Jive server';
					return NULL;
				}
			}
			else {
				$this->jiveErrMsg = 'Cannot understand JSON data';
				return NULL;
			}
		
		$nb = file_put_contents(JIVEPLUGIN_PLACEID, $jiveInfo['placeID']);
		if ($nb == 0 || $nb === FALSE) {
			$this->jiveErrMsg = 'Error writing placeID to file';
			return NULL;
		}
		return $jiveInfo['placeID'];
		
	}
	
}