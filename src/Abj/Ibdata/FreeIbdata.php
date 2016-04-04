<?php
/**
 * Created by Adam Jakab.
 * Date: 04/04/16
 * Time: 10.14
 */

namespace Abj\Ibdata;

use Monolog\Logger;

class FreeIbdata {
    /** @var callable */
    protected $logger;

    /** @var  Mysql */
    protected $mysql;

    /**
     * @param callable $logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->mysql = new Mysql($logger);
    }

    /**
     * @param array $options
     */
    public function execute($options) {
        //$this->log("EXECUTING..." . json_encode($options));
        $this->mysql->checkConnection();
        $this->mysql->checkPermissions();
        $this->mysql->createDatabaseBackups();

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