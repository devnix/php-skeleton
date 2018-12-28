<?php
namespace Koriym\PhpSkeleton;

use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Script\Event;

class Installer
{
    /**
     * @var array
     */
    private static $packageName;

    /**
     * @var string
     */
    private static $name;

    /**
     * @var string
     */
    private static $email;

    public static function preInstall(Event $event)
    {
        $io = $event->getIO();
        $vendorClass = self::ask($io, 'What is the vendor name ?', 'MyVendor');
        $packageClass = self::ask($io, 'What is the package name ?', 'MyPackage');
        self::$name = self::ask($io, 'What is your name ?', self::getUserName());
        self::$email = self::ask($io, 'What is your emaill address ?', self::getUserEmail());
        $packageName = sprintf('%s/%s', self::camel2dashed($vendorClass), self::camel2dashed($packageClass));
        $json = new JsonFile(Factory::getComposerFile());
        $composerDefinition = self::getDefinition($vendorClass, $packageClass, $packageName, $json);
        self::$packageName = [$vendorClass, $packageClass];
        // Update composer definition
        $json->write($composerDefinition);
        $io->write("<info>composer.json for {$composerDefinition['name']} is created.\n</info>");
    }

    public static function postInstall(Event $event = null)
    {
        unset($event);
        list($vendorName, $packageName) = self::$packageName;
        $skeletonRoot = dirname(__DIR__);
        self::recursiveJob("{$skeletonRoot}", self::rename($vendorName, $packageName));
        //mv
        $skeletonPhp = __DIR__ . '/Skeleton.php';
        copy($skeletonPhp, "{$skeletonRoot}/src/{$packageName}.php");
        $skeletoTest = "{$skeletonRoot}/tests/SkeletonTest.php";
        copy($skeletoTest, "{$skeletonRoot}/tests/{$packageName}Test.php");
        // remove installer files
        unlink($skeletonRoot . '/README.md');
        unlink($skeletonPhp);
        unlink($skeletoTest);
        unlink(__FILE__);
    }

    private static function ask(IOInterface $io, string $question, string $default) : string
    {
        $ask = [
            sprintf("\n<question>%s</question>\n", $question),
            sprintf("\n(<comment>%s</comment>):", $default)
        ];
        $answer = $io->ask($ask, $default);

        return $answer;
    }

    private static function recursiveJob(string $path, callable $job)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $job($file);
        }
    }

    private static function getDefinition(string $vendor, string $package, string $packageName, JsonFile $json) : array
    {
        $composerDefinition = $json->read();
        unset(
            $composerDefinition['autoload']['files'],
            $composerDefinition['scripts']['pre-install-cmd'],
            $composerDefinition['scripts']['pre-update-cmd'],
            $composerDefinition['scripts']['post-create-project-cmd'],
            $composerDefinition['keywords'],
            $composerDefinition['homepage']
        );

        $composerDefinition['name'] = $packageName;
        $composerDefinition['authors'] = [
            [
                'name' => self::$name,
                'email' => self::$email
            ]
        ];
        $composerDefinition['description'] = '';
        $composerDefinition['autoload']['psr-4'] = ["{$vendor}\\{$package}\\" => 'src/'];

        return $composerDefinition;
    }

    private static function rename(string $vendor, string $package) : \Closure
    {
        $jobRename = function (\SplFileInfo $file) use ($vendor, $package) {
            $fineName = $file->getFilename();
            if ($file->isDir() || strpos($fineName, '.') === 0 || ! is_writable($file)) {
                return;
            }
            $contents = file_get_contents($file);
            $contents = str_replace('__Vendor__', "{$vendor}", $contents);
            $contents = str_replace('__Package__', "{$package}", $contents);
            $contents = str_replace('__year__', date('Y'), $contents);
            $contents = str_replace('__name__', self::$name, $contents);
            $contents = str_replace('__PackageVarName__', lcfirst($package), $contents);
            file_put_contents($file, $contents);
        };

        return $jobRename;
    }

    private static function camel2dashed(string $name) : string
    {
        return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $name));
    }

    private static function getUserName() : string
    {
        $author = `git config --global user.name`;

        return $author ? trim($author) : '';
    }

    private static function getUserEmail() : string
    {
        $email = `git config --global user.email`;

        return $email ? trim($email) : '';
    }
}
