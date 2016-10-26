<?php
/*
  +------------------------------------------------------------------------+
  | PhalconEye CMS                                                         |
  +------------------------------------------------------------------------+
  | Copyright (c) 2013-2016 PhalconEye Team (http://phalconeye.com/)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconeye.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Author: Ivan Vorontsov <lantian.ivan@gmail.com>                 |
  +------------------------------------------------------------------------+
*/

namespace Engine;

use Engine\Console\AbstractCommand;
use Engine\Utils\ConsoleUtils;
use Engine\Utils\StringUtils;

/**
 * Console class.
 *
 * @category  PhalconEye
 * @package   Engine
 * @author    Ivan Vorontsov <lantian.ivan@gmail.com>
 * @copyright 2013-2016 PhalconEye Team
 * @license   New BSD License
 * @link      http://phalconeye.com/
 */
class Cli extends Application
{
    /**
     * Defined engine commands.
     *
     * @var AbstractCommand[]
     */
    private $_commands = [];

    /**
     * Run application.
     *
     * @param string $mode Run mode.
     *
     * @return void
     */
    public function run($mode = 'console')
    {
        parent::run($mode);

        // Init commands.
        $this->_initCommands();
    }

    /**
     * Init commands.
     *
     * @return void
     */
    protected function _initCommands()
    {
        // Get engine commands.
        $this->_getCommandsFrom(
            $this->getDI()->get('registry')->directories->engine . '/Console/Command',
            'Engine\Console\Command\\'
        );

        // Get modules commands.
        foreach ($this->getDI()->get('registry')->modules as $module => $path) {
            $module = ucfirst($module);
            $path = $path . $module . '/Command';
            $namespace = $module . '\Command\\';
            $this->_getCommandsFrom($path, $namespace);
        }
    }

    /**
     * Get commands located in directory.
     *
     * @param string $commandsLocation  Commands location path.
     * @param string $commandsNamespace Commands namespace.
     *
     * @return void
     */
    protected function _getCommandsFrom($commandsLocation, $commandsNamespace)
    {
        if (!file_exists($commandsLocation)) {
            return;
        }

        // Get all file names.
        $files = scandir($commandsLocation);

        // Iterate files.
        foreach ($files as $file) {
            if (!StringUtils::endsWith($file, '.php')) {
                continue;
            }

            $commandClass = $commandsNamespace . str_replace('.php', '', $file);
            $this->_commands[] = new $commandClass($this->getDI());
        }
    }

    /**
     * Handle all data and output result.
     *
     * @throws Exception
     * @return mixed
     */
    public function getOutput()
    {
        print ConsoleUtils::info('================================================================', true, 0);
        print ConsoleUtils::info(
            "
           ___  __       __              ____
          / _ \/ / ___ _/ _______  ___  / ____ _____
         / ___/ _ / _ `/ / __/ _ \/ _ \/ _// // / -_)
        /_/  /_//_\_,_/_/\__/\___/_//_/___/\_, /\__/
                                          /___/
                                          Commands Manager", false, 1
        );
        print ConsoleUtils::info('================================================================', false, 2);

        // Installation is required.
        if (!$this->_config->installed) {
            print ConsoleUtils::error('Please, install system first.') . PHP_EOL;
            die();
        }

        // Not arguments?
        if (!isset($_SERVER['argv'][1])) {
            $this->printAvailableCommands();
            die();
        }

        // Check if 'help' command was used.
        if ($this->_helpIsRequired()) {
            return;
        }

        // Try to dispatch the command.
        if ($cmd = $this->_getRequiredCommand()) {
            return $cmd->dispatch();
        }

        // Check for alternatives.
        $available = [];
        foreach ($this->_commands as $command) {
            $providedCommands = $command->getCommands();
            foreach ($providedCommands as $command) {
                $soundex = soundex($command);
                if (!isset($available[$soundex])) {
                    $available[$soundex] = [];
                }
                $available[$soundex][] = $command;
            }
        }

        // Show exception with/without alternatives.
        $soundex = soundex($_SERVER['argv'][1]);
        if (isset($available[$soundex])) {
            print ConsoleUtils::warningLine(
                'Command "' . $_SERVER['argv'][1] .
                '" not found. Did you mean: ' . join(' or ', $available[$soundex]) . '?'
            );
            $this->printAvailableCommands();
        } else {
            print ConsoleUtils::warningLine('Command "' . $_SERVER['argv'][1] . '" not found.');
            $this->printAvailableCommands();
        }
    }

    /**
     * Output available commands.
     *
     * @return void
     */
    public function printAvailableCommands()
    {
        print ConsoleUtils::head('Available commands:');
        foreach ($this->_commands as $command) {
            print ConsoleUtils::command(join(', ', $command->getCommands()), $command->getDescription());
        }
        print PHP_EOL;
    }

    /**
     * Get required command.
     *
     * @param string|null $input Input from console.
     *
     * @return AbstractCommand|null
     */
    protected function _getRequiredCommand($input = null)
    {
        if (!$input) {
            $input = $_SERVER['argv'][1];
        }

        foreach ($this->_commands as $command) {
            $providedCommands = $command->getCommands();
            if (in_array($input, $providedCommands)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * Check help system.
     *
     * @return bool
     */
    protected function _helpIsRequired()
    {
        if ($_SERVER['argv'][1] != 'help') {
            return false;
        }

        if (empty($_SERVER['argv'][2])) {
            $this->printAvailableCommands();
            return true;
        }

        $command = $this->_getRequiredCommand($_SERVER['argv'][2]);
        if (!$command) {
            print ConsoleUtils::warningLine('Command "' . $_SERVER['argv'][2] . '" not found.');
            return true;
        }

        $command->getHelp((!empty($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : null));
        return true;
    }
}