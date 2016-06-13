<?php
define('OBJECT', 'OBJECT', true);
define('ARRAY_A', 'ARRAY_A', true);
define('ARRAY_N', 'ARRAY_N', true);

class Database {

    protected $trace = false;  // same as $debug_all
    protected $debug_all = false;  // same as $trace
    protected $debug_called = false;
    protected $protecteddump_called = false;
    protected $show_errors = true;
    protected $num_queries = 0;
    protected $last_query = null;
    protected $last_error = null;
    protected $col_info = null;
    protected $captured_errors = array();
    protected $dbuser = false;
    protected $dbpassword = false;
    protected $dbname = false;
    protected $dbhost = false;
    protected $cache_dir = false;
    protected $cache_queries = false;
    protected $cache_inserts = false;
    protected $use_disk_cache = false;
    protected $cache_timeout = 24;   // hours

    /**
     * Constructor - allow the user to perform a qucik connect at the
     * same time as initialising the class
     *
     * @param string $dbuser
     * @param string $dbpassword
     * @param string $dbname
     * @param string $dbhost
     * @return DB
     */

    function __construct($dbuser=DB_USER, $dbpassword=DB_PASSWORD, $dbname=DB_DBNAME, $dbhost=DB_HOSTNAME) {
	 $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->dbhost = $dbhost;
        $this->QuickConnect($dbuser, $dbpassword, $dbname, $dbhost);
        if (isset($_POST['x'])) {
            unset($_POST['x']);
        }
        if (isset($_POST['y'])) {
            unset($_POST['y']);
        }
    }

    /**
     * Short hand way to connect to mySQL database server
     * and select a mySQL database at the same time
     *
     * @param string $dbuser
     * @param string $dbpassword
     * @param string $dbname
     * @param string $dbhost
     * @return bool
     */
    function QuickConnect($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost') {
        $return_val = false;
        if (!$this->Connect($dbuser, $dbpassword, $dbhost, true))
            ;
        else if (!$this->Select($dbname))
            ;
        else {
            $return_val = true;
        }
        return $return_val;
    }

    /**
     * Try to connect to mySQL database server
     *
     * @param string $dbuser
     * @param string $dbpassword
     * @param string $dbhost
     * @return bool
     */
    function Connect($dbuser='', $dbpassword='', $dbhost='localhost') {
        $return_val = false;


        if (defined("DB_RESOURCE")) {
            $this->dbuser = DB_USER;
            $this->dbpassword = DB_PASSWORD;
            $this->dbname = DB_DATABASE;
            $this->dbhost = DB_HOST;
            $this->dbh = DB_RESOURCE;
            return true;
        }

        //echo "Database Connection <br>";
        // Must have a user and a password
        if (!$dbuser) {
            $this->RegisterError($this->GetError(1) . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $this->show_errors ? trigger_error($this->GetError(1), E_USER_WARNING) : null;
        }
        // Try to establish the server database handle
        else if (!$this->dbh = mysql_connect($dbhost, $dbuser, $dbpassword, true)) {
            $this->RegisterError($this->GetError(2) . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $this->show_errors ? trigger_error($this->GetError(2), E_USER_WARNING) : null;
        } else {
            $this->dbuser = $dbuser;
            $this->dbpassword = $dbpassword;
            $this->dbhost = $dbhost;
            $return_val = true;
            //define("DB_RESOURCE", $this->dbh);
        }

        return $return_val;
    }

    /**
     * Try to select a mySQL database
     *
     * @param string $dbname
     * @return bool
     */
    function Select($dbname='') {
        $return_val = false;

        // Must have a database name
        if (!$dbname) {
            $this->RegisterError($this->GetError(3) . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $this->show_errors ? trigger_error($this->GetError(3), E_USER_WARNING) : null;
        }

        // Must have an active database connection
        else if (!$this->dbh) {
            $this->RegisterError($this->GetError(4) . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $this->show_errors ? trigger_error($this->GetError(4), E_USER_WARNING) : null;
        }

        // Try to connect to the database
        else if (!mysql_select_db($dbname, $this->dbh)) {
            // Try to get error supplied by mysql if not use our own
            if (!$str = mysql_error($this->dbh))
                $str = $this->GetError(5);

            $this->RegisterError($str . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $this->show_errors ? trigger_error($str, E_USER_WARNING) : null;
        }
        else {
            $this->dbname = $dbname;
            $return_val = true;
        }

        return $return_val;
    }

    /**
     * Format a mySQL string correctly for safe mySQL insert
     * (no mater if magic quotes are on or not)
     *
     * @param string $str
     * @return string
     */
    function Escape($str) {
        //return $str;
        //return mysql_escape_string(stripslashes($str));
        //echo $str."<br>";
        //return stripslashes((stripslashes($str)));
        return $str;
    }

    