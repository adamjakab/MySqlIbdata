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