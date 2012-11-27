<?php

/**
 * This file is part of LEPTON Core, released under the GNU GPL
 * Please see LICENSE and COPYING files in your package for details, specially for terms and warranties.
 * 
 * NOTICE:LEPTON CMS Package has several different licenses.
 * Please see the individual license in the header of each single file or info.php of modules and templates.
 *
 * @author          LEPTON Project
 * @copyright       2012, LEPTON Project
 * @link            http://www.LEPTON-cms.org
 * @license         http://www.gnu.org/licenses/gpl.html
 * @license_terms   please see LICENSE and COPYING files in your package
 *
 *
 */
 
// include class.secure.php to protect this file and the whole CMS!
if (defined('WB_PATH')) {
	include(WB_PATH.'/framework/class.secure.php');
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

// Check if user uploaded a file
if ( !isset($_FILES['userfile']) )
{
	header("Location: index.php");
	exit(0);
}

// Setup admin object
require_once(WB_PATH.'/framework/class.admin.php');
$admin = new admin('Addons', 'languages_install');

// Include the WB functions file
require_once(WB_PATH.'/framework/functions.php');

// Create temp string
$temp_string	= '';
$salt			= "abchefghjkmnpqrstuvwxyz0123456789";
srand( (double)microtime()*1000000 );
$i	= 0;
while ($i <= 7)
{
	$num			= rand() % 33;
	$tmp			= substr($salt, $num, 1);
	$temp_string	= $temp_string . $tmp;
	$i++;
}

// Set temp vars
$temp_dir	= WB_PATH.'/temp/';
$temp_file	= $temp_dir . 'language'.$temp_string;

// Check if language dir is writable
if ( !is_writable(WB_PATH.'/languages/'))
{
	if ( file_exists($temp_file) )
	{
		unlink($temp_file);  // Remove temp file
	}
	$admin->print_error( 'Unable to write to the target directory' );
}

// Try to upload the file to the temp dir
if (!move_uploaded_file($_FILES['userfile']['tmp_name'], $temp_file))
{
	if (file_exists($temp_file) )
	{
		unlink($temp_file); // Remove temp file
	}
	$admin->print_error( 'Cannot upload file' );
}

// Check if uploaded file is a valid language file (no binary file etc.)
$content	= file_get_contents( $temp_file );
if (strpos($content, '<?php') === false) $admin->print_error( 'Invalid LEPTON language file. Please check the text file.' );

// Remove any vars with name "language_code"
unset( $language_code );

// Include precheck files for versionCompare routine
require(WB_PATH . '/framework/addon.precheck.inc.php');

// Read the temp file and look for a language code
require( $temp_file );
$new_language_version	= $language_version;

// Check if the file is valid
if ( !isset($language_code) )
{
	if ( file_exists($temp_file) )
	{
		unlink($temp_file); // Remove temp file
	}
	// Restore to correct language
	require( WB_PATH.'/languages/'.LANGUAGE.'.php' );
	$admin->print_error( 'Invalid LEPTON language file. Please check the text file.' );
}

// Set destination for language file
$language_file	= WB_PATH.'/languages/'.$language_code.'.php';
$action			= 'install';

// Move to new location
if ( file_exists($language_file) )
{
	require($language_file);
	if ( versionCompare($language_version, $new_language_version, '>=') )
	{
		// Restore to correct language
		require(WB_PATH . '/languages/' . LANGUAGE . '.php');
		$admin->print_error( 'Already installed' );
	}
	
	/**
	 *
	 *
	 */
	if(	(!isset($language_license))		||
		(!isset($language_code))		||
		(!isset($language_version))		||
		(!isset($language_guid))
		)
		{
			require(WB_PATH . '/languages/' . LANGUAGE . '.php');
			$admin->print_error( 'Language installation failed, one (or more) of the following variables is missing:<br />language_code<br />language_name<br />language_version<br />language_license' );
		}
				
	$action		='upgrade';
	unlink( $language_file );
}

rename($temp_file, $language_file);

// Chmod the file
change_mode($language_file, 'file');

// Load language info into DB
load_language($language_file);

// Restore to correct language
require(WB_PATH.'/languages/'.LANGUAGE.'.php');

// Print success message
if ( $action == 'install' )
{
	$admin->print_success( 'Installed successfully' );
}
else
{
	$admin->print_success( 'Upgraded successfully' );
}

// Print admin footer
$admin->print_footer();

?>