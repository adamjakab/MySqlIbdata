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

    public function execute($options) {
        if (!($options["check-only"] || $options["backup-only"] || $options["do-it"])) {
            throw new \Exception("No execution options were set. Try --help.");
        }
        if ($options["do-it"]) {
            $this->runAll();
        }
        else {
            if ($options["check-only"]) {
                $this->runChecks();
            }
            if ($options["backup-only"]) {
                $this->runDatabaseBackups();
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function runAll() {
        $this->runChecks();
        $this->runDatabaseBackups();
        $this->mysql->removeAllDatabasesAndRecreateIbdataFile();
        $this->mysql->recreateDatabases();
        $this->log(str_repeat("-", 120));
        $this->log("All done.");
    }

    /**
     * @throws \Exception
     */
    protected function runDatabaseBackups() {
        $this->mysql->createDatabaseBackups();
    }

    /**
     * @throws \Exception
     */
    protected function runChecks() {
        $this->mysql->checkConnection();
        $this->mysql->checkPermissions();
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