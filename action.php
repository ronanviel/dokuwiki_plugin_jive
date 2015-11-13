<?php
/**
 * Jive Plugin: allows interaction with a Jive-n intranet social server
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author     Ronan Viel <ronan.viel@orange.com>
 */

class action_plugin_jive extends DokuWiki_Action_Plugin {

	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess');
		$controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'check_cache_validity');
	}
	
	public function handle_act_preprocess(&$event, $param) {
		// All Jive plugin actions start with 'jive_'
		if (substr($event->data, 0, 5) != 'jive_') return;
		
		if ($key = substr($event->data, 5, 32))
			switch($key) {
				case 'create_discussion' :
					$event->preventDefault();
					if (($location = $this->createJiveDiscussion()) !== NULL)
						header("Location: ".$location);
					break;
				default:
					break;	// Unknown keyword - do nothing
			}
	}
	
	public function check_cache_validity(&$event, $param) {
		global $ID;
		
		$cache = &$event->data;
		if (!isset($cache->page))
			return;
		if ($cache->mode != 'i')
			return;
		
		$meta = p_get_metadata(cleanID($ID), 'relation jive_plugin');
		if ($meta !== NULL && isset($meta['discussion_html'])) {
			// There is a discussion on this page
			$event->preventDefault();
			$event->stopPropagation();
			$event->depends['purge'] = TRUE;
		}
		
	}
	
	
	/**
 	 * Create a new Jive discussion for the current wiki page
 	 * 
 	 * @return string URL to the discussion on the Jive server or NULL
 	 */
	public function createJiveDiscussion() {
		
		global $ID;
		global $conf;
		
		if (($jive = $this->loadHelper('jive')) === NULL) {
			msg('Cannot load helper for jive plugin.', -1);
			return NULL;
		}
		
		if ($jive->initJiveServer() === FALSE) {
			msg('Failed to contact the Jive server: '.$jive->jiveLastErrorMsg(), -1);
			return NULL;
		}
		
		if (($placeID = $jive->getJiveGroup(NULL)) === NULL) {
			msg('Failed to get Jive group: '.$jive->jiveLastErrorMsg(), -1);
			return NULL;
		}
		
		// Get the title of the current page
		$title = p_get_metadata(cleanID($ID), 'title', METADATA_DONT_RENDER);
		if ( $title === NULL || $title == '') {
			msg('Failed to get page title from metadata', -1);
			return NULL;
		}
		
		// create the JSON request data
		if (($json = json_encode(array(
				"content" => array(
						"type" => "text/html",
						"text" => sprintf($this->getLang('jiveDiscussionContent'),
								DOKU_URL.$ID, $title)),
				"subject" => sprintf($this->getLang('jiveDiscussionSubject'), $title),
				"type" => "discussion",
				"tags" => array($conf['title']))
			)) === FALSE) {
			msg('Error encoding discussion creation post to JSON', -1);
			return NULL;
		}
		
		if (($data = $jive->postJiveData('/places/'.$placeID.'/contents',$json)) === FALSE) {
			msg('Failed to create discussion for that page: '.$jive->jiveLastErrorMsg(), -1);
			return NULL;
		}
			
		// Get and store useful URLs in metadata of the page
		$info = json_decode($data, TRUE);
		if ($info === NULL && json_last_error() !== JSON_ERROR_NONE) {
			msg('Failed to decode JSON returned on create Discussion. JSON error: '.json_last_error_msg(), -1);
			return NULL;
		}
		
		if (isset($info['error'])) {
			msg('Failed to create Discussion. JSON data returned:<br>'.$info, -1);
			return NULL;
		}
		
		if (!isset($info['contentID'])) {
			msg('Failed to get contentID for Discussion created. JSON data returned:<br>'.$info, -1);
			return NULL;
		}
		
		$meta = array('relation' => 
						array('jive_plugin' => 
								array('discussion_contentID' => $info['contentID'],
									'discussion_html' => $info['resources']['html']['ref'])));
		if (p_set_metadata(cleanID($ID), $meta) === FALSE) {
			msg('Failed to store metadata. Warning: multiple discussions on the same page maybe created.', -1);
			return NULL;
		}
			
		return $info['resources']['html']['ref'];
		
	}
	

	
}
