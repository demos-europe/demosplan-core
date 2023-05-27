<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class DemosFilesystem extends Filesystem
{
    /**
     * Recursively find pathnames that match a pattern.
     *
     * See {@link http://php.net/manual/en/function.glob.php glob} for more info.
     *
     * @param string $sDir     directory The directory to glob in
     * @param string $sPattern pattern The pattern to match paths against
     * @param int    $nFlags   `glob()` . See {@link http://php.net/manual/en/function.glob.php glob()}.
     *
     * @return array the list of paths that match the pattern
     *
     * @api
     */
    public static function globr($sDir, $sPattern, $nFlags = null)
    {
        if (false == ($aFiles = glob("$sDir/$sPattern", $nFlags))) {
            $aFiles = [];
        }

        if (false != ($aDirs = glob("$sDir/*", GLOB_ONLYDIR))) {
            foreach ($aDirs as $sSubDir) {
                if (is_link($sSubDir)) {
                    continue;
                }

                $aSubFiles = self::globr($sSubDir, $sPattern, $nFlags);
                $aFiles = array_merge($aFiles, $aSubFiles);
            }
        }

        return $aFiles;
    }

    /**
     * Copy recursively files and folders.
     *
     * @param string $src
     * @param string $dst
     * @param array  $excludedDirs
     */
    public static function copyr($src, $dst, $excludedDirs = [])
    {
        $dir = opendir($src);
        // !is_dir needs to checked twice. First check: Only try to create
        // dir if folder does not exist
        // second check: Did something happen during mkdir?
        if (!is_dir($dst) && !mkdir($dst) && !is_dir($dst)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dst));
        }

        while (false !== ($file = readdir($dir))) {
            if (('.' != $file) && ('..' != $file)) {
                if (is_dir($src.'/'.$file)) {
                    if (in_array($file, $excludedDirs)) {
                        continue;
                    }
                    self::copyr($src.'/'.$file, $dst.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }

        closedir($dir);
    }
}
