<?php

/**
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or (at
 *   your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful, but
 *   WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *   General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 *   @author          Black Cat Development
 *   @copyright       2013, Black Cat Development
 *   @link            http://blackcat-cms.org
 * @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 *
 */

if (defined('CAT_PATH')) {
    if (defined('CAT_VERSION')) include(CAT_PATH.'/framework/class.secure.php');
} elseif (file_exists($_SERVER['DOCUMENT_ROOT'].'/framework/class.secure.php')) {
    include($_SERVER['DOCUMENT_ROOT'].'/framework/class.secure.php');
} else {
    $subs = explode('/', dirname($_SERVER['SCRIPT_NAME']));    $dir = $_SERVER['DOCUMENT_ROOT'];
    $inc = false;
    foreach ($subs as $sub) {
        if (empty($sub)) continue; $dir .= '/'.$sub;
        if (file_exists($dir.'/framework/class.secure.php')) {
            include($dir.'/framework/class.secure.php'); $inc = true;    break;
	}
	}
    if (!$inc) trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
}

if(!( intval(FRONTEND_SIGNUP) && (  0 == (isset($_SESSION['USER_ID']) ? intval($_SESSION['USER_ID']) : 0) )))
{
	if(INTRO_PAGE) {
		header('Location: '.CAT_URL.PAGES_DIRECTORY.'/index.php');
		exit(0);
	} else {
		header('Location: '.CAT_URL.'/index.php');
		exit(0);
	}
}

if (
       ENABLED_ASP
	&& isset( $_POST[ 'username' ] )
	&& ( // form faked? Check the honeypot-fields.
	     (
		      ! isset( $_POST[ 'submitted_when' ] )
		   || ! isset( $_SESSION[ 'submitted_when' ] )
		 )
		 ||
		 (
		      $_POST[ 'submitted_when' ] != $_SESSION[ 'submitted_when' ]
		 )
		 ||
		 (
		         ! isset( $_POST[ 'email-address' ] )
		      || $_POST[ 'email-address' ]
		 )
		 ||
		 (
		 	    ! isset( $_POST[ 'name' ] )
			  || $_POST[ 'name' ]
	 	 )
		 ||
		 (
		         ! isset( $_POST[ 'full_name' ] )
		 	  || $_POST[ 'full_name' ]
		 )
	)
) {
	exit( header( "Location: " . CAT_URL . PAGES_DIRECTORY . "" ) );
}

// Load the language file
if ( !file_exists( CAT_PATH . '/languages/' . DEFAULT_LANGUAGE . '.php' ) )
{
	exit( 'Error loading language file ' . DEFAULT_LANGUAGE . ', please check configuration' );
}
else
{
	require_once( CAT_PATH . '/languages/' . DEFAULT_LANGUAGE . '.php' );
	$load_language = false;
}


// Required page details
$page_id          = 0;
$page_description = '';
$page_keywords    = '';
define( 'PAGE_ID', 0 );
define( 'ROOT_PARENT', 0 );
define( 'PARENT', 0 );
define( 'LEVEL', 0 );
define( 'PAGE_TITLE', $TEXT[ 'SIGNUP' ] );
define( 'MENU_TITLE', $TEXT[ 'SIGNUP' ] );
define( 'MODULE', '' );
define( 'VISIBILITY', 'public' );

// Set the page content include file
if ( isset( $_POST[ 'username' ] ) )
{
	define( 'PAGE_CONTENT', CAT_PATH . '/account/signup2.php' );
}
else
{
	define( 'PAGE_CONTENT', CAT_PATH . '/account/signup_form.php' );
}

// Set auto authentication to false
$auto_auth = false;

// Include the index (wrapper) file
require( CAT_PATH . '/index.php' );

?>