<?php

/**
 * This file is part of an ADDON for use with Black Cat CMS Core.
 * This ADDON is released under the GNU GPL.
 * Additional license terms can be seen in the info.php of this module.
 *
 * @module          wysiwyg
 * @author          Ryan Djurovich
 * @author          LEPTON Project
 * @copyright       2004-2010 WebsiteBaker Project
 * @copyright       2010-2011 LEPTON Project 
 * @link            http://www.LEPTON-cms.org
 * @license         http://www.gnu.org/licenses/gpl.html
 * @license_terms   please see info.php of this module
 *
 *
 */

// include class.secure.php to protect this file and the whole CMS!
if (defined('CAT_PATH')) {	
	include(CAT_PATH.'/framework/class.secure.php'); 
} else {
	$root = "../";
	$level = 1;
	while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
		$root .= "../";
		$level += 1;
	}
	if (file_exists($root.'/framework/class.secure.php')) { 
		include($root.'/framework/class.secure.php'); 
	} else {
		trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
	}
}
// end include class.secure.php

 
 
class c_pathfinder
{

	/**
	 *	Holds the version of the class(-source).
	 *
	 */
	protected $version= "0.1.1";
	
	/**
	 *
	 *
	 */
	public $lookup = array(
		'&gt;'	=> ">",
		'&lt;'	=> "<",
		'&quot;' => "\"",
		'&amp;'	 => "&"
	);

	/**
	 *
	 *
	 */
	private $last_section_id = 0;
	
	/**
	 *
	 *
	 */
	private $paths = Array(
		'css' => "",
		'style' => ""
	);
	
	/**
	 *
	 *
	 */
	public $files = array(
		'css' => Array (
			'/editor.css',
			'/css/editor.css'
		),
		'style' => Array (
			'/editor.styles.js',
			'/js/editor.styles.js'
		)
	);
	
	/**
	 *
	 *
	 */
	public $errors = Array();
	
	/**
	 *	Private value that holds the currend page_id
	 *
	 */
	private $__page_id = 0;
	
	/**
	 *	Private DB-Connector reference
	 *
	 */
	private $__db = 0;
	
	/**
	 *	constructor of the class
	 *
	 *	@param	mixed	Any MySQL-Connector
	 *	@param	array	Optional an assoc. array to overwrite the internal one, if you
	 *					want to search for other files.
	 *
	 */
	public function __construct (&$db_ref = NULL, $files=array()) {

		$this->__db = $db_ref;
		$this->__page_id = $_GET['page_id'];
		$this->__get_last_section();
		
		foreach($files as $key=>$value) $this->files[$key]=$value;
	}
	
	/**
	 *	@param	string	Any HTML-Source, pass by reference
	 *
	 */
	public function reverse_htmlentities(&$html_source) {
	
		$html_source = str_replace(
			array_keys( $this->lookup ), 
			array_values( $this->lookup ), 
			$html_source
		);
    	
    }

	/**
	 *	Returns the private value of the last section id
	 *
	 *	@see_also	__get_last_section
	 *
	 */	
	public function get_last_section_id() {
		return $this->last_section_id;
	}

	/**
	 *
	 *
	 */
	private function __get_last_section($type='wysiwyg') {
		global $id_list;
		
		if (!isset($id_list) OR !is_array($id_list)) {
			$q  = "SELECT `section_id` from `".CAT_TABLE_PREFIX."sections` where `page_id`=".$this->__page_id;
			$q .= " AND `module`='".$type."' order by position desc limit 1";
		
			$r = $this->__db->query($q);
		
			if ($r && ( $r->numRows() > 0 ) ) {
				$temp = $r->fetchRow( MYSQL_ASSOC );
				$this->last_section_id = $temp['section_id'];
			}
		} else {
			$this->last_section_id = end($id_list);
		}
	}
	
	/**
	 *
	 *
	 */
	public function get_paths() {
	
		$query = "SELECT `template` from `".CAT_TABLE_PREFIX."pages` where `page_id`='".$this->__page_id."'";
		$result = $this->__db->query( $query );
		$temp = $result->fetchRow( MYSQL_ASSOC );
		$base_folder = ($temp['template'] == "") ? DEFAULT_TEMPLATE : $temp['template'];
		
		foreach($this->files as $key=>$p) {
			foreach($p as $temp_path) {
				$path = CAT_PATH."/templates/".$base_folder.$temp_path;
				if (true == file_exists($path) ){
					$this->paths[$key] = str_replace(CAT_PATH, CAT_URL, $path);
					break;
				}
			}
		}
		
		return $this->paths;
	}
}
?>