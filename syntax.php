<?php
/**
 * Jive Plugin: allows interaction with a Jive-n intranet social server
*
* @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
* @author     Ronan Viel <ronan.viel@orange.com>
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
							return $this->jiveDiscussion();

						case 'events' :
							return 'Not implemented yet';
						
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
	 * Return the link to the Jive server discussion about the current wiki page
	 * 
	 *  @return A string with the HTML to render
	 *
	 */
	private function jiveDiscussion() {
	
		global $ID;
		$html = p_get_metadata($ID, 'relation plugin_jive_html');
		if ($html === NULL) {
  			$data = sprintf($this->getLang('createJiveDiscussion'), '/doku.php?id='.$ID.'&do=jiveCreateDiscussion');		
		}
		else {
			// Get information about the discussion
			if (($jive = loadHelper('jive', TRUE)) === NULL)
				return 'Cannot load helper for jive plugin.';
		
			if ($jive->initJiveServer() === FALSE)
				return 'Failed to contact the Jive Server: '.$jive->jiveLastErrorMsg();
			//TODO Get information on the discussion (# of msg, last msg date & creator, print last msg)
			
			$data = sprintf($this->getLang('linkToJiveDiscussion'), $html);
		}
		return $data;
	}

	
	/**
	 * Ping the Jive server.
	 * 
	 * @return A string with the Jive server version and the API version 
	 */
	private function jivePing() {
		
		if (($jive = $this->loadHelper('jive', TRUE)) === NULL)
			return 'Cannot load helper for jive plugin.';
		
		if (($data = $jive->initJiveServer()) === FALSE)
			return 'Ping failed with error: '.$jive->jiveLastErrorMsg(); 
				
		$jiveInfo = json_decode($data, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			$this->jiveErrMsg = 'JSON error: '.json_last_error_msg();
			return FALSE;
		}
		if (isset($jiveInfo['jiveCoreVersions']))
			foreach ($jiveInfo['jiveCoreVersions'] as $elem)
				if ($elem['version'] == 3)
					$jiveAPIVersion = 'and API v3.'.$elem['revision'];
		
		return 'Ping OK on '.$jive->getJiveServerURL.' running Jive server v'.$jiveInfo['jiveVersion'].$jiveAPIVersion;
	}
		
	
	/**
	 * Handle the actual output creation.
	 */
	function render($mode, &$renderer, $data) {
		if($mode == 'xhtml'){
			$renderer->doc .= '<h2>'.$this->getLang('discussionTitle').'</h2><p>'.$data.'</p>';
			return true;
		}
		return false;
	}
	
}

?>

