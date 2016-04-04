<?php
/**
 * Created by PhpStorm.
 * User: jackisback
 * Date: 14/11/15
 * Time: 22.32
 */

namespace Abj\Console;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Class Configuration
 * @package Mekit\Console
 */
class Configuration {
    /** @var  string */
    private static $configurationFilePath;

    /** @var array */
    private static $configuration;

    /**
     * @param string $configurationFilePath
     */
    public static function initializeWithConfigurationFile($configurationFilePath) {
        self::$configurationFilePath = $configurationFilePath;
        if (!self::$configuration) {
            self::loadConfiguration();
        }
    }

    /**
     * @throws \Exception|ParseException
     */
    protected static function loadConfiguration() {
        $configPath = realpath(self::$configurationFilePath);
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException("The configuration file does not exist!");
        }
        $fs = new Filesystem();
        $yamlParser = new Parser();
        $config = $yamlParser->parse(file_get_contents($configPath));
        if (!is_array($config) || !isset($config["configuration"])) {
            throw new \InvalidArgumentException("Malformed configuration file!" . $configPath);
        }

        $imports = [];
        if (isset($config["imports"]) && is_array($config["imports"]) && count($config["imports"])) {
            $imports = $config["imports"];
            unset($config["imports"]);
        }

        foreach ($imports as $import) {
            if (isset($import["resource"])) {
                $resourcePath = realpath(dirname($configPath) . '/' . $import["resource"]);
                if ($resourcePath) {
                    $additionalConfig = $yamlParser->parse(file_get_contents($resourcePath));
                    $config = array_replace_recursive($additionalConfig, $config);
                }
                else {
                    throw new \LogicException(
                        "Import resource is set but cannot be found(" . $import["resource"] . ")!"
                    );
                }
            }
        }

        self::$configuration = $config["configuration"];
    }

    /**
     * @param string|bool $databaseName
     * @return \PDO
     */
    public static function getDatabaseConnection($databaseName = FALSE) {
        $cfg = self::getConfiguration();
        if (!isset($cfg["database"]) || !is_array($cfg["database"])) {
            throw new \LogicException("Missing 'database' section in configuration!");
        }

        $host = isset($cfg["database"]["host"]) ? $cfg["database"]["host"] : '';;
        $username = isset($cfg["database"]["username"]) ? $cfg["database"]["username"] : '';
        $password = isset($cfg["database"]["password"]) ? $cfg["database"]["password"] : '';

        $dsn = "mysql:host={$host}" . ($databaseName ? ";dbname={$databaseName}" : "");

        $connection = new \PDO($dsn, $username, $password);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $connection;
    }

    /**
     * @return array
     */
    public static function getConfiguration() {
        return self::$configuration;
    }
}