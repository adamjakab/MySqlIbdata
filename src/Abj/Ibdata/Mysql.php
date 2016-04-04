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

    /** @var  string */
    protected $backupPath;

    /**
     * @param callable $logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->filesystem = new Filesystem($logger);
        $cfg = Configuration::getConfiguration();
        $backupPath = isset($cfg["global"]["backup_path"]) ? $cfg["global"]["backup_path"] : './mysqlbackups';
        $this->backupPath = $this->filesystem->createFolder($backupPath);
    }

    /**
     * @throws \Exception
     */
    public function recreateDatabases() {
        $this->log("Recreating databases...");
        $backupfiles = glob($this->backupPath . '/*.sql');
        foreach ($backupfiles as $backupfile) {
            $databaseName = str_replace([$this->backupPath . '/', '.sql'], '', $backupfile);
            $this->log("Dropping database({$databaseName})...");
            $this->dropDatabase($databaseName);
            $this->log("Creating database({$databaseName})...");
            $this->createDatabase($databaseName);
            $this->log("Restoring database({$databaseName})...");
            $this->restoreDatabase($databaseName, $backupfile);
        }
    }

    /**
     * @throws \Exception
     */
    public function removeAllDatabasesAndRecreateIbdataFile() {
        $this->log("Dropping all databases...");
        $databases = $this->getDatabaseList();
        $backupfiles = glob($this->backupPath . '/*.sql');
        foreach ($backupfiles as $backupfile) {
            $databaseName = str_replace([$this->backupPath . '/', '.sql'], '', $backupfile);
            if (in_array($databaseName, $databases)) {
                $this->log("Dropping database({$databaseName})...");
                $this->dropDatabase($databaseName);
            }
        }

        //STOP MYSQL
        $command = 'service mysql stop';
        $o = $this->executeConsoleCommand($command);
        $this->log("EXEC({$command}): " . json_encode($o));
        sleep(3);

        $this->filesystem->removeIbdataFiles();

        $command = 'service mysql start';
        $o = $this->executeConsoleCommand($command);
        $this->log("EXEC({$command}): " . json_encode($o));
        sleep(3);
    }

    /**
     * @throws \Exception
     */
    public function createDatabaseBackups() {
        $this->log("Creating database backups in: {$this->backupPath}");
        $databases = $this->getDatabaseList();
        foreach ($databases as $databaseName) {
            $this->dumpDatabase($databaseName, $this->backupPath . '/' . $databaseName . '.sql');
        }
    }

    /**
     * @throws \Exception
     */
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

        if (!$this->filesystem->checkIfDatabaseFolderExists($temporaryDbName)) {
            throw new \Exception("Cannot find test database folder!");
        }

        $temporaryDbTable = "data1";
        $this->createDatabaseTable($temporaryDbName, $temporaryDbTable);
        if (!$this->filesystem->checkIfDatabaseTableFilesExist($temporaryDbName, $temporaryDbTable)) {
            throw new \Exception("Cannot find test database table files(.ibd|.frm)!");
        }
        $this->dropDatabaseTable($temporaryDbName, $temporaryDbTable);
        $this->dropDatabase($temporaryDbName);
        $this->log("Permissions ok.");
    }

    /**
     * @throws \Exception
     *
     * @todo: check also innodb_data_file_path
     */
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
     * @param string $databaseName
     * @param string $backupfile
     * @throws \Exception
     */
    protected function restoreDatabase($databaseName, $backupfile) {
        $cfg = Configuration::getConfiguration();
        $username = isset($cfg["database"]["username"]) ? $cfg["database"]["username"] : '';
        $password = isset($cfg["database"]["password"]) ? $cfg["database"]["password"] : '';
        $command = "mysql"
                   . ($username ? " -u{$username}" : "")
                   . ($password ? " -p{$password}" : "")
                   . " " . $databaseName
                   . " < " . $backupfile;
        $this->executeConsoleCommand($command);
        $this->log("Restore for database({$databaseName}) OK.");
    }

    /**
     * @param string $databaseName
     * @param string $dumpfile
     * @throws \Exception
     */
    protected function dumpDatabase($databaseName, $dumpfile) {
        $cfg = Configuration::getConfiguration();
        $username = isset($cfg["database"]["username"]) ? $cfg["database"]["username"] : '';
        $password = isset($cfg["database"]["password"]) ? $cfg["database"]["password"] : '';
        $command = "mysqldump"
                   . ($username ? " -u{$username}" : "")
                   . ($password ? " -p{$password}" : "")
                   . " " . $databaseName
                   . " > " . $dumpfile;
        $this->executeConsoleCommand($command);
        $this->log("Backup for database({$databaseName}) OK.");
    }

    /**
     * @param string $cmdString
     * @return array
     * @throws \Exception
     */
    protected function executeConsoleCommand($cmdString) {
        $output = FALSE;
        $ret = FALSE;
        exec($cmdString, $output, $ret);
        if ($ret != 0) {
            throw new \Exception("Execution of console command({$cmdString}) failed: " . json_encode($output));
        }
        return $output;
    }

    /**
     * @param string $databaseName
     * @param string $tableName
     */
    protected function createDatabaseTable($databaseName, $tableName) {
        $dbConn = Configuration::getDatabaseConnection($databaseName);
        $sql = "CREATE TABLE {$tableName}
        (id int(11), name varchar(64), PRIMARY KEY (id)
        ) ENGINE=InnoDB
        ";
        $dbConn->exec($sql);
    }

    /**
     * @param string $databaseName
     * @param string $tableName
     */
    protected function dropDatabaseTable($databaseName, $tableName) {
        $dbConn = Configuration::getDatabaseConnection($databaseName);
        $sql = "DROP TABLE {$tableName}";
        $dbConn->exec($sql);
    }

    /**
     * @param string $databaseName
     */
    protected function createDatabase($databaseName) {
        $sql = "CREATE DATABASE {$databaseName}";
        $this->conn->exec($sql);
    }

    /**
     * @param string $databaseName
     */
    protected function dropDatabase($databaseName) {
        $sql = "DROP DATABASE {$databaseName}";
        $this->conn->exec($sql);
    }

    /**
     * @return array
     */
    protected function getDatabaseList() {
        $answer = [];
        $exclude = ["mysql", "information_schema", "performance_schema"];
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

}