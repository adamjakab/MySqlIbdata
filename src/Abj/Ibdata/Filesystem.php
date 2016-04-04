<?php
/**
 * Created by Adam Jakab.
 * Date: 04/04/16
 * Time: 10.27
 */

namespace Abj\Ibdata;

use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem as FS;

class Filesystem {
    /** @var callable */
    protected $logger;

    /** @var  string */
    protected $mysqlDataDir;

    /** @var  FS */
    protected $fs;

    /**
     * @param callable $logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->fs = new FS();
    }


    public function removeIbdataFiles() {
        $this->fs->remove($this->mysqlDataDir . '/ibdata1');
        $this->fs->remove($this->mysqlDataDir . '/ib_logfile0');
        $this->fs->remove($this->mysqlDataDir . '/ib_logfile1');
    }

    /**
     * @param string $path
     * @param int    $mode
     * @return string
     */
    public function createFolder($path, $mode = 0777) {
        $this->fs->mkdir($path, $mode);
        return realpath($path) ? realpath($path) : getcwd() . '/' . $path;
    }

    /**
     * @param string $databaseName
     * @return bool
     */
    public function checkIfDatabaseTableFilesExist($databaseName, $tableName) {
        $answer = TRUE;
        $answer = $answer && is_file($this->mysqlDataDir . '/' . $databaseName . '/' . $tableName . '.frm');
        $answer = $answer && is_file($this->mysqlDataDir . '/' . $databaseName . '/' . $tableName . '.ibd');
        return $answer;
    }

    /**
     * @param string $databaseName
     * @return bool
     */
    public function checkIfDatabaseFolderExists($databaseName) {
        return is_dir($this->mysqlDataDir . '/' . $databaseName);
    }

    /**
     * @param string $mysqlDataDir
     * @throws \Exception
     */
    public function setDatadir($mysqlDataDir) {
        if (!is_dir($mysqlDataDir)) {
            throw new \Exception("The datadir({$mysqlDataDir}) does not exist!");
        }
        if (!is_readable($mysqlDataDir)) {
            throw new \Exception("The datadir({$mysqlDataDir}) is not readable by this user!");
        }
        if (!is_writable($mysqlDataDir)) {
            throw new \Exception("The datadir({$mysqlDataDir}) is not writable by this user!");
        }
        if (!is_writable($mysqlDataDir . '/ibdata1')) {
            throw new \Exception("The file({$mysqlDataDir}/ibdata1) is not writable by this user!");
        }
        if (!is_writable($mysqlDataDir . '/ib_logfile0')) {
            throw new \Exception("The file({$mysqlDataDir}/ib_logfile0) is not writable by this user!");
        }
        if (!is_writable($mysqlDataDir . '/ib_logfile1')) {
            throw new \Exception("The file({$mysqlDataDir}/ib_logfile1) is not writable by this user!");
        }
        $this->mysqlDataDir = $mysqlDataDir;
        $this->log("Mysql 'datadir' is registered at path: " . $this->mysqlDataDir);
    }

    /**
     * @param string $msg
     * @param int    $level
     * @param array  $context
     */
    protected function log($msg, $level = Logger::INFO, $context = []) {
        call_user_func($this->logger, $msg, $level, $context);
    }
}