<?php
/**
 * Jive Plugin: allows interaction with a Jive-n intranet social server
*
* @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
* @author     Ronan Viel <ronan.viel@orange.com>
*/
 
// must be run from within DokuWiki
if (!defined('DOKU_INC')) die();


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
		if (($pos = strpos($match, '&')) === FALSE) {
			$cmd = $match;
			$arg = NULL;
		} else {
			$cmd = substr($match, 0, $pos-strlen($match));
			$arg = substr($match, $pos+1);
		}
			
		switch ($cmd) {
			case 'discussion' :
				return $this->jiveDiscussion();

			case 'document' :
				return $this->jiveDocument($arg);
				
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
	
		if (($placeID = $jive->jiveGetDiscussionGroup(NULL)) !== NULL)
			if (($data = $jive->jiveRequestAPI('GET', '/places/'.$placeID, NULL)) !== FALSE) {
				$info = json_decode($data, TRUE);
				//FIXME stcmp() call seems useless - check for error in JSON
				if (!isset($info['Error'])) {
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
		if (($resp = $jive->jiveRequestAPI('POST', '/places', $json)) === FALSE) {
			msg('Failed to create group: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		}
		
		// Get the placeID for the group created
		if (($placeID = $jive->jiveGetDiscussionGroup($resp)) === NULL) {
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
	
		global $conf;
		$extern = '';
		if (isset($conf['target']['extern']))
			$extern = $conf['target']['extern'];
		
		global $ID;
		$meta = p_get_metadata(cleanID($ID), 'relation jive_plugin');
		$data = '<div class="discussion__title">'.$this->getLang('discussionTitle').'</div>';
				
		if ($meta === NULL || !isset($meta['discussion_html']) || ($html = $meta['discussion_html']) == '') {
			// No discussion yet - show a link to initiate it
			$data .= sprintf($this->getLang('createJiveDiscussion'), 
							DOKU_URL.'/doku.php?id='.$ID.'&do=jive_create_discussion',
							$extern);		
			return array(FALSE, $data);
		}
		
		// Show the link to the discussion
		if (($jive = $this->loadHelper('jive')) === NULL) {
			msg('Cannot load helper for jive plugin.', -1);
			return array(FALSE, NULL);
		}
		
		if (!isset($meta['discussion_contentID']) || ($contentID = $meta['discussion_contentID']) == '') {
			msg('Failed to get the contentID for discussion. Please reset it with the syntax "{{jive>init?discussion}}"', -1);
			return array(FALSE, NULL);
		}
			
		
		if (($resp = $jive->jiveRequestAPI('GET', '/contents/'.$contentID, NULL)) === FALSE) {
			msg('Failed to connect to server. Error: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		}
		
		$info = json_decode($resp, TRUE);
		if ($info === NULL && json_last_error() !== JSON_ERROR_NONE) {
			msg('JSON error: '.json_last_error_msg(),-1);
			return array(FALSE, NULL);
		}
		
		// Check for error
		if (isset($info["error"])) {
			$data .= sprintf($this->getLang('createJiveDiscussion'),
					DOKU_URL.'/doku.php?id='.$ID.'&do=jive_create_discussion',
					$extern);
			return array(FALSE, $data);
		}
		
		$stats = '';
		$lastMsgHeader = '';
		$lastMsg = '';
		// Show information about the discussion
		if (isset($info['followerCount']))
			$stats .= $this->getLang('JiveDiscussionFollower').$info['followerCount'].', ';	
		if (isset($info['likeCount']))
			$stats .= $this->getLang('JiveDiscussionLike').$info['likeCount'].', ';
		if (isset($info['replyCount'])) {
			$stats .= $this->getLang('JiveDiscussionReply').$info['replyCount'];
				
			if ($info['replyCount'] > 0) {
				$flags = '?startIndex='.($info['replyCount']-1).'&count=1';
				$flags .= '&hierarchical=false';
						
				if (($resp = $jive->jiveRequestAPI('GET',
												'/messages/contents/'.$contentID.$flags,
												NULL)) === FALSE) {
					msg('Failed to connect to server. Error: '.$jive->jiveLastErrorMsg(), -1);
					return array(FALSE, NULL);
				}
				
				$info = json_decode($resp, TRUE);
				if ($info === NULL && json_last_error() !== JSON_ERROR_NONE) {
					msg('JSON error: '.json_last_error_msg(),-1);
					return array(FALSE, NULL);
				}
							
				$lastMsgHeader .= $this->getLang('jiveDiscussionLastMsg');
				if (isset($info['list'][0]['author']['displayName']))
					$lastMsgHeader .= $this->getLang('jiveDiscussionLastMsg2')
							.$info['list'][0]['author']['displayName'];
				if (isset($info['list'][0]['updated'])) {
					setlocale(LC_TIME, $conf['lang']);
					$time = strtotime($info['list'][0]['updated']);
					$lastMsgHeader .= strftime($this->getLang('jiveDiscussionLastMsg3'), $time);
				}
				if (isset($info['list'][0]['content']['text']))
					$lastMsg .= $info['list'][0]['content']['text'];
			}
		}
		$data .= '<div class="discussion__stats">'.$stats.'</div>';
		$data .= '<div class="discussion__lastmsgheader">'.$lastMsgHeader.'</div>';
		$data .= '<div class="discussion__lastmsg">'.$lastMsg.'</div>';
		
		$data .= '<p><b>'.sprintf($this->getLang('linkToJiveDiscussion'), $html, $extern).'</b></p>';
		return array(FALSE, $data);
	}

	
	/**
	 * Return the link to the Jive server discussion about the current wiki page
	 *
	 *  @return A string with the HTML to render
	 *
	 */
	private function jiveDocument($arg) {
	
		global $conf;
		$extern = '';
		if (isset($conf['target']['extern']))
			$extern = $conf['target']['extern'];
	
		if (($jive = $this->loadHelper('jive')) === NULL) {
			msg('Cannot load helper for jive plugin.', -1);
			return array(FALSE, NULL);
		}
	
		if (($resp = $jive->jiveRequestAPI('GET', '/contents/'.$contentID, NULL)) === FALSE) {
			msg('Failed to connect to server. Error: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		}
	
		$info = json_decode($resp, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			msg('JSON error: '.json_last_error_msg(),-1);
			return array(FALSE, NULL);
		}
			
		// Check for error
		if (isset($info["error"])) {
			$data = sprintf($this->getLang('createJiveDiscussion'),
					DOKU_URL.'/doku.php?id='.$ID.'&do=jive_create_discussion',
					$extern);
			return array(FALSE, $data);
		}
	
		$data = '<div class="jiveDocument__stats">';
		// Show the document
		
	
		$data .= '</div><p><b>'.sprintf($this->getLang('linkToJiveDiscussion'), $html, $extern).'</b></p>';
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
		
		if (($data = $jive->jiveRequestAPI('GET', '/people?count=1', NULL)) === FALSE) {
			msg('Ping failed with error: '.$jive->jiveLastErrorMsg(), -1);
			return array(FALSE, NULL);
		} 
				
		$jiveInfo = json_decode($data, TRUE);
		if ($jiveInfo === NULL && json_last_error() !== JSON_ERROR_NONE) {
			msg('JSON error: '.json_last_error_msg(),-1);
			return array(FALSE, NULL);
		}
		
		if (isset($jiveInfo['Error'])) {
			msg('Ping OK with error: '.$jiveInfo['Error']['message']);
		} else {
			$jiveServer = $jive->jiveGetServerConf();
			msg('Ping OK on '.$jiveServer["apiUrl"]);
		}
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
				$renderer->doc .= '<div class="jiveplugin__section">';
				$renderer->doc .= $data[1];
				$renderer->doc .= '</div>';		// jiveplugin_section
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


