<?php

namespace GetId3\Extension\Cache;

use GetId3\GetId3Core;
use GetId3\Exception\DefaultException;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
//                                                             //
// extension.cache.mysql.php - part of GetId3()                //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////
//                                                             //
// This extension written by Allan Hansen <ahØartemis*dk>      //
// Table name mod by Carlo Capocasa <calroØcarlocapocasa*com>  //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * This is a caching extension for GetId3(). It works the exact same
 * way as the GetId3 class, but return cached information very fast
 *
 * Example:  (see also demo.cache.mysql.php in /demo/)
 *
 *    Normal GetId3 usage (example):
 *
 *       require_once 'getid3/getid3.php';
 *       $getID3 = new GetId3;
 *       $getID3->setEncoding('UTF-8');
 *       $info1 = $getID3->analyze('file1.flac');
 *       $info2 = $getID3->analyze('file2.wv');
 *
 *    GetId3_cached usage:
 *
 *       require_once 'getid3/getid3.php';
 *       require_once 'getid3/getid3/extension.cache.mysql.php';
 *       // 5th parameter (tablename) is optional, default is 'getid3_cache'
 *       $getID3 = new GetId3_cached_mysql('localhost', 'database', 'username', 'password', 'tablename');
 *       $getID3->setEncoding('UTF-8');
 *       $info1 = $getID3->analyze('file1.flac');
 *       $info2 = $getID3->analyze('file2.wv');
 *
 *
 * Supported Cache Types    (this extension)
 *
 *   SQL Databases:
 *
 *   cache_type          cache_options
 *   -------------------------------------------------------------------
 *   mysql               host, database, username, password
 *
 *
 *   DBM-Style Databases:    (use extension.cache.dbm)
 *
 *   cache_type          cache_options
 *   -------------------------------------------------------------------
 *   gdbm                dbm_filename, lock_filename
 *   ndbm                dbm_filename, lock_filename
 *   db2                 dbm_filename, lock_filename
 *   db3                 dbm_filename, lock_filename
 *   db4                 dbm_filename, lock_filename  (PHP5 required)
 *
 *   PHP must have write access to both dbm_filename and lock_filename.
 *
 *
 * Recommended Cache Types
 *
 *   Infrequent updates, many reads      any DBM
 *   Frequent updates                    mysql
 */

/**
 * @author James Heinrich <info@getid3.org>
 * @author Allan Hansen <ahØartemis*dk>
 * @author Carlo Capocasa <calroØcarlocapocasa*com>
 *
 * @link http://getid3.sourceforge.net
 * @link http://www.getid3.org
 */
class Mysql extends GetId3
{
    /**
     * @var type
     */
    private $cursor;

    /**
     * @var type
     */
    private $connection;

    /**
     * public: constructor - see top of this file for cache type and cache_options
     *
     * @param  type      $host
     * @param  type      $database
     * @param  type      $username
     * @param  type      $password
     * @param  type      $table
     *
     * @throws Exception
     */
    public function __construct($host, $database, $username, $password, $table = 'getid3_cache')
    {
        // Check for mysql support
        if (!function_exists('mysqli_connect')) {
            throw new DefaultException('PHP not compiled with mysqli support.');
        }

        // Connect to database
        $this->connection = mysqli_connect('p:' . $host, $username, $password);
        if (!$this->connection) {
            throw new DefaultException('mysqli_connect() failed - check permissions and spelling.');
        }

        // Select database
        if (!mysqli_select_db($this->connection, $database)) {
            throw new DefaultException('Cannot use database '.$database);
        }

        // Set table
        $this->table = $table;

        // Create cache table if not exists
        $this->create_table();

        // Check version number and clear cache if changed
        $version = '';
        if ($this->cursor = mysqli_query($this->connection, 'SELECT `value` FROM `'.mysqli_real_escape_string($this->connection, $this->table)."` WHERE (`filename` = '".mysqli_real_escape_string($this->connection, GetId3Core::VERSION)."') AND (`filesize` = '-1') AND (`filetime` = '-1') AND (`analyzetime` = '-1')")) {
            list($version) = mysqli_fetch_array($this->cursor);
        }
        if ($version != GetId3Core::VERSION) {
            $this->clear_cache();
        }

        parent::__construct();
    }

    /**
     * public: clear cache
     */
    public function clear_cache()
    {
        $this->cursor = mysqli_query($this->connection, 'DELETE FROM `'.mysqli_real_escape_string($this->connection, $this->table).'`');
        $this->cursor = mysqli_query($this->connection, 'INSERT INTO `'.mysqli_real_escape_string($this->connection, $this->table)."` VALUES ('".GetId3Core::VERSION."', -1, -1, -1, '".GetId3Core::VERSION."')");
    }

    /**
     * public: analyze file
     *
     * @param  type $filename
     *
     * @return type
     */
    public function analyze($filename)
    {
        if (file_exists($filename)) {

            // Short-hands
            $filetime = filemtime($filename);
            $filesize = filesize($filename);

            // Lookup file
            $this->cursor = mysqli_query($this->connection, 'SELECT `value` FROM `'.mysqli_real_escape_string($this->connection, $this->table)."` WHERE (`filename` = '".mysqli_real_escape_string($this->connection, $filename)."') AND (`filesize` = '".mysqli_real_escape_string($this->connection, $filesize)."') AND (`filetime` = '".mysqli_real_escape_string($this->connection, $filetime)."')");
            if (mysqli_num_rows($this->cursor) > 0) {
                // Hit
                list($result) = mysqli_fetch_array($this->cursor);

                return unserialize(base64_decode($result));
            }
        }

        // Miss
        $analysis = parent::analyze($filename);

        // Save result
        if (file_exists($filename)) {
            $this->cursor = mysqli_query($this->connection, 'INSERT INTO `'.mysqli_real_escape_string($this->connection, $this->table)."` (`filename`, `filesize`, `filetime`, `analyzetime`, `value`) VALUES ('".mysqli_real_escape_string($this->connection, $filename)."', '".mysqli_real_escape_string($this->connection, $filesize)."', '".mysqli_real_escape_string($connection, $filetime)."', '".mysqli_real_escape_string($this->connection, time())."', '".mysqli_real_escape_string($this->connection, base64_encode(serialize($analysis)))."')");
        }

        return $analysis;
    }

    /**
     * private: (re)create sql table
     *
     * @param type $drop
     */
    private function create_table($drop = false)
    {
        $this->cursor = mysqli_query($this->connection, 'CREATE TABLE IF NOT EXISTS `'.mysqli_real_escape_string($this->connection, $this->table)."` (
            `filename` VARCHAR(255) NOT NULL DEFAULT '',
            `filesize` INT(11) NOT NULL DEFAULT '0',
            `filetime` INT(11) NOT NULL DEFAULT '0',
            `analyzetime` INT(11) NOT NULL DEFAULT '0',
            `value` TEXT NOT NULL,
            PRIMARY KEY (`filename`,`filesize`,`filetime`)) ENGINE=MyISAM");
        echo mysqli_error($this->connection);
    }
}
