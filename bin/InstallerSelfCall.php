<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18.01.17
 * Time: 10:40
 */

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
require_once 'config/env_configurator.php';

use Composer\IO\ConsoleIO;
use rollun\installer\Install\InstallerInterface;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$container = include 'config/container.php';

/** init composer IO  */
$consoleInput = new ArgvInput();
$consoleOutput = new ConsoleOutput();

$helperSet = new HelperSet([
    'question' => new QuestionHelper(),
    'formatter' => new FormatterHelper(),
    'descriptor' => new DescriptorHelper(),
    'process' => new ProcessHelper(),
    'debugFormatter' => new DebugFormatterHelper(),
]);
$composerIO = new ConsoleIO($consoleInput, $consoleOutput, $helperSet);

if ($argc < 2) {
    $composerIO->writeError('Usage: InstallerSelfCall [fullInstallerClassName] install [/uninstall/reinstall] ');
    exit(1);
}

$className = $argv[1];
if (class_exists($className)) {
    $reflectionClass = new ReflectionClass($className);
    if ($reflectionClass->implementsInterface(InstallerInterface::class) &&
        $reflectionClass->isInstantiable()) {
        try {
            $installer = $reflectionClass->newInstance($container, $composerIO);
            $method = isset($argv[2]) ? $argv[2] : "install";
            call_user_func([$installer, $method]);
            exit(1);
        } catch (Exception $exception) {
            $composerIO->writeError(
                "Installer: $className crash by exception with message: " .
                $exception->getMessage()
            );
            exit(1);
        }
    }
    $composerIO->writeError("$className in not instantiable.");
    exit(1);
}
$composerIO->writeError("Wrong class name: $className");
exit(1);
