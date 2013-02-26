<?php

/**
 * This file is part of LEPTON2 Core, released under the GNU GPL
 * Please see LICENSE and COPYING files in your package for details, specially for terms and warranties.
 * 
 * NOTICE:LEPTON CMS Package has several different licenses.
 * Please see the individual license in the header of each single file or info.php of modules and templates.
 *
 * @author			LEPTON2 Project
 * @copyright		2012, LEPTON2 Project
 * @link			http://lepton2.org
 * @license			http://www.gnu.org/licenses/gpl.html
 * @license_terms	please see LICENSE and COPYING files in your package
 *
 *
 */
 
// include class.secure.php to protect this file and the whole CMS!
if (defined('CAT_PATH')) {
	include(CAT_PATH.'/framework/class.secure.php');
} else {
	$oneback = "../";
	$root = $oneback;
	$level = 1;
	while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
		$root .= $oneback;
		$level += 1;
	}
	if (file_exists($root.'/framework/class.secure.php')) {
		include($root.'/framework/class.secure.php');
	} else {
		trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
	}
}
// end include class.secure.php

// =============== 
// ! Get page id   
// =============== 
if ( !isset($_GET['page_id']) || !is_numeric($_GET['page_id']) )
{
	header("Location: index.php");
	exit(0);
}
else
{
	$page_id	= $_GET['page_id'];
}

require_once(CAT_PATH.'/framework/class.admin.php');

$admin = new admin('Pages', 'pages_modify');

// ============= 
// ! Get perms   
// ============= 
if ( !$admin->get_page_permission($page_id,'admin') )
{
	$admin->print_error( 'You do not have permissions to modify this page' );
}

$sectionId = isset($_GET['wysiwyg']) ? htmlspecialchars($admin->get_get('wysiwyg')) : NULL;

// ==================== 
// ! Get page details   
// ==================== 
$results_array							= CAT_Pages::getInstance()->get_page_details($page_id);

// ========================================================= 
// ! Get display name of person who last modified the page   
// ========================================================= 
$user									= $admin->get_user_details( $results_array['modified_by'] );

// =========================================================================== 
// ! Create the controller, it is reusable and can render multiple templates 	
// =========================================================================== 
global $parser;
$data_dwoo = array();

// ============================ 
// ! Include page info script   
// ============================ 
$data_dwoo['PAGE_ID']					= $results_array['page_id'];
$data_dwoo['PAGE_TITLE']				= $results_array['page_title'];
$data_dwoo['MENU_TITLE']				= $results_array['menu_title'];
$data_dwoo['PAGE_LINK']					= $admin->page_link($results_array['link']);

$data_dwoo['MODIFIED_BY']				= $user['display_name'];
$data_dwoo['MODIFIED_BY_USERNAME']		= $user['username'];
$data_dwoo['MODIFIED_WHEN']				= ($results_array['modified_when'] != 0) ? $modified_ts = date(TIME_FORMAT.', '.DATE_FORMAT, $results_array['modified_when']) : false;

$data_dwoo['SECTION_BLOCKS']			= SECTION_BLOCKS;
$data_dwoo['SEC_ANCHOR']				= SEC_ANCHOR;
$data_dwoo['DATE_FORMAT']				= DATE_FORMAT;

$data_dwoo['CUR_TAB']                   = 'modify';
$data_dwoo['PAGE_HEADER']               = $admin->lang->translate('Modify page');

// ========================================================= 
// ! Work-out if we should show the "manage sections" link   
// ========================================================= 
$query_sections = $database->query('SELECT `section_id` FROM `'.CAT_TABLE_PREFIX.'sections` WHERE `page_id` = '.(int)$page_id.' AND `module` = "menu_link"');

$data_dwoo['MANAGE_SECTIONS']			= ( $query_sections->numRows() > 0 || MANAGE_SECTIONS != 'enabled' ) ? false : true;

// =========================================================================== 
// ! get template used for the displayed page (for displaying block details)   
// =========================================================================== 
$get_template		= $database->query("SELECT `template` from `" . CAT_TABLE_PREFIX . "pages` WHERE `page_id` = '$page_id' ");
$template_row		= $get_template->fetchRow( MYSQL_ASSOC );
$current_template	= ( $template_row['template'] != '' ) ? $template_row['template'] : DEFAULT_TEMPLATE;

require CAT_PATH . '/framework/CAT/Pages.php';
$pg = new CAT_Pages();


// ============================== 
// ! Get sections for this page   
// ============================== 
$module_permissions							= $_SESSION['MODULE_PERMISSIONS'];

require CAT_PATH.'/framework/CAT/Helper/Addons.php';
$addons = new CAT_Helper_Addons();
$data_dwoo['modules']				= $addons->get_addons( 1, 'module', 'page', $module_permissions );
// Remove menu_link from list
foreach ( $data_dwoo['modules'] as $index => $module )
{
	if ( $module['VALUE'] == 'menu_link' )
	{
		unset($data_dwoo['modules'][$index]);
	}
}

$query_sections = $database->query('SELECT * FROM `'.CAT_TABLE_PREFIX.'sections` WHERE `page_id` = '.intval($page_id).' ORDER BY position ASC');

if ( $query_sections->numRows() > 0 )
{
	$data_dwoo['blocks_counter']	= 0;
	while ( $section = $query_sections->fetchRow( MYSQL_ASSOC ) )
	{
		$module		= $section['module'];
		// ==================== 
		// ! Have permission?   
		// ==================== 
		if ( !is_numeric( array_search($module, $module_permissions) ) )
		{
			// =================================================== 
			// ! Include the modules editing script if it exists   
			// =================================================== 
			if ( file_exists(CAT_PATH.'/modules/'.$module.'/modify.php') )
			{
				// =========================================== 
				// ! output block name if blocks are enabled   
				// =========================================== 
				if ( SECTION_BLOCKS )
				{
					$section_id		= $section['section_id'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['template_blocks']		= $pg->get_template_blocks( $current_template, $section['block'] );
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['current_block_id']		= $pg->current_block['id'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['current_block_name']	= $pg->current_block['name'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['section_id']			= $section['section_id'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['module']				= $section['module'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['name']					= $section['name'];
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_day_from']			= $section['publ_start'] > 0 ? date('d', $section['publ_start'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_month_from']		= $section['publ_start'] > 0 ? date('m', $section['publ_start'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_year_from']		= $section['publ_start'] > 0 ? date('Y', $section['publ_start'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_hour_from']		= $section['publ_start'] > 0 ? date('H', $section['publ_start'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_minute_from']		= $section['publ_start'] > 0 ? date('i', $section['publ_start'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_day_to']			= $section['publ_start'] > 0 ? date('d', $section['publ_end'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_month_to']			= $section['publ_start'] > 0 ? date('m', $section['publ_end'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_year_to']			= $section['publ_start'] > 0 ? date('Y', $section['publ_end'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_hour_to']			= $section['publ_start'] > 0 ? date('H', $section['publ_end'] ) : '';
					$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['date_minute_to']		= $section['publ_start'] > 0 ? date('i', $section['publ_end'] ) : '';
					// ====================================================== 
					// ! Include the module and add it to the output buffer   
					// ====================================================== 
					ob_start();
						require(CAT_PATH.'/modules/'.$module.'/modify.php');
						$data_dwoo['blocks'][$data_dwoo['blocks_counter']]['content']			= ob_get_contents();
					ob_end_clean();

					$data_dwoo['blocks_counter']++;
				}
			}
		}
	}
}

// ==================== 
// ! Parse the site   
// ==================== 
$parser->output('backend_pages_modify.lte', $data_dwoo);

// ====================== 
// ! Print admin footer   
// ====================== 
$admin->print_footer();

?>