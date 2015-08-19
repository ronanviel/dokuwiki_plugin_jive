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
							return $this->showJiveDiscussion();

						case 'events' :
							return 'Liste des événements';
							
						default:
							return 'Invalid expression';
				}
				break;
		}
		return array();
	}
	
	
	/**
	 * Create the link to the discussion  
	 */
	private function showJiveDiscussion() {
		// get configuration variables to access Jive server
		if (($serverURL = $this->getConf('jiveServerURL')) == NULL)
			return 'Cannot find "jiveServerURL" in configuration';
		if (! substr_compare($serverURL, '!!', 0, 2, TRUE))
			return 'Seems that "jiveServerURL" is not set';
		if (($user = $this->getConf('jiveServerUser')) == NULL)
			return 'Cannot find "jiveServerUser" in configuration';
		if (! substr_compare($user, '!!', 0, 2, TRUE))
			return 'Seems that "jiveServerUser" is not set';
		if (($pass = $this->getConf('jiveServerPassword')) == NULL)
			return 'Cannot find "jiveServerPassword" in configuration';
		if (! substr_compare($pass, '!!', 0, 2, TRUE))
			return 'Seems that "jiveServerPassword" is not set';
		
		// check that the server url start with "http"
		if (substr_compare($serverURL, 'http', 0, 4, TRUE))
			return 'Invalid Jive Server URL (should start with http:// or https://)';
			
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $user.":".$pass);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		//curl_setopt($curl, CURLOPT_PROXY, 'proxy.rd.francetelecom.fr');
		//curl_setopt($curl, CURLOPT_PROXYPORT, 80);
		
		// build URL
		$url = $serverURL.'/api/core/v3/messages/64658';
		curl_setopt($curl, CURLOPT_URL, $url);
		
		$result = curl_exec($curl);
		
		if ($result === FALSE)
			$data = 'Error: '.curl_error($curl);
		elseif ($result == '')
			$data = 'Unknown error. Is the URL correct?';
		else
			$data = $result;
		
		curl_close($curl);
		
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

