<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 09.03.17
 * Time: 16:54
 */

namespace rollun\installer;

use Composer\IO\ConsoleIO;
use Composer\Package\PackageInterface;
use FilesystemIterator;
use Interop\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use rollun\installer\Install\InstallerAbstract;
use rollun\installer\Install\InstallerInterface;

class LibInstallerManager
{
    /** @var  PackageInterface */
    protected $package;

    /** @var  string */
    protected $rootNamespace;

    /** @var  ConsoleIO */
    protected $cliIO;

    /** @var  ContainerInterface */
    protected $container;

    /** @var array */
    protected $installers;

    /** @var  string */
    protected $src;

    /**
     * LibInstallerManager constructor.
     * @param PackageInterface $package
     * @param ContainerInterface $container
     * @param ConsoleIO $cliIO
     * @param string $src
     */
    public function __construct(PackageInterface $package, ContainerInterface $container, ConsoleIO $cliIO, $src = null)
    {

        $this->package = $package;
        $this->container = $container;
        $this->cliIO = $cliIO;
        $this->installers = [];

        $autoload = $package->getAutoload();
        if (isset($autoload['psr-4'])) {
            $rootNamespaces = array_keys($autoload['psr-4']);
            $this->cliIO->writeError("autoload['psr-4']: " . implode(', ', $rootNamespaces));

            foreach ($rootNamespaces as $rootNamespace) {
                $this->rootNamespace = $rootNamespace;
                if (isset($src) && isset($autoload['psr-4'][$this->rootNamespace])) {
                    $dir = is_string($autoload['psr-4'][$this->rootNamespace]) ?
                        $autoload['psr-4'][$this->rootNamespace] :
                        $autoload['psr-4'][$this->rootNamespace][0];
                    $this->src = realpath($src . DIRECTORY_SEPARATOR . $dir);
                } elseif (isset($autoload['psr-4'][$this->rootNamespace])) {
                    $dir = is_string($autoload['psr-4'][$this->rootNamespace]) ?
                        $autoload['psr-4'][$this->rootNamespace] :
                        $autoload['psr-4'][$this->rootNamespace][0];
                    /*$this->src = realpath('vendor') . DIRECTORY_SEPARATOR .
                        str_replace("/", DIRECTORY_SEPARATOR, $package->getPrettyName()) . DIRECTORY_SEPARATOR .
                        $dir;*/
                    $this->src = realpath('vendor' . DIRECTORY_SEPARATOR .
                        $package->getPrettyName() . DIRECTORY_SEPARATOR .
                        $dir);
                }
                if (!isset($this->src) || !is_string($this->src) || !is_dir($this->src)) {
                    $this->cliIO->writeError("Can't find src for package: " . $this->package->getPrettyName());
                } else {
                    $installers = $this->findInstaller($this->src);
                    foreach ($installers as $installerClass) {
                        try {
                            /** @var  InstallerAbstract $installer */
                            $installer = new $installerClass($this->container, $this->cliIO);
                            $this->installers[$installerClass] = $installer;
                        } catch (\Exception $exception) {
                            $this->cliIO->writeError(
                                "Installer: $installerClass crash by exception with message: " .
                                $exception->getMessage()
                            );
                        }
                    }
                }
            }
        } else {
            //$this->cliIO->writeError("Lib don't implements psr-4");
        }
    }

    /**
     * lib dir
     * @param $dir string
     * root namespace for lib.
     * @return string[]
     */
    protected function findInstaller($dir)
    {
        $installer = [];

        if (is_dir($dir)) {
            $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS |
                FilesystemIterator::KEY_AS_PATHNAME);
        } else {
            return $installer;
        }

        foreach ($iterator as $item) {
            //Save only class who implement InstallerInterface and has Installer in name
            /** @var $item RecursiveDirectoryIterator */
            if (!preg_match('/^(\.)|(vendor)/', $item->getFilename())) {
                if ($item->isDir()) {
                    $installer = array_merge($installer, $this->findInstaller($item->getPathname()));
                } elseif (preg_match('/Installer/', $item->getFilename())) {
                    $src = 'src';
                    $itemPath = $item->getPath();
                    $path = ($pos = strripos($itemPath, $src)) !== false ? trim(substr($itemPath, $pos+3, strlen($itemPath)), DIRECTORY_SEPARATOR) : null;
                    $classNameSpace = $this->rootNamespace . str_replace(DIRECTORY_SEPARATOR, '\\', $path);
                    $class = rtrim($classNameSpace, '\\') . '\\' . $item->getBasename('.php');
                    if (class_exists($class)) {
                        try {
                            $reflector = new \ReflectionClass($class);
                            if ($reflector->implementsInterface(InstallerInterface::class) &&
                                $reflector->isInstantiable()
                            ) {
                                $installer[] = $reflector->getName();
                            } else {
                                $this->cliIO->write("Class: $class not instantiable.");
                            }
                        } catch (\Throwable $throwable) {
                            $message = "Message: " . $throwable->getMessage() . " ";
                            $message .= "File: " . $throwable->getFile() . " ";
                            $message .= "Line: " . $throwable->getLine() . " ";
                            $this->cliIO->writeError($message);
                        }
                    } else {
                        $this->cliIO->write("Class: $class not exist. ClassPath -> [$itemPath]. resolvePath -> [$path]");
                    }
                }
            }
        }
        return $installer;
    }

    public function isSupported()
    {
        return !empty($this->installers);
    }

    /**
     * @return PackageInterface
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * @return array
     */
    public function getInstallers()
    {
        return $this->installers;
    }

    public function getInstaller($name)
    {
        return isset($this->installers[$name]) ? $this->installers[$name] : null;
    }

    /**
     * @return string
     */
    public function getRootNamespace()
    {
        return $this->rootNamespace;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

}
