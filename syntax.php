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
				return 'Not implemented yet';
						
			case 'create' :
				return $this->createJiveGroup();
			case 'init' :
				return $this->initJive($arg);
							
			case 'ping' :
				return $this->jivePing();
							
			case 'cdis' :	//FIXME remove before delivery
				return $this->createJiveDiscussion();
							
			default:
				return 'Invalid command for Jive plugin: '.$cmd;
		}
		
	}
	
	
	/**
	 * Create a new Jive discussion for the current wiki page
	 */
	private function createJiveDiscussion() {
	
		global $ID;
		global $conf;
	
		if (($jive = $this->loadHelper('jive')) === NULL)
			return 'Cannot load helper for jive plugin.';
		
		if ($jive->initJiveServer() === FALSE)
			return 'Failed to contact the Jive Server: '.$jive->jiveLastErrorMsg();
		
		if (($placeID = $jive->getJiveGroup(NULL)) === NULL)
			return 'Failed to get Jive group: '.$jive->jiveLastErrorMsg();
		
		// Get the title of the current page
		if (($title = p_get_metadata(cleanID($ID), 'title')) === NULL)
			return 'Failed to get page title from metadata <h2>';
		
		// create the JSON request data
		if (($json = json_encode(array(
				"content" => array(
						"type" => "text/html",
						"text" => sprintf($this->getLang('jiveDiscussionContent'), 
											DOKU_URL.$ID, $title)),
				"subject" => sprintf($this->getLang('jiveDiscussionSubject'), $title),
				"type" => "discussion",
				"tags" => array($conf['title'])
				))) === FALSE)
				return 'Error encoding discussion creation post to JSON';
				
		if (($data = $jive->postJiveData('/places/'.$placeID.'/contents',$json)) === FALSE)
			return 'Failed to create discussion for that page: '.$jive->jiveLastErrorMsg();
			
		// Get and store useful URLs in metadata of the page
		$info = json_decode($data, TRUE);
		if ($info === NULL && json_last_error() !== JSON_ERROR_NONE)
			return 'Failed to decode JSON returned on create Discussion. JSON error: '.json_last_error_msg();
		
		if (isset($info['error']))
			return 'Failed to create Discussion. JSON data returned: '.$info;

		if (!isset($info['contentID']))
			return 'Failed to get contentID for Discussion created. JSON data returned: '.$info;
		
		$met = array('relation' => array('jivePlugin_contentID' => $info['contentID'],
											'jivePlugin_html' => $info['resources']['html']['ref']));
		if (p_set_metadata(cleanID($ID), $met) === FALSE)
			return 'Failed to store metadata. Warning: multiple discussions on the same page maybe created.';
			
		return "C'est bon !"; //FIXME
	}
	
	
	/**
	 * Initialize Jive entities or files on Jive server and/or DokuWiki server
	 */
	private function initJive($arg) {
		if ($arg === null || $arg == '')
			return 'Missing argument "group" or "discussion" for init command.';
		
		switch ($arg) {
			case 'discussion' :
				return $this->resetJiveDiscussion();
			case 'group' :
				return $this->createJiveGroup();
			default:
				return 'Unknown argument "'.$arg.'" for init command. Must be "group" or "discussion"';
		}
		
	}
	
	
	/**
	 *  Reset the discussion metadata to NULL
	 */
	 private function resetJiveDiscussion() {
	 	
	 	// TODO
	 	
	 	return 'Discussion resetted for this page';
	 }
	
	
	/**
	 * Create a new Jive group to hold discussions about wiki pages
	 * 
	 */
	private function createJiveGroup() {
	
		if (($jive = $this->loadHelper('jive')) === NULL)
			return 'Cannot load helper for jive plugin.';
	
		if ($jive->initJiveServer() === FALSE)
			return 'Failed to contact the Jive Server: '.$jive->jiveLastErrorMsg();
		
		if (($placeID = $jive->getJiveGroup(NULL)) !== NULL)
			if (($data = $jive->getJiveData('/places/'.$placeID)) !== FALSE) {
				$info = json_decode($data, TRUE);
				if (isset($info['placeID']) && strcmp($info['placeID'],$placeID) == 0)
					return "Jive group (placeID=".$placeID.") already exists - nothing to do.";
			}

		global $conf;
		if (($displayName = strtr(utf8_deaccent($conf['title'])," ", "-")) === NULL)
			return 'error converting $conf[\'title\'].';
		
		// Create JSON data for group creation request
		if (($json = json_encode(array(
						"type" => "group",
						"name" => $conf['title'],
						"displayName" => $displayName,
						"description" => sprintf($this->getLang('jiveGroupDescription'), $conf['title'])." - ".DOKU_URL,
						"groupType" => "OPEN"))) === FALSE)
			return 'error encoding group creation post to JSON';
		
		// Call API to create group
		if (($resp = $jive->postJiveData('/places', $json)) === FALSE)
			return 'Failed to create group: '.$jive->jiveLastErrorMsg();
		
		// Get the placeID for the group created
		if (($placeID = $jive->getJiveGroup($resp)) === NULL)
			return 'Failed to get group ID: '.$jive->jiveLastErrorMsg()."<br>JSON data: ".$resp;
			
		return "Jive group (placeID=".$placeID.") created on Jive server and stored in Wiki configuration.";
	}
	
	
	/**
	 * Return the link to the Jive server discussion about the current wiki page
	 * 
	 *  @return A string with the HTML to render
	 *
	 */
	private function jiveDiscussion() {
	
		global $ID;
		$html = p_get_metadata(cleanID($ID), 'relation jivePlugin_html');
		if ($html === NULL) {
  			$data = sprintf($this->getLang('createJiveDiscussion'), '/doku.php?id='.$ID.'&do=jiveCreateDiscussion');		
		}
		else {
			// Get information about the discussion
			if (($jive = $this->loadHelper('jive')) === NULL)
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
		
		if (($jive = $this->loadHelper('jive')) === NULL)
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
		
		return 'Ping OK on '.$jive->getJiveServerURL().' running Jive server v'
				.$jiveInfo['jiveVersion'].$jiveAPIVersion;
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

