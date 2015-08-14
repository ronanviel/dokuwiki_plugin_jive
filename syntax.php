<?php
/**
 * Plugin Jive: allows interaction with a Jive-n intranet social server
*
* @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
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
	 * Where to sort in?
	 */
	function getSort(){
		return 329;
	}
	 
	 
	/**
	 * Connect lookup pattern to lexer.
	 */
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('{{jive>}}',$mode,'plugin_jive');
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
				break;
		}
		return array();
	}
	 
	/**
	 * Handle the actual output creation.
	 */
	function render($mode, &$renderer, $data) {
		if($mode == 'xhtml'){
			$renderer->doc .= '<div class="jive">Discussion Jive</div>';
			return true;
		}
		return false;
	}
}

?>

