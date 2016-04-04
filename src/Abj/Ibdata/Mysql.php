<?php
/**
 * Created by Adam Jakab.
 * Date: 04/04/16
 * Time: 10.26
 */

namespace Abj\Ibdata;


use Abj\Console\Configuration;
use Monolog\Logger;

class Mysql {
    /** @var callable */
    protected $logger;

    /** @var  Filesystem */
    protected $filesystem;

    /** @var  \PDO */
    protected $conn;

    /**
     * @param callable $logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->filesystem = new Filesystem($logger);

    }

    public function checkConnection() {
        $this->conn = Configuration::getDatabaseConnection();
        $hostname = $this->getConfigurationVariable('hostname');
        $version = $this->getConfigurationVariable('version');
        $this->log("Connected to MySql database: {$version} @{$hostname}");
        //check if server is using 'innodb_file_per_table'
        $innodb_file_per_table = $this->getConfigurationVariable('innodb_file_per_table');
        if ($innodb_file_per_table != 'ON') {
            throw new \Exception("Server is NOT configured with option 'innodb_file_per_table'!");
        }
        $this->log("Configuration 'innodb_file_per_table' OK.");
    }

    /**
     * @param $varName
     * @return array|string|bool
     */
    protected function getConfigurationVariable($varName) {
        $answer = FALSE;
        $sql = 'SHOW VARIABLES LIKE ' . $this->conn->quote($varName);
        $s = $this->conn->query($sql);
        $res = $s->fetchAll(\PDO::FETCH_ASSOC);
        if (count($res) > 1) {
            $answer = [];
            foreach ($res as $data) {
                $answer[$data["Variable_name"]] = $data["Value"];
            }
        }
        else if (count($res) == 1) {
            $answer = $res[0]["Value"];
        }
        return $answer;
    }

    /**
     * @param string $msg
     * @param int    $level
     * @param array  $context
     */
    protected function log($msg, $level = Logger::INFO, $context = []) {
        call_user_func($this->logger, $msg, $level, $context);
    }

    public function checkPermissions() {
        $this->filesystem->setDatadir($this->getConfigurationVariable('datadir'));
        $databases = $this->getDatabaseList();
        $temporaryDbName = 'ibdatatest';
        if (in_array($temporaryDbName, $databases)) {
            throw new \Exception("Temporary test database({$temporaryDbName}) already exists! Remove & rerun.");
        }
        $this->createDatabase($temporaryDbName);
        $databases = $this->getDatabaseList();
        if (!in_array($temporaryDbName, $databases)) {
            throw new \Exception("Unable to create test database({$temporaryDbName})!");
        }


        //$this->dropDatabase($temporaryDbName);
    }

    /**
     * @return array
     */
    protected function getDatabaseList() {
        $answer = [];
        $exclude = ["mysql", "information_schema"];
        $sql = 'SHOW DATABASES';
        $s = $this->conn->query($sql);
        $res = $s->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($res as $data) {
            if (!in_array($data["Database"], $exclude)) {
                $answer[] = $data["Database"];
            }
        }
        return $answer;
    }

    protected function createDatabase($databaseName) {
        $sql = "CREATE DATABASE {$databaseName}";
        $this->conn->exec($sql);
    }

    protected function dropDatabase($databaseName) {
        $sql = "DROP DATABASE {$databaseName}";
        $this->conn->exec($sql);
    }
}