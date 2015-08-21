<?php
/**
 * Jive Plugin: allows interaction with a Jive-n intranet social server
*
* @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
* @author     Ronan Viel <ronan.viel@orange.com>
*/
 
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
*/
class syntax_plugin_jive extends DokuWiki_Syntax_Plugin {
	 
	
	private $jiveErrMsg = NULL; 
	private $jiveUserPwd = NULL;
	private $jiveAPIURI = NULL;
	private $jiveVersion = NULL;
	 
	/**
	 * Get the type of syntax this plugin defines.
	 */
	function getType(){
		return 'substition';
	}

	
	/**
	 * What type of XHTML do we create?
	 */
	function getPType() {
		return 'block';
	}
	
	
	/**
	 * Where to sort in?
	 */
	function getSort(){
		return 105;
	}
	 
	 
	/**
	 * Connect lookup pattern to lexer.
	 */
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('\{\{jive>[^}]*\}\}',$mode,'plugin_jive');
	}
	 
	   
	/**
	 * Handler to prepare matched data for the rendering process.
	 */
	function handle($match, $state, $pos, &$handler){
		switch ($state) {
			case DOKU_LEXER_ENTER :
				break;
			case DOKU_LEXER_MATCHED :
				break;
			case DOKU_LEXER_UNMATCHED :
				break;
			case DOKU_LEXER_EXIT :
				break;
			case DOKU_LEXER_SPECIAL :
				if ($match = substr($match, 7, -2))
					switch($match) {
						case 'discussion' :
							return $this->jiveDiscussion();

						case 'events' :
							return 'Liste des événements';
						
						case 'ping' :
							return $this->jivePing();
						default:
							return 'Invalid expression';
				}
				break;
		}
		return array();
	}
	
	
	/**
	 * TODO
	 * Create the link to the discussion
	 *
	 */
	private function jiveDiscussion() {
	
		if (($this->jiveAPIURI === NULL) || ($this->jiveUserPwd === NULL))
			if ($this->initJiveServer() === NULL)
				return 'Ping failed with error: '.$this->jiveErrMsg;
	
			if (($data = $this->getJiveData($this->jiveAPIURI.'/messages/64658')) === FALSE)
				return $this->jiveErrMsg;
	
			return $data;
	}
	
	
	/**
	 * Ping the Jive server.
	 * 
	 * @return A string with the Jive server version and the API version 
	 */
	private function jivePing() {
		
		if (($data = $this->initJiveServer()) === FALSE)
			return 'Ping failed with error: '.$this->jiveErrMsg; 
				
		$jiveInfo = json_decode($data, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			$this->jiveErrMsg = 'JSON error: '.json_last_error_msg();
			return FALSE;
		}
		if (isset($jiveInfo['jiveCoreVersions']))
			foreach ($jiveInfo['jiveCoreVersions'] as $elem)
				if ($elem['version'] == 3)
					$jiveAPIVersion = 'and API v3.'.$elem['revision'];
		
		return 'Ping OK on '.$this->jiveAPIURI.' with Jive server v'.$jiveInfo['jiveVersion'].$jiveAPIVersion;
	}
	
	
	/**
	 * Initialize the Jive server variables from the plugin configuration and a call 
	 * to the version API, so we check availability of API v3 which has been the target
	 * version for this plugin.
	 * 
	 * @return An array with serverVersion and APIVersion on success, or FALSE on error
	 *         a with message set in jiveErrMsg.
	 */
	private function initJiveServer() {
		
		// Get and check the server URL
		if (($jiveServerURL = $this->getConf ( 'jiveServerURL' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find "jiveServerURL" in configuration';
			return FALSE;
		}
		if (! substr_compare ( $jiveServerURL, '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that "jiveServerURL" is not set';
			return FALSE;
		}
		// check that the server url start with "http"
		if (substr_compare ( $jiveServerURL, 'http', 0, 4, TRUE )) {
			$this->jiveErrMsg = 'Invalid Jive Server URL (should start with http:// or https://)';
			return FALSE;
		}		
		
		// Get and check the user and password
		if (($user = $this->getConf ( 'jiveServerUser' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find "jiveServerUser" in configuration';
			return FALSE;
		}
		if (! substr_compare ( $user, '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that "jiveServerUser" is not set';
			return FALSE;
		}
		if (($pass = $this->getConf ( 'jiveServerPassword' )) === NULL) {
			$this->jiveErrMsg = 'Cannot find "jiveServerPassword" in configuration';
			return FALSE;
		}
		if (! substr_compare ( $pass, '!!', 0, 2, TRUE )) {
			$this->jiveErrMsg = 'Seems that "jiveServerPassword" is not set';
			return FALSE;
		}
		$this->jiveUserPwd = $user . ":" . $pass;
		
		//Check availability of Core API v3 and set the API URI
		if (($data = $this->getJiveData($jiveServerURL.'/api/version')) === FALSE)
			return FALSE;
		$jiveInfo = json_decode($data, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			$this->jiveErrMsg = 'JSON error: '.json_last_error_msg();
			return FALSE;
		}
		//if (array_key_exists('jiveCoreVersions',$jiveInfo))
		if (isset($jiveInfo['jiveCoreVersions'])) {
			foreach ($jiveInfo['jiveCoreVersions'] as $elem)
				if ($elem['version'] == 3 && isset($elem['uri']))
					// Append 'version.uri' to the API URI 
					$this->jiveAPIURI = $jiveServerURL.$elem['uri'];
		}
		else {
			$this->jiveErrMsg = 'Cannot find any Core API version for this Jive server';
			return FALSE;
		}
		if ($this->jiveAPIURI === NULL) {
			$this->jiveErrMsg = 'Cannot find Core API v3 URI for this Jive server';
			return FALSE;
		}
					
		return $data;
	}
		
	/**
	 * Get data from the Jive server 
	 * 
	 * @return JSON data on success or FALSE on error with a message set in jiveErrMsg.  
	 */
	private function getJiveData($svc) {
					
		$curl = curl_init();
		if ($svc === NULL) {
			$this->jiveErrMsg = 'Internal plugin error: no service URL';
			return FALSE;
		}
		curl_setopt($curl, CURLOPT_URL, $svc);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		if ($this->jiveUserPwd === NULL) {
			$this->jiveErrMsg = 'Internal plugin error: jiveUserPwd unset';
			return FALSE;
		}
		curl_setopt($curl, CURLOPT_USERPWD, $this->jiveUserPwd);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
				
		$result = curl_exec($curl);
		
		// Check the result
		if ($result === FALSE) {
			$this->jiveErrMsg = 'Error: '.curl_error($curl);
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
	 * Handle the actual output creation.
	 */
	function render($mode, &$renderer, $data) {
		if($mode == 'xhtml'){
			$renderer->doc .= '<p>'.$data.'</p>';
			return true;
		}
		return false;
	}
	
}

?>

