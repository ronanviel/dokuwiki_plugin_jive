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
		$this->Lexer->addSpecialPattern('\{\{jive>[^}]*?\}\}',$mode,'plugin_jive');
	}
	 
	   
	/**
	 * Handler to prepare matched data for the rendering process.
	 */
	function handle($match, $state, $pos, &$handler){
		
		$match = substr($match, 7, -2); //Strip '{{jive>' and '}}'
		if (($pos = strpos($match, '?')) === FALSE) {
			$cmd = $match;
			$arg = NULL;
		} 
		else {
			$cmd = substr($match, 0, $pos-strlen($match));
			$arg = substr($match, $pos+1);
		}
			
		switch ($cmd) {
			case 'discussion' :
				return $this->jiveDiscussion();

			case 'events' :
				msg("'events' is not implemented yet");
				return array(FALSE, NULL);
						
			case 'init' :
				return $this->initJive($arg);
							
			case 'ping' :
				return $this->jivePing();
							
			default:
				msg('Invalid command for Jive plugin: '.$cmd);
				return array(FALSE, NULL);
		}
		
	}
	
	
	/**
	 * Initialize Jive entities or files on Jive server and/or DokuWiki server
	 */
	private function initJive($arg) {
		if ($arg === null || $arg == '') {
			msg('Missing argument "group" or "discussion" for init command.', -1);
			return array(FALSE, NULL);
		}
		
		switch ($arg) {
			case 'discussion' :
				return $this->resetJiveDiscussion();
			case 'group' :
				return $this->createJiveGroup();
			default:
				msg('Unknown argument "'.$arg.'" for init command. Must be "group" or "discussion"', -1);
				return array(FALSE, NULL);
		}
		
	}
	
	
	/**
	 *  Reset the discussion metadata
	 */
	 private function resetJiveDiscussion() {
	 	
	 	//TODO Search for a discussion with the page name in the Jive group
	 	
	 	$meta = NULL;
	 	
	 	msg('Discussion resetted for this page');
	 	return array($meta, 'You should now delete this Jive plugin statement in this page source.');
	 }
	
	
	/**
	 * Create a new Jive group to hold discussions about wiki pages
	 * 
	 */
	private function createJiveGroup() {
	
		if (($jive = $this->loadHelper('jive')) === NULL) {
			msg('Cannot load helper for jive plugin.', -1);
			return array(FALSE, NULL);
		}
	
		if ($jive->initJiveServer() === FALSE) {
			msg('Failed to contact the Jive Server: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		}
		
		if (($placeID = $jive->getJiveGroup(NULL)) !== NULL)
			if (($data = $jive->getJiveData('/places/'.$placeID)) !== FALSE) {
				$info = json_decode($data, TRUE);
				if (isset($info['placeID']) && strcmp($info['placeID'],$placeID) == 0) {
					msg("Jive group (placeID=".$placeID.") already exists - nothing to do.");
					return array(FALSE, NULL);
				}
			}

		global $conf;
		if (($displayName = strtr(utf8_deaccent($conf['title'])," ", "-")) === NULL) {
			msg('error converting $conf[\'title\'].', -1);
			return array(FALSE, NULL);
		}
		
		// Create JSON data for group creation request
		if (($json = json_encode(array(
						"type" => "group",
						"name" => $conf['title'],
						"displayName" => $displayName,
						"description" => sprintf($this->getLang('jiveGroupDescription'), $conf['title'])." - ".DOKU_URL,
						"groupType" => "OPEN"))) === FALSE) {
			msg('error encoding group creation post to JSON', -1);
			return array(FALSE, NULL);
		}
		
		// Call API to create group
		if (($resp = $jive->postJiveData('/places', $json)) === FALSE) {
			msg('Failed to create group: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		}
		
		// Get the placeID for the group created
		if (($placeID = $jive->getJiveGroup($resp)) === NULL) {
			msg('Failed to get group ID: '.$jive->jiveLastErrorMsg()."See JSON data below", -1);
			return array(FALSE, "JSON data returned:<br>".$resp);
		}
			
			
		msg("Jive group (placeID=".$placeID.") created on Jive server and stored in Wiki configuration.", 1);
		return array(FALSE, 'You should now delete this Jive plugin statement in this page source.');
	}
	
	
	/**
	 * Return the link to the Jive server discussion about the current wiki page
	 * 
	 *  @return A string with the HTML to render
	 *
	 */
	private function jiveDiscussion() {
	
		global $ID;
		$meta = p_get_metadata(cleanID($ID), 'relation jive_plugin');
				
		if ($meta === NULL || !isset($meta['discussion_html']) || ($html = $meta['discussion_html']) == '') {
  			$data = sprintf($this->getLang('createJiveDiscussion'), '/doku.php?id='.$ID.'&do=jive_create_discussion');		
		}
		else {
			// Get information about the discussion
			if (($jive = $this->loadHelper('jive')) === NULL) {
				msg('Cannot load helper for jive plugin.', -1);
				return array(FALSE, NULL);
			}
		
			if ($jive->initJiveServer() === FALSE) {
				msg('Failed to contact the Jive Server: '.$jive->jiveLastErrorMsg(), -1);
				return array(FALSE, NULL);
			}
			
			//TODO Get information on the discussion (# of msg, last msg date & creator, print last msg)
			
			$data = sprintf($this->getLang('linkToJiveDiscussion'), $html);
		}
		return array(FALSE, $data);
	}

	
	/**
	 * Ping the Jive server.
	 * 
	 * @return A string with the Jive server version and the API version 
	 */
	private function jivePing() {
		
		if (($jive = $this->loadHelper('jive')) === NULL) {
			msg('Cannot load helper for jive plugin.', -1);
			return array(FALSE, NULL);
		}
		
		if (($data = $jive->initJiveServer()) === FALSE) {
			msg('Ping failed with error: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		} 
				
		$jiveInfo = json_decode($data, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			msg('JSON error: '.json_last_error_msg(),-1);
			return array(FALSE, NULL);
		}
		
		if (isset($jiveInfo['jiveCoreVersions']))
			foreach ($jiveInfo['jiveCoreVersions'] as $elem)
				if ($elem['version'] == 3)
					$jiveAPIVersion = 'and API v3.'.$elem['revision'];
		
		msg('Ping OK on '.$jive->getJiveServerURL().' running Jive server v'
				.$jiveInfo['jiveVersion'].$jiveAPIVersion);
		return array(FALSE, NULL);
	}
		
	
	/**
	 * Handle the actual output creation.
	 * 
	 * @param $data $data[0] is metadata, $data[1] is data to show on page
	 */
	function render($mode, &$renderer, $data) {
		if ($mode == 'xhtml') {
			if ($data[1] !== NULL) {
				$renderer->doc .= '<div id="jive_plugin">';
				$renderer->doc .= '<div class="title">'.$this->getLang('discussionTitle').'</div>';
				$renderer->doc .= '<p>'.$data[1].'</p>';
				$renderer->doc .= '</div>';
			}
			return TRUE;
		}
		
		if ($mode == 'metadata') {
			if ($data[0] !== FALSE) {
				$renderer->persistent['relation']['jive_plugin'] = $data[0];
				$renderer->meta['relation']['jive_plugin'] = $data[0];
			}
							
			return TRUE;
		}
		return FALSE;
	}
	
}

?>

