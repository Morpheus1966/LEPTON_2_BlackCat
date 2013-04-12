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

if (!class_exists('CAT_Helper_Validate'))
{
    if (!class_exists('CAT_Object', false))
    {
        @include dirname(__FILE__) . '/../Object.php';
    }

    class CAT_Helper_Validate extends CAT_Object
    {
        protected      $_config             = array( 'loglevel' => 8 );
        private static $instance;

        public static function getInstance()
        {
            if (!self::$instance)
            {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * check a value as type
         *
         * @access public
         * @param  mixed  $value
         * @param  string $as
         * @return mixed
         **/
        public static function check($value,$as)
        {
            $func = 'is_'.$as;
            if ( ! function_exists($func) )
            {
                CAT_Object::getInstance()->printFatalError( 'No such validation method: '.$as );
            }
            if ( ! $func($value) ) return false;
            return $value;
        }

        /**
         * global method to get data from globals
         *
         * @access public
         * @param  string  $global - name of the superglobal
         * @param  string  $key    - name of the key/var to get
         * @param  string  $require - value type (scalar, numeric, array)
         * @return mixed
         **/
        public function get( $global, $key, $require = NULL, $escape = false )
        {
            $this->log()->logDebug(sprintf('Get key [%s] from global var [%s] validate as [%s]',$key,$global,$require));
            $glob = array();
            if ( isset($GLOBALS[$global]) )
            {
                $glob =& $GLOBALS[$global];
            }
            $value = isset($glob[$key]) ? $glob[$key] : NULL;
            if ( $value && $require )
            {
                $value = self::check($value,$require);
            }
            if ( $value && $escape )
            {
                $value = $this->add_slashes($value);
            }
            $this->log()->logDebug('returning value:',$value);
            return $value;
        }   // end function get()

        /**
         * Modified addslashes function which takes into account magic_quotes
         *
         * @access public
         * @param  string  $input
         * @return string
         **/
        public function add_slashes($input)
        {
            if (get_magic_quotes_gpc() || (!is_string($input)))
            {
                return $input;
            }
            $output = addslashes($input);
            return $output;
        }   // end function add_slashes()

        /**
         * Modified stripslashes function which takes into account magic_quotes
         *
         * @access public
         * @param  string  $input
         * @return string
         **/
        public function strip_slashes($input)
        {
            if (!get_magic_quotes_gpc() || (!is_string($input)))
            {
                return $input;
            }
            $output = stripslashes($input);
            return $output;
        }   // end function strip_slashes()

        /**
         * Get POST data
         *
         * TODO: add sanitize/validate
         *
         * @access public
         * @param  string  $field - fieldname
         * @param  string  $require - value type (scalar, numeric, array)
         * @param  boolean $escape  - use add_slashes(); default: false
         * @return mixed
         **/
        public function sanitizePost( $field, $require=NULL, $escape = false )
        {
            $this->log()->logDebug(sprintf('get field [%s] from $_POST, require type [%s], escape [%s]',$field,$require,$escape));
            return $this->get('_POST',$field,$require,$escape);
        }   // end function sanitizePost()

        /**
         * Get GET data
         *
         * TODO: add sanitize/validate
         *
         * @access public
         * @param  string  $field - fieldname
         * @param  string  $require - value type (scalar, numeric, array)
         * @return mixed
         **/
        public function sanitizeGet($field,$require=NULL,$escape=false)
        {
            $this->log()->logDebug(sprintf('get field [%s] from $_GET, require type [%s], escape [%s]',$field,$require,$escape));
            return $this->get('_GET',$field,$require,$escape);
        }   // end function sanitizeGet()

        /**
         * convenience function to meet the names of the other ones
         **/
        public function sanitizeSession($field,$require=NULL,$escape=false)
        {
            return $this->get('_SESSION',$field,$require,$escape);
        }   // end function sanitizeSession()

        /**
         * Get SESSION data
         *
         * @access public
         * @param  string  $field - fieldname
         * @param  string  $require - value type (scalar, numeric, array)
         * @return mixed
         **/
        public function fromSession($field,$require=NULL,$escape=false)
        {
            return $this->get('_SESSION',$field,$require,$escape);
        }   // end function fromSession()

        /**
         * Get SERVER data
         *
         * @access public
         * @param  string  $field - fieldname
         * @param  string  $require - value type (scalar, numeric, array)
         * @return mixed
         **/
        public function sanitizeServer($field,$require=NULL,$escape=false)
        {
            return $this->get('_SERVER',$field,$require,$escape);
        }   // end function sanitizeServer()

        //*********************************************************************
        // convenience methods; just wrap filter_var
        //*********************************************************************
        public function sanitize_string($string)
        {
            return filter_var($string, FILTER_SANITIZE_STRING);
        }

        public function sanitize_email($address)
        {
            return filter_var($address, FILTER_SANITIZE_EMAIL);
        }

        public function sanitize_url($address)
        {
            $address    = htmlspecialchars((filter_var($address, FILTER_SANITIZE_URL)));
            // href="http://..." ==> href isn't relative
            $rel_parsed = parse_url($address);
            $path       = $rel_parsed['path'];
            $path       = preg_replace('~/\./~', '/', $path); // bla/./bloo ==> bla/bloo
            // resolve /../
            // loop through all the parts, popping whenever there's a .., pushing otherwise.
            $parts      = array();
            foreach ( explode('/', preg_replace('~/+~', '/', $path)) as $part )
            {
                if ($part === ".." || $part == '')
                {
                    array_pop($parts);
                }
                elseif ($part!="")
                {
                    $parts[] = $part;
                }
            }
            return
            (
                  ( is_array($rel_parsed) && array_key_exists( 'scheme', $rel_parsed ) )
                ? $rel_parsed['scheme'] . '://' . $rel_parsed['host'] . ( isset($rel_parsed['port']) ? ':'.$rel_parsed['port'] : NULL )
                : ""
            ) . "/" . implode("/", $parts);
        }

        public function validate_string($string)
        {
            return filter_var($string, FILTER_VALIDATE_STRING);
        }
        public function validate_ip($ip)
        {
            return filter_var($ip, FILTER_VALIDATE_IP);
        }
        public function validate_email($address)
        {
            return filter_var($address, FILTER_VALIDATE_EMAIL);
        }
        public function validate_url($address)
        {
            return filter_var($address, FILTER_VALIDATE_URL);
        }
    }
}
