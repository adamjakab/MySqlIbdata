<?php
/**
 * Created by Adam Jakab.
 * Date: 07/10/15
 * Time: 14.26
 */

namespace Abj\Command;

use Abj\Ibdata\FreeIbdata;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FreeIbdataCommand extends Command implements CommandInterface {
    const COMMAND_NAME = 'free:ibdata';
    const COMMAND_DESCRIPTION = 'Free Ibdata';

    public function __construct() {
        parent::__construct(NULL);
    }

    /**
     * Configure command
     */
    protected function configure() {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->setDefinition(
            [
                new InputArgument(
                    'config_file', InputArgument::REQUIRED, 'The yaml(.yml) configuration file inside the "'
                                                            . $this->configDir . '" subfolder.'
                ),
            ]
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        parent::_execute($input, $output);
        $freeIbdata = new FreeIbdata([$this, 'log']);
        $freeIbdata->execute($input->getOptions());
    }
}