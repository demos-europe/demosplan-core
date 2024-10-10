<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities;

use RuntimeException;

/**
 * Class DemosPlanPaths.
 *
 * Convenience methods for accessing common paths from inside the application
 */
class DemosPlanPath
{
    private static $projectPathFromConfig = '';

    /**
     * This is called during kernel initialization to configure project paths.
     */
    public static function setProjectPathFromConfig(string $projectPathFromConfig)
    {
        self::$projectPathFromConfig = $projectPathFromConfig;
    }

    /**
     * Returns the path to the repository! root.
     *
     * Can optionally receive a path / filename segment which will be appended
     * ensuring that necessary directory separators are in the right places.
     *
     * @param string $path optional path which will be appended
     *
     * @return string path
     */
    public static function getRootPath($path = ''): string
    {
        return dirname(__FILE__, 4).DIRECTORY_SEPARATOR.$path;
    }

    public static function getConfigPath(string $path = ''): string
    {
        return self::getRootPath('config'.('' !== $path ? "/{$path}" : ''));
    }

    /**
     * Returns a path to the system temp directory.
     *
     * Can optionally receive a path / filename segment which will be appended
     * ensuring that necessary directory separators are in the right places.
     *
     * @param string $path optional path which will be appended
     *
     * @return string path
     *
     * @throws RuntimeException
     */
    public static function getTemporaryPath(string $path = ''): string
    {
        $tempDir = sys_get_temp_dir();
        if (!is_dir($tempDir)) {
            throw new RuntimeException('Could not determine temporary directory');
        }

        return $tempDir.DIRECTORY_SEPARATOR.$path;
    }

    public static function getSystemFilesPath(string $path = ''): string
    {
        return 'system'.DIRECTORY_SEPARATOR.$path;
    }

    public static function makeTemporaryDir(string $path = '', $mode = 0777): string
    {
        $tempDir = static::getTemporaryPath($path);

        if (!is_dir($tempDir) && !mkdir($tempDir, $mode, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }

        return $tempDir;
    }

    /**
     * @throws RuntimeException if projectPathFromConfig not configured
     */
    public static function getProjectPath(string $path = ''): string
    {
        if (self::isInstalledAsLib()) {
            return dirname(self::getRootPath(), 3).DIRECTORY_SEPARATOR.$path;
        }

        $projectPath = self::$projectPathFromConfig;

        return self::getRootPath("{$projectPath}/{$path}");
    }

    /**
     * Gets the path to the tests folder.
     * If a path is send as a parameter it will be concatenated to the tests folder's path.
     * In no case will there be a trailing slash.
     */
    public static function getTestPath(string $path = ''): string
    {
        return '' !== $path
            ? self::getRootPath('tests').'/'.$path
            : self::getRootPath('tests');
    }

    public static function isInstalledAsLib(): bool
    {
        return !is_dir(self::getRootPath('vendor'));
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $path
     *
     * @throws RuntimeException If anything fails
     */
    public static function recursiveRemoveLocalPath($path)
    {
        if (is_dir($path)) {
            $objects = scandir($path, SCANDIR_SORT_NONE);

            foreach ($objects as $object) {
                if ('.' !== $object && '..' !== $object) {
                    $pathToObject = $path.DIRECTORY_SEPARATOR.$object;

                    if ('dir' === filetype($pathToObject)) {
                        self::recursiveRemoveLocalPath($pathToObject);
                    } else {
                        // local file is valid, no need for flysystem
                        $deleted = unlink($pathToObject);
                        if (false === $deleted) {
                            throw new RuntimeException('Could not delete File recursively', [$pathToObject]);
                        }
                    }
                }
            }

            reset($objects);
            $deleted = rmdir($path);

            if (false === $deleted) {
                throw new RuntimeException('Could not delete Dir '.$path);
            }
        }
    }
}
