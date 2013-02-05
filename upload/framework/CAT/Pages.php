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
 *   @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 *
 */

if (!class_exists('CAT_Object', false))
{
    @include dirname(__FILE__) . '/Object.php';
}
if (!function_exists('sanitize_url'))
{
    @include dirname(__FILE__) . '/../functions.php';
}

if (!class_exists('CAT_Pages', false))
{
    class CAT_Pages extends CAT_Object
    {
        protected $debugLevel           = 8; // 8 = OFF

        private $space                  = '    ';  // space before header items
        public  $current_block          = array(); // data for current block

        private $preferences		    = array();
        public $page_id                 = NULL;
        public  $current_page		= array(
    			'id'		=> -1,
    			'parent'	=> -1
    	);
	    public	$pages_editable;

	    private $permissions		= array();
	    private $menu				= array();
	    private $pages				= array();
	    private $template_menu		= array();
	    private $template_block		= array();

        private static $properties      = array();

        // header components
        private static $css             = array();
        private static $meta            = array();
        private static $js              = array();
        private static $jquery          = array();
        private static $jquery_core     = false;
        private static $jquery_ui_core  = false;

        // scan dirs
        private static $css_search_path = array();
        private static $js_search_path  = array();

        // footer components
        private static $script          = array();
        private static $f_jquery        = array();
        private static $f_js            = array();

        // singleton
        private static $instance        = NULL;

        public static function getInstance( $permissions = false )
        {
            if (!self::$instance)
            {
                self::$instance = new self();
                self::$instance->preferences = array(
    				'PAGE_LEVEL_LIMIT'		=> PAGE_LEVEL_LIMIT,
    				'MANAGE_SECTIONS'		=> MANAGE_SECTIONS,
    				'PAGE_TRASH'			=> PAGE_TRASH,
    				'MULTIPLE_MENUS'		=> MULTIPLE_MENUS == false ? false : true
    		    );
                self::$instance->setPerms($permissions);
            }
            return self::$instance;
        }

        public function setPerms($permissions)
        {
            if ( is_array($permissions) && count($permissions) )
    		{
    			$this->permissions = array(
					'PAGES'			 => $permissions['pages'],
					'DISPLAY_ADD_L0' => $permissions['pages_add_l0'],
					'DISPLAY_ADD'	 => $permissions['pages_add'],
					'PAGES_MODIFY'	 => $permissions['pages_modify'],
					'PAGES_DELETE'	 => $permissions['pages_delete'],
					'PAGES_SETTINGS' => $permissions['pages_settings'],
					'DISPLAY_INTRO'	 => $permissions['pages_intro']
    			);
    		}
        }   // end function setPerms()

        /**
         * identify the page to show
         *   @access public
         *   @param  boolean  $no_intro
         *   @param  integer  $page_id
         *   @return boolean
         **/
        public function getPage($no_intro, $page_id)
        {
            global $database, $wb;
            $wb->sql_where_language = NULL;
            // We have no page id and are supposed to show the intro page
            if ((INTRO_PAGE AND !isset($no_intro)) AND (!isset($page_id) OR !is_numeric($page_id)))
            {
                // Get intro page content
                $filename = CAT_PATH . PAGES_DIRECTORY . '/intro' . PAGE_EXTENSION;
                if (file_exists($filename))
                {
                    $handle  = @fopen($filename, "r");
                    $content = @fread($handle, filesize($filename));
                    @fclose($handle);
                    $this->preprocess($content);
                    header("Location: " . CAT_URL . PAGES_DIRECTORY . "/intro" . PAGE_EXTENSION . ""); // send intro.php as header to allow parsing of php statements
                    echo ($content);
                    return false;
                }
            }
            // Check if we should add page language sql code
            if (PAGE_LANGUAGES)
            {
                // needed for SM2, for example
                $wb->sql_where_language = ' AND `language`=\'' . LANGUAGE . '\'';
            }
            if (!isset($page_id) OR !is_numeric($page_id))
            {
                $this->getDefaultPage($wb->sql_where_language);
            }
            else
            {
                $this->page_id = $page_id;
            }
            return true;
        } // end function getPage()

        /**
         * determine default page
         *   @access public
         *   @param  string  $where_lang
         *   @return void
         **/
        public function getDefaultPage($where_lang)
        {
            global $database, $wb;
            // Check for a page id
            $table_p       = CAT_TABLE_PREFIX . 'pages';
            $table_s       = CAT_TABLE_PREFIX . 'sections';
            $now           = time();
            $query_default = "
    			SELECT `p`.`page_id`, `link`, `language`
    			FROM `$table_p` AS `p` INNER JOIN `$table_s` USING(`page_id`)
    			WHERE `parent` = '0' AND `visibility` = 'public'
    			AND (($now>=`publ_start` OR `publ_start`=0) AND ($now<=`publ_end` OR `publ_end`=0))
    			$where_lang
    			ORDER BY `p`.`position` ASC LIMIT 1";
            $get_default   = $database->query($query_default);
            if (!$get_default->numRows() > 0)
            {
                // no default page for this lang, try without
                $query_default = "
        			SELECT `p`.`page_id`, `link`, `language`
        			FROM `$table_p` AS `p` INNER JOIN `$table_s` USING(`page_id`)
        			WHERE `parent` = '0' AND `visibility` = 'public'
        			AND (($now>=`publ_start` OR `publ_start`=0) AND ($now<=`publ_end` OR `publ_end`=0))
        			ORDER BY `p`.`position` ASC LIMIT 1";
                $get_default   = $database->query($query_default);
            }
            if ($get_default->numRows() > 0)
            {
                $fetch_default = $get_default->fetchRow(MYSQL_ASSOC);
                if (!isset($fetch_default))
                {
                    $wb->print_under_construction();
                    exit();
                }
                $this->default_link    = $fetch_default['link'];
                $this->default_page_id = $fetch_default['page_id'];
                $this->page_language   = $fetch_default['language'];
                $this->page_id         = $fetch_default['page_id'];
                // Check for redirection
                if (HOMEPAGE_REDIRECTION)
                {
                    header("Location: " . $wb->page_link($this->default_link));
                    exit();
                }
            }
            else
            {
                // No pages have been added, so print under construction page
                $wb->print_under_construction();
                exit();
            }
        } // end function getDefaultPage()

        /**
         * load the page details
         *   @access public
         *   @return void
         **/
        public function getPageDetails()
        {
            global $database, $wb;
            if ($this->page_id != 0)
            {
                // Query page details
                $query_page = "SELECT * FROM " . CAT_TABLE_PREFIX . "pages WHERE page_id = '{$this->page_id}'";
                $get_page   = $database->query($query_page);
                // Make sure page was found in database
                if ($get_page->numRows() == 0)
                {
                    $this->printFatalError("Page not found");
                }
                // Fetch page details
                $this->page = $get_page->fetchRow(MYSQL_ASSOC);
                if (!defined('PAGE_ID'))
                {
                    define('PAGE_ID', $this->page['page_id']);
                }
                if (!defined('PAGE_TITLE'))
                {
                    define('PAGE_TITLE', $this->page['page_title']);
                }
                if (!defined('PARENT'))
                {
                    define('PARENT', $this->page['parent']);
                }
                if (!defined('ROOT_PARENT'))
                {
                    define('ROOT_PARENT', $this->page['root_parent']);
                }
                if (!defined('LEVEL'))
                {
                    define('LEVEL', $this->page['level']);
                }
                if (!defined('VISIBILITY'))
                {
                    define('VISIBILITY', $this->page['visibility']);
                }

                // Menu Title
                $menu_title = $this->page['menu_title'];
                if ($menu_title != '')
                {
                    if (!defined('MENU_TITLE'))
                    {
                        define('MENU_TITLE', $menu_title);
                    }
                }
                else
                {
                    if (!defined('MENU_TITLE'))
                    {
                        define('MENU_TITLE', PAGE_TITLE);
                    }
                }
                // Page trail
                foreach (explode(',', $this->page['page_trail']) AS $pid)
                {
                    $this->page_trail[$pid] = $pid;
                }
                // Page description
                $this->page_description = $this->page['description'];
                if ($this->page_description != '')
                {
                    define('PAGE_DESCRIPTION', $this->page_description);
                }
                else
                {
                    define('PAGE_DESCRIPTION', WEBSITE_DESCRIPTION);
                }
                // Page keywords
                $this->page_keywords = $this->page['keywords'];
                // Page link
                $this->link          = $this->getLink($this->page['link']);
            }
            else
            {
                $this->printFatalError('Missing page_id!');
            }

            // Figure out what template to use
            if (!defined('TEMPLATE'))
            {
                if (isset($this->page['template']) AND $this->page['template'] != '')
                {
                    if (file_exists(CAT_PATH . '/templates/' . $this->page['template'] . '/index.php'))
                    {
                        define('TEMPLATE', $this->page['template']);
                    }
                    else
                    {
                        define('TEMPLATE', DEFAULT_TEMPLATE);
                    }
                }
                else
                {
                    define('TEMPLATE', DEFAULT_TEMPLATE);
                }
            }
            // Set the template dir
            define('TEMPLATE_DIR', CAT_URL . '/templates/' . TEMPLATE);

            // Check if user is allowed to view this page
            if ($this->page && $this->isVisible($this->page) == false)
            {
                if (VISIBILITY == 'deleted' OR VISIBILITY == 'none')
                {
                    // User isnt allowed on this page so tell them
                    $this->page_access_denied = true;
                }
                elseif (VISIBILITY == 'private' OR VISIBILITY == 'registered')
                {
                    // Check if the user is authenticated
                    if (CAT_Users::getInstance()->is_authenticated() == false)
                    {
                        // User needs to login first
                        header("Location: " . CAT_URL . "/account/login.php?redirect=" . $this->link);
                        exit(0);
                    }
                    else
                    {
                        // User isnt allowed on this page so tell them
                        $wb->page_access_denied = true;
                    }

                }
            }
            // check if there is at least one active section
            if ($this->page && $this->isActive($this->page) == false)
            {
                $this->page_no_active_sections = true;
            }
        } // end function getPageDetails()

        /**
         * get page sections for given block
         *   @access public
         *   @param  integer $block
         *   @return void (direct print to STDOUT)
         **/
        public function getPageContent($block = 1)
        {
            // Get outside objects
            global $TEXT, $MENU, $HEADING, $MESSAGE;
            global $logger, $globals, $database, $wb, $sec_h, $parser;
            $admin =& $wb;

            $logger->logDebug(sprintf('getting content for block [%s]', $block));

            if ($wb->page_access_denied == true)
            {
                $logger->logDebug('Access denied');
                echo $this->lang()->translate('Sorry, you do not have permissions to view this page');
                return;
            }
            if ($sec_h->has_active_sections($this->page_id) === false)
            {
                $logger->logDebug('no active sections found');
                echo $this->lang()->translate('Sorry, no active content to display');
                return;
            }

            if (isset($globals) and is_array($globals))
            {
                $logger->logDebug('setting globals', $globals);
                foreach ($globals as $global_name)
                {
                    global $$global_name;
                }
            }
            // Make sure block is numeric
            if (!is_numeric($block))
            {
                $block = 1;
            }
            // Include page content
            if (!defined('PAGE_CONTENT') or $block != 1)
            {
                $page_id             = intval($wb->page_id);
                // set session variable to save page_id only if PAGE_CONTENT is empty
                $_SESSION['PAGE_ID'] = !isset($_SESSION['PAGE_ID']) ? $page_id : $_SESSION['PAGE_ID'];
                // set to new value if page_id changed and not 0
                if (($page_id != 0) && ($_SESSION['PAGE_ID'] <> $page_id))
                {
                    $_SESSION['PAGE_ID'] = $page_id;
                }
                // get sections
                $sections = $sec_h->get_active_sections(PAGE_ID, $block);
                // no active sections found, so...
                if (!is_array($sections) || !count($sections))
                {
                    $logger->logDebug('no active sections found');
                    // ...do we have default block content?
                    if ($wb->default_block_content == 'none')
                    {
                        $logger->logDebug('no default content found');
                        return;
                    }
                    if (is_numeric($wb->default_block_content))
                    {
                        $logger->logDebug('getting default content from default block');
                        // set page id to default block and get sections
                        $page_id  = $wb->default_block_content;
                        $sections = $sec_h->get_active_sections($page_id, $block);
                    }
                    else
                    {
                        $logger->logDebug('getting default content from default page');
                        // set page id to default page and get sections
                        $page_id  = $wb->default_page_id;
                        $sections = $sec_h->get_active_sections($page_id, $block);
                    }
                    // still no sections?
                    if (!is_array($sections) || !count($sections))
                    {
                        $logger->logDebug('still no sections, return undef');
                        return;
                    }
                }
                // Loop through them and include their module file
                foreach ($sections as $section)
                {
                    $logger->logDebug('sections for this block', $sections);
                    $section_id = $section['section_id'];
                    $module     = $section['module'];
                    // make a anchor for every section.
                    if (defined('SEC_ANCHOR') && SEC_ANCHOR != '')
                    {
                        echo '<a class="section_anchor" id="' . SEC_ANCHOR . $section_id . '"></a>';
                    }
                    // check if module exists - feature: write in errorlog
                    if (file_exists(CAT_PATH . '/modules/' . $module . '/view.php'))
                    {
                        // load language file (if any)
                        $this->lang()->addFile(LANGUAGE . '.php', sanitize_path(CAT_PATH . '/modules/' . $module . '/languages'));
                        // set template path
                        if (file_exists(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates')))
                            $parser->setPath(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates'));
                        if (file_exists(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates/default')))
                            $parser->setPath(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates/default'));
                        if (file_exists(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates/' . DEFAULT_TEMPLATE)))
                        {
                            $parser->setFallbackPath(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates/default'));
                            $parser->setPath(sanitize_path(CAT_PATH . '/modules/' . $module . '/templates/' . DEFAULT_TEMPLATE));
                        }
                        // fetch content -- this is where to place possible output-filters (before highlighting)
                        // fetch original content
                        ob_start();
                        require(CAT_PATH . '/modules/' . $module . '/view.php');
                        $content = ob_get_contents();
                        ob_end_clean();
                    }
                    else
                    {
                        continue;
                    }

                    // highlights searchresults
                    if (isset($_GET['searchresult']) && is_numeric($_GET['searchresult']) && !isset($_GET['nohighlight']) && isset($_GET['sstring']) && !empty($_GET['sstring']))
                    {
                        $arr_string = explode(" ", $_GET['sstring']);
                        if ($_GET['searchresult'] == 2)
                        {
                            // exact match
                            $arr_string[0] = str_replace("_", " ", $arr_string[0]);
                        }
                        echo search_highlight($content, $arr_string);
                    }
                    else
                    {
                        echo $content;
                    }
                }
            }
            else
            {
                require(PAGE_CONTENT);
            }
        }

        public function getLink($link)
        {
            // Check for :// in the link (used in URL's) as well as mailto:
            if (strstr($link, '://') == '' && substr($link, 0, 7) != 'mailto:')
            {
                return CAT_URL . PAGES_DIRECTORY . $link . PAGE_EXTENSION;
            }
            else
            {
                return $link;
            }
        } // end function getLink()

        /**
         * calls appropriate function for analyzing and printing page footers
         *
         * @access public
         * @param  string  $for - 'backend'/'frontend'
         * @return mixed
         *
         **/
        public function getFooters($for)
        {
            // what for?
            if (!$for || $for == '' || ($for != 'frontend' && $for != 'backend'))
            {
                $for = 'frontend';
            }
            $this->log()->logDebug('creating footers for [' . $for . ']');

            if ($for == 'backend')
            {
                return $this->getBackendFooters();
            }
            else
            {
                return $this->getFrontendFooters();
            }
        } // end function getFooters()

        /**
         * calls appropriate function for analyzing and printing page headers
         *
         * @access public
         * @param  string  $for - 'backend'/'frontend'
         * @param  string  $section - backend section name to load JS for
         * @return mixed
         *
         **/
        public function getHeaders($for = NULL, $section = false)
        {
            // don't do this twice
            if (defined('CAT_HEADERS_SENT'))
            {
                $this->log()->logDebug('headers already sent, returning');
                return;
            }

            // what for?
            if (!$for || $for == '' || ($for != 'frontend' && $for != 'backend'))
            {
                $for = 'frontend';
            }
            $this->log()->logDebug('creating headers for [' . $for . ']');

            // do we have a page id?
            $page_id = defined('PAGE_ID') ? PAGE_ID : ((isset($_REQUEST['page_id']) && is_numeric($_REQUEST['page_id'])) ? $_REQUEST['page_id'] : NULL);
            $this->log()->logDebug('page id: [' . $page_id . ']');

            if ($for == 'backend')
            {
                return $this->getBackendHeaders($section);
            }
            else
            {
                return $this->getFrontendHeaders($section);
            }

        } // end function getHeaders()

        /**
         *
         *
         *
         *
         **/
        public function getBackendFooters()
        {
            // -----------------------------------------------------------------
            // -----                    backend theme                      -----
            // -----------------------------------------------------------------
            $file = $this->sanitizePath(CAT_PATH . '/templates/' . DEFAULT_THEME . '/footers.inc.php');
            if (file_exists($file))
            {
                $this->log()->logDebug(sprintf('adding footer items for backend theme [%s]', DEFAULT_THEME));
                $this->_load_footers_inc($file, 'backend', 'templates/' . DEFAULT_THEME);
            } // end loading theme

            // -----------------------------------------------------------------
            // -----                     admin tool                        -----
            // -----------------------------------------------------------------
            if (isset($_REQUEST['tool']))
            {
                $path = $this->sanitizePath(CAT_PATH . '/modules/' . $_REQUEST['tool'] . '/tool.php');
                $this->log()->logDebug(sprintf('handle admin tool [%s] - path [%s]', $_REQUEST['tool'], $path));

                if (file_exists($path))
                {
                    $file = $this->sanitizePath(CAT_PATH . '/modules/' . $_REQUEST['tool'] . '/footers.inc.php');
                    if (file_exists($file))
                    {
                        $this->log()->logDebug(sprintf('adding footer items for admin tool [%s]', $_REQUEST['tool']));
                        $this->_load_footers_inc($file, 'backend', 'templates/' . DEFAULT_THEME);
                    }
                }
            }

            // -----------------------------------------------------------------
            // -----                scan for js files                      -----
            // -----------------------------------------------------------------
            if (count(CAT_Pages::$js_search_path))
            {
                foreach (CAT_Pages::$js_search_path as $directory)
                {
                    $file = $this->sanitizePath($directory . '/backend_body.js');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$f_js[] = '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $file) . '"></script>' . "\n";
                    }
                }
            }

            return $this->getJQuery('footer') . $this->getJavaScripts('footer');

        } // end function getBackendFooters()

        /**
         *
         *
         *
         *
         **/
        public function getBackendHeaders($section)
        {
            // -----------------------------------------------------------------
            // -----                    backend theme                      -----
            // -----------------------------------------------------------------
            $file = $this->sanitizePath(CAT_PATH . '/templates/' . DEFAULT_THEME . '/headers.inc.php');
            if (file_exists($file))
            {
                $this->log()->logDebug(sprintf('adding items for backend theme [%s]', DEFAULT_THEME));
                $this->_load_headers_inc($file, 'backend', 'templates/' . DEFAULT_THEME, $section);
            } // end loading theme

            // -----------------------------------------------------------------
            // -----                     admin tool                        -----
            // -----------------------------------------------------------------
            if (isset($_REQUEST['tool']))
            {
                $path = $this->sanitizePath(CAT_PATH . '/modules/' . $_REQUEST['tool'] . '/tool.php');
                $this->log()->logDebug(sprintf('handle admin tool [%s] - path [%s]', $_REQUEST['tool'], $path));

                if (file_exists($path))
                {
                    array_push(CAT_Pages::$css_search_path, '/modules/' . $_REQUEST['tool'], '/modules/' . $_REQUEST['tool'] . '/css');
                    array_push(CAT_Pages::$js_search_path, '/modules/' . $_REQUEST['tool'], '/modules/' . $_REQUEST['tool'] . '/js');

                    $file = $this->sanitizePath(CAT_PATH . '/modules/' . $_REQUEST['tool'] . '/headers.inc.php');
                    if (file_exists($file))
                    {
                        $this->log()->logDebug(sprintf('adding items for admin tool [%s]', $_REQUEST['tool']));
                        $this->_load_headers_inc($file, 'backend', 'modules/' . $_REQUEST['tool'], $section);
                    }
                }
            }
            // -----------------------------------------------------------------
            // -----                  edit page                            -----
            // -----------------------------------------------------------------
            else
            {
                $this->_load_sections('backend');
            }

            // -----------------------------------------------------------------
            // -----                scan for css files                     -----
            // -----------------------------------------------------------------
            $this->_load_css('backend');

            // -----------------------------------------------------------------
            // -----                scan for js files                      -----
            // -----------------------------------------------------------------
            $this->_load_js('backend');

            // return the results
            return $this->getCSS() . $this->getJQuery('header') . $this->getJavaScripts('header');

        } // end function getBackendHeaders()

        /**
         *
         *
         *
         *
         **/
        public function getFrontendFooters()
        {
            // -----------------------------------------------------------------
            // -----                  frontend theme                       -----
            // -----------------------------------------------------------------
            $file = $this->sanitizePath(CAT_PATH . '/templates/' . TEMPLATE . '/footers.inc.php');
            if (file_exists($file))
            {
                $this->log()->logDebug(sprintf('adding footer items for frontend template [%s]', TEMPLATE));
                $this->_load_footers_inc($file, 'frontend', 'templates/' . TEMPLATE);
            } // end loading theme

            // -----------------------------------------------------------------
            // -----                  scan for js files                    -----
            // -----------------------------------------------------------------
            if (count(CAT_Pages::$js_search_path))
            {
                foreach (CAT_Pages::$js_search_path as $directory)
                {
                    $file = $this->sanitizePath($directory . '/frontend_body.js');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$f_js[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $file) . '"></script>' . "\n";
                    }
                }
            }

            return $this->getJQuery('footer') . $this->getJavaScripts('footer');

        } // end function getFrontendFooters()

        /**
         *
         *
         *
         *
         **/
        public function getFrontendHeaders()
        {
            // -----------------------------------------------------------------
            // -----                  frontend theme                       -----
            // -----------------------------------------------------------------
            $file = $this->sanitizePath(CAT_PATH . '/templates/' . TEMPLATE . '/headers.inc.php');
            if (file_exists($file))
            {
                $this->log()->logDebug(sprintf('adding items for backend theme [%s]', TEMPLATE));
                $this->_load_headers_inc($file, 'frontend', 'templates/' . TEMPLATE);
            }

            // add template path to CSS search path (frontend only)
            array_push(
                CAT_Pages::$css_search_path,
                '/templates/' . TEMPLATE,
                '/templates/' . TEMPLATE . '/css',
                // for skinnables
                '/templates/' . TEMPLATE . '/templates/default',
                '/templates/' . TEMPLATE . '/templates/default/css'
            );

            // -----------------------------------------------------------------
            // -----                  sections (modules)                   -----
            // -----------------------------------------------------------------
            $this->_load_sections('frontend');

            // -----------------------------------------------------------------
            // -----                  scan for css files                   -----
            // -----------------------------------------------------------------
            $this->_load_css('frontend');

            // -----------------------------------------------------------------
            // -----                  scan for js files                    -----
            // -----------------------------------------------------------------
            $this->_load_js('frontend');

            // return the results
            return $this->getPageProperties() . $this->getCSS() . $this->getJQuery('header') . $this->getJavaScripts('header');

        } // end function getFrontendHeaders()

        /**
         * returns the items of static array $css as HTML link markups
         *
         * @access public
         * @return HTML
         *
         **/
        public function getCSS()
        {
            $output = NULL;
            if (count(CAT_Pages::$css))
            {
                foreach (CAT_Pages::$css as $item)
                {
                    // make sure we have an URI (CAT_URL included)
                    $file = (preg_match('#' . CAT_URL . '#i', $item['file']) ? $item['file'] : CAT_URL . '/' . $item['file']);
                    $output .= '<link rel="stylesheet" type="text/css" href="' . sanitize_url($file) . '" media="' . (isset($item['media']) ? $item['media'] : 'all') . '" />' . "\n";
                }
            }
            return $output;
        } // end function getCSS()

        /**
         * returns the items of static array $jquery
         *
         * @access public
         * @return HTML
         *
         **/
        public function getJQuery($for = 'header')
        {
            if ($for == 'header')
            {
                $static =& CAT_Pages::$jquery;
            }
            else
            {
                $static =& CAT_Pages::$f_jquery;
            }
            if (count($static))
            {
                return implode($static);
            }
            return NULL;
        } // end function getJQuery()

        /**
         * returns the items of static array $js
         *
         * @access public
         * @return HTML
         *
         **/
        public function getJavaScripts($for = 'header')
        {
            if ($for == 'header')
            {
                $static =& CAT_Pages::$js;
            }
            else
            {
                $static =& CAT_Pages::$f_js;
            }
            if (is_array($static) && count($static))
            {
                return implode("\n", $static) . "\n";
            }
            return NULL;
        } // end function getJavaScripts()

        /**
         *
         *
         *
         *
         **/
        public function getPageProperties()
        {
            $properties = $this->_load_page_properties();
            $output     = array();

            // charset
            if (isset($properties['default_charset']))
            {
                $output[] = $this->space . '<meta http-equiv="Content-Type" content="text/html; charset=' . $properties['default_charset'] . '" />';
            }

            // page title
            if (isset($properties['title']))
            {
                $output[] = $this->space . '<title>' . $properties['title'] . '</title>';
            }

            // description
            if (isset($properties['description']))
            {
                $output[] = $this->space . '<meta name="description" content="' . $properties['description'] . '" />';
            }

            // keywords
            if (isset($properties['keywords']))
            {
                $output[] = $this->space . '<meta name="keywords" content="' . $properties['keywords'] . '" />';
            }

            return implode("\n", $output) . "\n";

        } // end function getPageProperties()

        /**
         *
         *
         *
         *
         **/
        public function get_linked_by_language($page_id)
        {
            global $database, $wb, $admin;
            if (!is_object($wb))
                $wb =& $admin;
            $results = $database->query('SELECT * FROM `' . CAT_TABLE_PREFIX . 'page_langs` AS t1' . ' RIGHT OUTER JOIN `' . CAT_TABLE_PREFIX . 'pages` AS t2' . ' ON t1.link_page_id=t2.page_id' . ' WHERE t1.page_id = ' . $page_id);
            if ($results->numRows())
            {
                $items = array();
                while (($row = $results->fetchRow(MYSQL_ASSOC)) !== false)
                {
                    $row['href'] = $wb->page_link($row['link']) . (($row['lang'] != '') ? '?lang=' . $row['lang'] : NULL);
                    $items[]     = $row;
                }
                return $items;
            }
            return false;
        } // end function get_linked_by_language()

    	/**
    	 * get_template_blocks function.
    	 *
    	 * Function to get all menus of an template
    	 *
    	 * @access public
    	 * @param  mixed  $template (default: DEFAULT_TEMPLATE)
    	 * @param  int    $selected (default: 1)
    	 * @return void
    	 */
    	public function get_template_blocks( $template = DEFAULT_TEMPLATE , $selected = 1)
    	{
    		// =============================================
    		// ! Include template info file (if it exists)
    		// =============================================
    		if ( SECTION_BLOCKS != false )
    		{
    			$template_location = ( $template != '' ) ?
    				CAT_PATH . '/templates/' . $template . '/info.php' :
    				CAT_PATH . '/templates/' . DEFAULT_TEMPLATE . '/info.php';

    			if ( file_exists($template_location) )
    			{
    				require($template_location);
    			}

    			// =========================
    			// ! Check if $menu is set
    			// =========================
    			if ( !isset($block[1]) || $block[1] == '' )
    			{
    				$block[1]	= $this->lang()->translate('Main');
    			}

    			// ================================
    			// ! Add menu options to the list
    			// ================================
    			foreach ( $block AS $number => $name )
    			{
    				$this->template_block[$number] = array(
    					'NAME'			=> $name,
    					'VALUE'			=> $number,
    					'SELECTED'		=> ( $selected == $number || $selected == $name ) ? true : false
    				);
    				if ( $selected == $number || $selected == $name )
    				{
    					$this->current_block	= array(
    						'name'		=> $name,
    						'id'		=> $number
    					);
    				}
    			}
    			return $this->template_block;
    		}
    		else return false;
    	}   // end function get_template_blocks()

    	/**
    	 * get_template_menus function.
    	 *
    	 * Function to get all menus of an template
    	 *
    	 * @access public
    	 * @param mixed $template (default: DEFAULT_TEMPLATE)
    	 * @param int $selected (default: 1)
    	 * @return void
    	 */
    	public function get_template_menus( $template = DEFAULT_TEMPLATE , $selected = 1)
    	{
    		// =============================================
    		// ! Include template info file (if it exists)
    		// =============================================
    		if ( $this->preferences['MULTIPLE_MENUS'] != false )
    		{
    			$template_location = ( $template != '') ?
    				CAT_PATH . '/templates/' . $template . '/info.php' :
    				CAT_PATH . '/templates/' . DEFAULT_TEMPLATE . '/info.php';

    			if ( file_exists($template_location) )
    			{
    				require($template_location);
    			}
    			// =========================
    			// ! Check if $menu is set
    			// =========================
    			if ( !isset($menu[1]) || $menu[1] == '' )
    			{
    				$menu[1]	= $this->lang()->translate('Main');
    			}

    			// ================================
    			// ! Add menu options to the list
    			// ================================
    			foreach ( $menu AS $number => $name )
    			{
    				$this->template_menu[$number] = array(
    					'NAME'			=> $name,
    					'VALUE'			=> $number,
    					'SELECTED'		=> ( $selected == $number || $selected == $name ) ? true : false
    				);
    			}
    			return $this->template_menu;
    		}
    		else return false;
    	}   // end function get_template_menus()

        /**
    	 * pages_list function.
    	 *
    	 * Function to workout to which pages a new page can be added (for Dropdown)
    	 *
    	 * @access public
    	 * @param  int    $parent (default: 0)
    	 * @param  int    $level  (default: 1)
    	 * @return void
    	 */
    	public function pages_list($parent = 0, $level = 1)
        {
    		// ==================================================================================
    		// ! If user can add a page to level = 0, add this option to the array parent first
    		// ==================================================================================
    		if ( isset($this->permissions['DISPLAY_ADD_L0']) && $this->permissions['DISPLAY_ADD_L0'] == true || $level == 0)
    		{
    			$this->parent_page[0]['disabled']			= false;
    			$this->parent_page[0]['level']				= 0;
    			$this->parent_page[0]['id']					= 0;
    			$this->parent_page[0]['menu_title']			= $this->lang()->translate('None');
    			$this->parent_page[0]['page_title']			= $this->lang()->translate('None');
    			$this->parent_page[0]['current_is_parent']	= false;
    			$this->parent_page[0]['is_parent']			= ( $this->current_page['parent']	== 0 ) ? true : false;
    			$this->parent_page[0]['is_current']			= ( $this->current_page['id']		== 0 ) ? true : false;
    		}
    		else $this->parent_page = array();

    		$this->parent_list(0);

    		return $this->parent_page;
    	}   // end function pages_list()

    	/**
    	 * parent_list function.
    	 *
    	 * Function to get all pages for a dropdown menu
    	 *
    	 * @access public
    	 * @param int $parent (default: 0)
    	 * @return void
    	 */
    	public function parent_list( $parent = 0 )
    	{
            global $database;

    		$get_pages = $database->query("SELECT * FROM " . CAT_TABLE_PREFIX . "pages WHERE parent = '$parent' AND visibility!='deleted' ORDER BY position ASC");

    		while ( $page = $get_pages->fetchRow( MYSQL_ASSOC ) )
    		{
    			if ( $this->isVisible($page) == false ) continue;
    			// ===================================================================================
    			// ! Stop users from adding pages with a level of more than the set page level limit
    			// ===================================================================================
    			if ( $page['level']+1 < PAGE_LEVEL_LIMIT)
    			{
    				// ==================
    				// ! Get user perms
    				// ==================
    				$admin_groups		= explode(',', str_replace('_', '', $page['admin_groups']));
    				$admin_users		= explode(',', str_replace('_', '', $page['admin_users']));
    				$page_trail			= explode(',',$page['page_trail']);

    				$in_group = false;
    				foreach ( CAT_Users::getInstance()->get_groups_id() as $cur_gid )
    				{
    					if ( in_array($cur_gid, $admin_groups) )
    					{
    						$in_group = true;
    					}
    				}
    				$this->parent_page[$page['page_id']]['disabled']			= ( ($in_group) || is_numeric( array_search($this->get_user_id(), $admin_users) ) ) ? false : true;
    				$this->parent_page[$page['page_id']]['level']				= $page['level'];
    				$this->parent_page[$page['page_id']]['id']					= $page['page_id'];
    				$this->parent_page[$page['page_id']]['menu_title']			= $page['menu_title'];
    				$this->parent_page[$page['page_id']]['page_title']			= $page['page_title'];
    				$this->parent_page[$page['page_id']]['current_is_parent']	= in_array( $this->current_page['id'], $page_trail ) ? true : false;
    				$this->parent_page[$page['page_id']]['is_direct_parent']	= ( $this->current_page['parent']	== $page['page_id'] ) ? true : false;
    				$this->parent_page[$page['page_id']]['is_current']			= ( $this->current_page['id']		== $page['page_id'] ) ? true : false;
    			}
    			$this->parent_page		= $this->parent_list( $page['page_id'] );
    		}
    		return $this->parent_page;
    	}

        /**
         * checks if page is active (=has active sections and is between
         * publ_start and publ_end)
         *
         * @access public
         * @param  array  $page
         * @return boolean
         **/
        public function isActive($page)
        {
            global $database;
            $now = time();
            $sql = 'SELECT COUNT(*) FROM `' . CAT_TABLE_PREFIX . 'sections` ';
            $sql .= 'WHERE (' . $now . ' BETWEEN `publ_start` AND `publ_end`) OR ';
            $sql .= '(' . $now . ' > `publ_start` AND `publ_end`=0) ';
            $sql .= 'AND `page_id`=' . (int) $page['page_id'];
            return ($database->get_one($sql) != false);
        } // end function isActive()

        /**
         * Check whether a page is visible or not.
         * This will check page-visibility and user- and group-rights.
         *
         * @param  array   $page
         * @return boolean
         */

        public function isVisible($page)
        {
            global $wb, $admin;
            //if(!is_object($wb)) $wb =& $admin;
            // First check if visibility is 'none', 'deleted'
            $show_it = false;
            switch ($page['visibility'])
            {
                case 'none':
                case 'deleted':
                    $show_it = false;
                    break;
                case 'hidden':
                case 'public':
                    $show_it = true;
                    break;
                case 'private':
                case 'registered':
                    #if ($admin->is_authenticated() == true)
                    #{
                    #    $show_it = ($admin->is_group_match($admin->get_groups_id(), $page['viewing_groups']) || $admin->is_group_match($admin->get_user_id(), $page['viewing_users']));
                    #}
                    $show_id = true;
                    break;
            }
            return ($show_it);
        } // end function isVisible()

        /**
         *
         **/
        public function show_page($page)
        {
            global $database;
            if (!is_array($page))
            {
                $sql = 'SELECT `page_id`, `visibility`, `viewing_groups`, `viewing_users` ';
                $sql .= 'FROM `' . CAT_TABLE_PREFIX . 'pages` WHERE `page_id`=' . (int)$page;
                if (($res_pages = $database->query($sql)) != null)
                {
                    if (!($page = $res_pages->fetchRow()))
                    {
                        return false;
                    }
                }
            }
            return($this->isVisible($page) && $this->isActive($page));
        }   // end function show_page()

// -----------------------------------------------------------------------------
// ----- from class.pages.php --------------------------------------------------
// -----------------------------------------------------------------------------

    	/**
    	 * make_list function.
    	 *
    	 * Completely rewritten function to get pages
    	 *
    	 * @access public
    	 * @param int $parent (default: 0)
    	 * @param bool $add_sections (default: false)
    	 * @return void
    	 */
    	public function make_list( $parent = 0 , $add_sections = false )
    	{
            global $database;

    		// ===================================================
    		// ! Get objects and vars from outside this function
    		// ===================================================

    		if ( $add_sections )
    		{
    			$sql_sections		 = '';
    		}

    		$sql		 = 'SELECT * FROM `'.CAT_TABLE_PREFIX.'pages` ';
    		$sql		.= ( $parent != 0 )					? 'WHERE `parent` = '.$parent.' ' : '';
    		$sql		.= ( PAGE_TRASH != 'inline' )		?
    													( $parent != 0 ) ?
    														'AND `visibility` != \'deleted\' ' :
    														'WHERE `visibility` != \'deleted\' ' :
    														'';
    		$sql		.= 'ORDER BY `level` ASC, `position` DESC';

    		// ===============================
    		// ! Get page list from database
    		// ===============================
    		$get_pages		= $database->query($sql);
    		if ( $get_pages->numRows() > 0 )
    		{
    			$this->pages_editable = true;
    			$temp_values		 = array();
    			while ( $values = $get_pages->fetchRow( MYSQL_ASSOC ) )
    			{
    				$temp_values[$values['page_id']]				= $values;

    				if ( $add_sections )
    				{
    					$sql_sections	.= ' OR page_id = ' . $values['page_id'];
    				}

    				if ( isset($temp_values[$values['parent']]) && is_array($temp_values[$values['parent']]) )
    				{
    					$temp_values[$values['parent']]		= array_merge( $temp_values[$values['parent']], array( 'is_parent' => ( $values['parent'] != $parent ? true : false ) ) );
    				}
    				else
    				{
    					$temp_values[$values['parent']]['is_parent']		= $values['parent'] != 0 ? true : false;
    				}
    				if ( !(isset($pages) && is_array($pages)) )
    				{
    					$pages			= array( $values['page_id'] );
    					$pages_parent	= $values['parent'];
    				}
    				else if ( $values['parent'] == $parent )
    				{
    					array_splice( $pages, 0, 0, $values['page_id'] );
    				}
    				else {
    					$key		= array_search( $values['parent'], $pages )+1; // "+1" to add the value BEHIND the parent page in the array
    					array_splice( $pages, $key, count($pages), array_merge( array( $values['page_id'] ), array_slice( $pages, $key ) ) );
    				}
    			}

    			if ( $add_sections && $sql_sections != '' )
    			{
    				$sql_sections	= 'SELECT page_id, section_id, name FROM `'.CAT_TABLE_PREFIX.'sections` WHERE ' . substr( $sql_sections, 4 ) . ' ORDER BY position';
    				$sections		= $database->query($sql_sections);
    				if ( $sections->numRows() > 0 )
    				{
    					$sections_array	= array();
    					while ( $section = $sections->fetchRow( MYSQL_ASSOC ) )
    					{
    						if ( !( isset( $sections_array[$section['page_id']] ) && is_array( $sections_array[$section['page_id']] ) ) )
    						{
    							$sections_array[$section['page_id']]						= array();
    						}
    						$sections_array[$section['page_id']][$section['section_id']]	= array(
    							'section_id'	=> $section['section_id'],
    							'name'			=> $section['name']
    						);
    					}
    				}
    			}

    			$pages_array	= array();
    			$level			= 0;
                $users          = CAT_Users::getInstance();

    			foreach ( $pages as $key => $page )
    			{
    				$this->pages[$key]						= $temp_values[$page];
    				$this->pages[$key]['page_link']			= substr($temp_values[$page]['link'],strripos($temp_values[$page]['link'],'/')+1);
    				$this->pages[$key]['sections']			= isset($sections_array[$page])							? $sections_array[$page] : false;
    				$this->pages[$key]['cookie']			= isset( $_COOKIE['pageid_'.$this->pages[$key]['page_id']] )	?  true : false;

    				// ==================
    				// ! Get user perms
    				// ==================
    				$admin_groups		= explode(',', str_replace('_', '', $this->pages[$key]['admin_groups']));
    				$admin_users		= explode(',', str_replace('_', '', $this->pages[$key]['admin_users']));
    				$in_group = FALSE;
    				foreach ( $users->get_groups_id() as $cur_gid)
    				{
    					if (in_array($cur_gid, $admin_groups))
    					{
    						$in_group = TRUE;
    					}
    				}

    				// =================================================
    				// ! Check user perms and count for editable sites
    				// =================================================
    				if ( ($in_group) || is_numeric(array_search($this->get_user_id(), $admin_users) ) )
    				{
    					if ( $this->pages[$key]['visibility'] == 'deleted' )
    					{
    						$this->pages[$key]['editable']		= false; // $this->preferences['PAGE_TRASH'] == 'inline' ? true : false;
    					}
    					else if ( $this->pages[$key]['visibility'] != 'deleted' )
    					{
    						$this->pages[$key]['editable']		= true;
    					}
    				}
    				else
    				{
    					if ( $this->pages[$key]['visibility'] == 'private')
    					{
    						continue;
    					}
    					else
    					{
    						$this->pages[$key]['editable']		= false;
    					}
    				}

    				$this->pages[$key]['close_parent']		= $temp_values[$page]['level'] < $level ?
    																( $level - $temp_values[$page]['level'] ) : 0;
    				$level									= $temp_values[$page]['level'];
    			}
    		}
    		return $this->pages;
    	}

    	/**
    	 * get_child_pages function.
    	 *
    	 * Is not needed any more, as make_list can also get only one page layer - marked as deprecated
    	 *
    	 * @access public
    	 * @param int $page_id (default: 0)
    	 * @param string $field (default: '*')
    	 * @return void
    	 */
    	public function get_child_pages( $page_id = 0, $field = '*' )
    	{
            global $database;
    		if ( $this->permissions['PAGES'] == true)
    		{

    			if ( !isset($this->child_pages['editable']) )
    				$this->child_pages['editable']	= 0;

    			if ( is_array( $field ) )
    			{
    				$get_field = implode(',', $field);
    			}
    			// only for security remove all
    			else if ( $field != '*' )
    			{
    				$get_field = htmlentities($field);
    			}
    			else $get_field = '*';

    			$get_child_pages = $database->query('SELECT '.$get_field.' FROM `'.CAT_TABLE_PREFIX.'pages` WHERE `parent` = '.$page_id.' ORDER BY `position` ASC');

    			if ( $get_child_pages->numRows() > 0)
    			{
    				$counter = 0;
    				while ( $child_page = $get_child_pages->fetchRow( MYSQL_ASSOC ))
    				{
    					$this->child_pages[$counter]['page_id']			= $child_page['page_id'];
    					$this->child_pages[$counter]['menu_title']		= $child_page['menu_title'];
    					$this->child_pages[$counter]['page_title']		= $child_page['page_title'];
    					$this->child_pages[$counter]['visibility']		= $child_page['visibility'];
    					$this->child_pages[$counter]['cookie']			= isset( $_COOKIE['p'.$child_page['page_id']] ) ?  true : false;
    					//$this->child_pages[$counter]['active']			= $this->page_is_active($page) ? true : false;
    					$this->child_pages[$counter]['view_url']		= PAGES_DIRECTORY .$child_page['link']. PAGE_EXTENSION;

    					if ( $child_page['visibility'] != 'deleted' )
    					{
    						$this->child_pages[$counter]['editable']	= true;
    						$this->child_pages['editable']				= $this->child_pages['editable']+1;
    					}

    					// =================================
    					// ! Check if page has child pages
    					// =================================
    					$check_for_childs = $database->query('SELECT `page_id` FROM `'.CAT_TABLE_PREFIX.'pages` WHERE `parent` = '.$child_page['page_id']);
    					$this->child_pages[$counter]['is_parent']		= ( $check_for_childs->numRows() > 0 ) ? true : false;

    					$counter++;
    				}

    				return $this->child_pages;
    			}
    			else return false;
    		}
    		else return false;
    	}

        /**
         *
         *
         *
         *
         **/
        private function _analyze_css(&$arr, $path_prefix = NULL)
        {
            if (is_array($arr))
            {
                $check_paths = array();
                if ($path_prefix != '')
                {
                    $check_paths = explode('/', $path_prefix);
                    $check_paths = array_reverse($check_paths);
                }
                foreach ($arr as $css)
                {
                    // no file - no good
                    if (!isset($css['file']))
                    {
                        continue;
                    }
                    // relative path?
                    if (!preg_match('#/modules/#i', $css['file']) && ! !preg_match('#/templates/#i', $css['file']))
                    {
                        foreach ($check_paths as $subdir)
                        {
                            if (!preg_match('#' . $subdir . '/#', $css['file']))
                            {
                                $css['file'] = $this->sanitizePath($subdir . '/' . $css['file']);
                            }
                        }
                    }
                    CAT_Pages::$css[] = $css;
                }
            }
        } // end function _analyze_css()

        /**
         * analyzes javascripts array and fills static array $js
         *
         * The components of given $path_prefix are checked to be included in
         * the file name (and added if not)
         *
         * @access private
         * @param  array    $arr
         * @param  string   $path_prefix
         * @return void
         *
         **/
        private function _analyze_javascripts(&$arr, $for = 'header', $path_prefix = NULL, $section = false)
        {
            if ($for == 'header')
            {
                $static =& CAT_Pages::$js;
            }
            else
            {
                $static =& CAT_Pages::$f_js;
            }

            if (is_array($arr))
            {
                $check_paths = array();
                if ($path_prefix != '')
                {
                    $check_paths = explode('/', $path_prefix);
                    $check_paths = array_reverse($check_paths);
                }

                if (isset($arr['all']))
                {
                    foreach ($arr['all'] as $item)
                    {
                        if (!preg_match('#/modules/#i', $item))
                        {
                            foreach ($check_paths as $subdir)
                            {
                                if (!preg_match('#' . $subdir . '/#', $item))
                                {
                                    $item = $this->sanitizePath($subdir . '/' . $item);
                                }
                            }
                        }
                        $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $item) . '"></script>';
                    }
                    unset($arr['all']);
                }

                if (isset($arr['individual']))
                {
                    if (is_array($arr['individual']))
                    {
                        foreach ($arr['individual'] as $section_name => $item)
                        {
                            if ($section_name == strtolower($section))
                            {
                                foreach ($check_paths as $subdir)
                                {
                                    if (!preg_match('#' . $subdir . '/#', $item))
                                    {
                                        $item = $this->sanitizePath($subdir . '/' . $item);
                                    }
                                }
                                $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $item) . '"></script>';
                            }
                        }
                    }
                    unset($arr['individual']);
                }

                #remaining
                if(is_array($arr) && count($arr))
                {
                    foreach ($arr as $item)
                    {
                        if ( preg_match('/^http(s)?:/', $item))
                        {
                            $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url($item) . '"></script>';
                            continue;
                        }
                        if (!preg_match('#/modules/#i', $item))
                        {
                            foreach ($check_paths as $subdir)
                            {
                                if (!preg_match('#' . $subdir . '/#', $item))
                                {
                                    $item = $this->sanitizePath($subdir . '/' . $item);
                                }
                            }
                        }
                        $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $item) . '"></script>';
                    }
                }

            }
            else
            {
                $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . '/' . $arr) . '"></script>';
            }
        } // end function _analyze_javascripts()

        /**
         *
         *
         *
         *
         **/
        private function _analyze_jquery_components(&$arr, $for = 'frontend', $section = NULL)
        {
            $static =& CAT_Pages::$jquery;

            // make sure that we load the core if needed, even if the
            // author forgot to set the flags
            if ( isset($arr['ui']) && $arr['ui'] === true )
            {
                $arr['core'] = true;
                $arr['ui'] = true;
            }

            // load the components
            if (isset($arr['ui-theme']) && file_exists(CAT_PATH . '/modules/lib_jquery/jquery-ui/themes/' . $arr['ui-theme']))
            {
                $static[] = $this->space . '<link rel="stylesheet" type="text/css" href="' . sanitize_url(CAT_URL . '/modules/lib_jquery/jquery-ui/themes/' . $arr['ui-theme'] . '/jquery-ui.css') . '" media="all" />' . "\n";
            }

            // core is always added to header
            if (!CAT_Pages::$jquery_core && isset($arr['core']) && $arr['core'] === true)
            {
                CAT_Pages::$jquery[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . '/modules/lib_jquery/jquery-core/jquery-core.min.js') . '"></script>' . "\n";
                CAT_Pages::$jquery_core = true;
            }

            // ui is always added to header
            if (!CAT_Pages::$jquery_ui_core && isset($arr['ui']) && $arr['ui'] === true)
            {
                CAT_Pages::$jquery[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . '/modules/lib_jquery/jquery-ui/ui/jquery-ui.min.js') . '"></script>' . "\n";
                CAT_Pages::$jquery_ui_core = true;
            }

            // components to load on all pages (backend only)
            if (isset($arr['all']) && is_array($arr['all']))
            {
                foreach ($arr['all'] as $item)
                {
                    if (!file_exists(sanitize_path(CAT_PATH . '/modules/lib_jquery/plugins/' . $item)))
                    {
                        if (!file_exists(sanitize_path(CAT_PATH . '/modules/lib_jquery/plugins/' . $item . '/' . $item . '.js')))
                        {
                            // error! file not found!
                            continue;
                        }
                        else
                        {
                            $item = $item . '/' . $item . '.js';
                        }
                    }
                    $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . '/modules/lib_jquery/plugins/' . $item . '/' . $item . '.js') . '"></script>' . "\n";
                }
            }

            // components to load on individual pages only (backend only)
            if (isset($arr['individual']) && is_array($arr['individual']) && isset($section) && $section != '')
            {
                foreach ($arr['individual'] as $section_name => $item)
                {
                    if ($section_name == strtolower($section))
                    {
                        if (!file_exists(sanitize_path(CAT_PATH . '/modules/lib_jquery/plugins/' . $item)))
                        {
                            if (!file_exists(sanitize_path(CAT_PATH . '/modules/lib_jquery/plugins/' . $item . '/' . $item . '.js')))
                            {
                                // error! file not found!
                                continue;
                            }
                            else
                            {
                                $item = $item . '/' . $item . '.js';
                            }
                        }
                        $static[] = $this->space . '<script type="text/javascript" src="' . sanitize_url(CAT_URL . '/modules/lib_jquery/plugins/' . $item) . '"></script>' . "\n";
                    }
                }
            }

        } // end function _analyze_jquery_components()

        /**
         *
         *
         *
         *
         **/
        private function _load_css($for = 'frontend')
        {
            if (count(CAT_Pages::$css_search_path))
            {
                // automatically add CSS files
                foreach (CAT_Pages::$css_search_path as $directory)
                {
                    // template.css
                    $file = $this->sanitizePath($directory . '/template.css');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$css[] = array(
                            'media' => 'screen,projection',
                            'file' => $file
                        );
                    }
                    // print.css
                    $file = $this->sanitizePath($directory . '/print.css');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$css[] = array(
                            'media' => 'print',
                            'file' => $file
                        );
                    }
                    // frontend.css / backend.css
                    $file = $this->sanitizePath($directory . '/' . $for . '.css');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$css[] = array(
                            'media' => 'all',
                            'file' => $file
                        );
                    }
                    // frontend.css / backend_print.css
                    $file = $this->sanitizePath($directory . '/' . $for . '_print.css');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$css[] = array(
                            'media' => 'print',
                            'file' => $file
                        );
                    }
                    // PAGE_ID.css (frontend only)
                    if ($for == 'frontend' && defined('PAGE_ID') && preg_match('#/templates/#', $directory))
                    {
                        $file = $this->sanitizePath($directory . '/' . PAGE_ID . '.css');
                        if (file_exists($this->sanitizePath(CAT_PATH . '/' . $file)))
                        {
                            CAT_Pages::$css[] = array(
                                'media' => 'screen,projection',
                                'file' => $file
                            );
                        }
                        $file = $this->sanitizePath($directory . '/' . PAGE_ID . '_print.css');
                        if (file_exists($this->sanitizePath(CAT_PATH . '/' . $file)))
                        {
                            CAT_Pages::$css[] = array(
                                'media' => 'print',
                                'file' => $file
                            );
                        }
                    }
                }
            }
        } // end function _load_css()

        /**
         *
         *
         *
         *
         **/
        private function _load_footers_inc($file, $for, $path_prefix, $section)
        {
            // reset array
            $mod_footers = array();
            // load file
            require $file;
            // analyze
            if (isset($mod_footers[$for]) && is_array($mod_footers[$for]) && count($mod_footers[$for]))
            {
                if (isset($mod_footers[$for]['jquery']) && is_array($mod_footers[$for]['jquery']) && count($mod_footers[$for]['jquery']))
                {
                    $this->_analyze_jquery_components($mod_footers[$for]['jquery'][0], $for, $section);
                }
                // ----- other JS -----
                if (isset($mod_footers[$for]['js']) && is_array($mod_footers[$for]['js']) && count($mod_footers[$for]['js']))
                {
                    $temp_arr = ( is_array($mod_footers[$for]['js'][0]) ? $mod_footers[$for]['js'][0] : $mod_footers[$for]['js'] );
                    $this->_analyze_javascripts($mod_footers[$for]['js'], 'footer', $path_prefix . '/js', $section);
                }
            }
        } // end function _load_footers_inc()

        /**
         *
         *
         *
         *
         **/
        private function _load_headers_inc($file, $for, $path_prefix, $section = NULL)
        {
            // reset array
            $mod_headers = array();
            // load file
            require $file;
            // analyze
            if (isset($mod_headers[$for]) && is_array($mod_headers[$for]) && count($mod_headers[$for]))
            {
                // ----- CSS -----
                if (isset($mod_headers[$for]['css']) && is_array($mod_headers[$for]['css']) && count($mod_headers[$for]['css']))
                {
                    $this->_analyze_css($mod_headers[$for]['css'], $path_prefix);
                }
                // ----- jQuery -----
                if (isset($mod_headers[$for]['jquery']) && is_array($mod_headers[$for]['jquery']) && count($mod_headers[$for]['jquery']))
                {
                    $this->_analyze_jquery_components($mod_headers[$for]['jquery'][0], $for, $section);
                }
                // ----- other JS -----
                if (isset($mod_headers[$for]['js']) && is_array($mod_headers[$for]['js']) && count($mod_headers[$for]['js']))
                {
                    $temp_arr = ( is_array($mod_headers[$for]['js'][0]) ? $mod_headers[$for]['js'][0] : $mod_headers[$for]['js'] );
                    $this->_analyze_javascripts($temp_arr, 'header', $path_prefix . '/js', $section);
                }
            }
        }

        /**
         *
         *
         *
         *
         **/
        private function _load_js($for = 'frontend')
        {
            if (count(CAT_Pages::$js_search_path))
            {
                foreach (CAT_Pages::$js_search_path as $directory)
                {
                    $file = $this->sanitizePath($directory . '/' . $for . '.js');
                    if (file_exists(CAT_PATH . '/' . $file))
                    {
                        CAT_Pages::$js[] = '<script type="text/javascript" src="' . sanitize_url(CAT_URL . $file) . '"></script>' . "\n";
                    }
                }
            }
        } // end function _load_js()

        /**
         *
         *
         *
         *
         **/
        private function _load_page_properties()
        {
            global $database;

            if (!is_array(self::$properties) || !count(self::$properties))
            {
                // get global settings
                $sql = sprintf('SELECT `name`,`value` FROM `%ssettings` ORDER BY `name`', CAT_TABLE_PREFIX);
                if (($result = $database->query($sql)) && ($result->numRows() > 0))
                {
                    while (false != ($row = $result->fetchRow(MYSQL_ASSOC)))
                    {
                        if (preg_match('#^website_(.*)$#', $row['name'], $match))
                        {
                            self::$properties[$match[1]] = $row['value'];
                        }
                        if ($row['name'] == 'default_charset')
                        {
                            self::$properties['default_charset'] = ($row['value'] != '') ? $row['value'] : 'utf-8';
                        }
                    }
                }
                else
                {
                    die("Settings not found");
                }

                // get properties for current page; overwrites globals if not empty
                $sql = sprintf('SELECT page_title, description, keywords FROM %spages WHERE page_id = "%d"', CAT_TABLE_PREFIX, PAGE_ID);
                if (($result = $database->query($sql)) && ($result->numRows() > 0))
                {
                    while (false != ($row = $result->fetchRow(MYSQL_ASSOC)))
                    {
                        foreach (array(
                            'page_title',
                            'description',
                            'keywords'
                        ) as $key)
                        {
                            if (isset($row[$key]) && $row[$key] != '')
                            {
                                $prop                    = str_ireplace('page_', '', $key);
                                self::$properties[$prop] = $row[$key];
                            }
                        }
                    }
                }
            }

            return self::$properties;
        }

        /**
         *
         *
         *
         *
         **/
        private function _load_sections($for = 'frontend')
        {
            $page_id = defined('PAGE_ID') ? PAGE_ID : ((isset($_GET['page_id']) && is_numeric($_GET['page_id'])) ? $_GET['page_id'] : NULL);

            if ($page_id && is_numeric($page_id))
            {
                // ...get active sections
                if (!class_exists('CAT_Sections'))
                {
                    @require_once $this->sanitizePath(dirname(__FILE__) . '/Sections.php');
                }
                $sec_h    = new CAT_Sections();
                $sections = $sec_h->get_active_sections($page_id);
                if (is_array($sections) && count($sections))
                {
                    global $current_section;
                    foreach ($sections as $section)
                    {
                        $module = $section['module'];
                        $file   = $this->sanitizePath(CAT_PATH.'/modules/'.$module.'/headers.inc.php');
                        // find header definition file
                        if (file_exists($file))
                        {
                            $current_section = $section['section_id'];
                            $this->_load_headers_inc($file, $for, 'modules/' . $module, $section);
                        }
                        array_push(CAT_Pages::$css_search_path, '/modules/' . $module, '/modules/' . $module . '/css');
                        array_push(CAT_Pages::$js_search_path, '/modules/' . $module, '/modules/' . $module . '/js');
                    } // foreach ($sections as $section)
                } // if (count($sections))
            }
        }

    } // end class

}

?>