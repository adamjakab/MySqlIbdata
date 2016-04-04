<?php
/**
 * Created by Adam Jakab.
 * Date: 07/10/15
 * Time: 14.27
 */

namespace Abj\Command;

use Abj\Console\Configuration;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Command extends ConsoleCommand {
    /** @var string */
    protected $configDir = 'config';

    /** @var  InputInterface */
    protected $cmdInput;

    /** @var  OutputInterface */
    protected $cmdOutput;

    /** @var  Logger */
    protected $logger;

    /** @var bool */
    protected $logToConsole = FALSE;

    /** @var bool */
    protected $logToFile = FALSE;

    /**
     * @param string $name
     */
    public function __construct($name = NULL) {
        parent::__construct($name);
    }

    /**
     * @param string $msg
     * @param int    $level
     * @param array  $context
     */
    public function log($msg, $level = Logger::INFO, $context = []) {
        if ($this->logToConsole) {
            $this->cmdOutput->writeln($msg);
        }
        if ($this->logToFile) {
            $this->logger->addRecord($level, $msg, $context);
        }
    }

    protected function _execute(InputInterface $input, OutputInterface $output) {
        $this->cmdInput = $input;
        $this->cmdOutput = $output;
        $this->setConfigurationFile();
        $this->setupLogger();
    }

    /**
     * Parse yml configuration
     */
    protected function setConfigurationFile() {
        $config_file = $this->cmdInput->getArgument('config_file');
        $configPath = realpath($config_file);
        if (!$configPath) {
            $configPath = realpath(PROJECT_ROOT . '/config/' . $config_file);
        }
        if (!$configPath) {
            throw new \InvalidArgumentException("The configuration file does not exist!");
        }
        Configuration::initializeWithConfigurationFile($configPath);
    }

    /**
     *
     */
    protected function setupLogger() {
        $cfg = Configuration::getConfiguration();
        $this->logToConsole = isset($cfg['global']['log_to_console']) && $cfg['global']['log_to_console'];
        $this->logToFile = isset($cfg['global']['log_to_file']) && $cfg['global']['log_to_file'];
        //
        if ($this->logToFile) {
            $logFileName = (isset($cfg['global']['log_path']) && $cfg['global']['log_path']
                ? $cfg['global']['log_path']
                : "mysqlibdata.log"
            );
            if (!preg_match('/\.log$/', $logFileName)) {
                $logFileName .= '.log';
            }
            $logFilePath = PROJECT_ROOT . DIRECTORY_SEPARATOR . $logFileName;
            $fs = new Filesystem();
            $fs->touch($logFilePath);

            $this->logger = new Logger("file_logger");
            $logToFileHandler = new StreamHandler($logFilePath, Logger::INFO);
            $this->logger->pushHandler($logToFileHandler);
        }
    }
}