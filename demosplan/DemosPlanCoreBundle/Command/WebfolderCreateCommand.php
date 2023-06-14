<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * dplan:webfolder:create.
 *
 * Creates webfolder with project overrides
 */
class WebfolderCreateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:webfolder:create';
    protected static $defaultDescription = 'Create web folder with project overrides';

    public function configure(): void
    {
        $this
            ->setHelp(<<<EOT
Create web folder with project overrides. Usage:
    php app/console dplan:webfolder:create
EOT
            );
    }

    /**
     * Create Webfolder.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = $this->setupIo($input, $output, false);

        $corePath = DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle');
        $projectPath = getcwd().'/';

        // Pfad des web-Verzeichnisses im DemosPlanCore
        $coreWebsource = realpath($corePath.'/Resources/public');
        // Pfad des web-Verzeichnisses im jeweiligen Projekt,
        $projectWebsource = realpath(
            $projectPath.'src/demosplanProject/DemosPlanProjectCoreBundle/Resources/public'
        );
        // Pfad des resultierenden web-Verzeichnisses, htdocroot des Projektes
        $projectWebfolderTarget = realpath($projectPath.'web');

        // clean target folder from old files
        $this->cleanWebFolder($projectWebfolderTarget, $output);

        // Kopiere die Quellen aus dem DemosPlanCore
        // Lasse den Ordner css aus, der wird aus dem Sass reingezogen
        DemosFilesystem::copyr(
            $coreWebsource,
            $projectWebfolderTarget,
            ['css']
        );

        // Kopiere die statischen CSS-Dateien aus dem DemosPlanCore
        $this->copyStaticCss($coreWebsource, $projectWebfolderTarget);

        // Wenn ein Projektspezifischer Ordner vorhanden ist,
        // kopiere die Quellen aus dem DemosPlanProject
        // Lasse den Ordner css aus, der wird aus dem Sass reingezogen
        if (is_dir($projectWebsource)) {
            DemosFilesystem::copyr(
                $projectWebsource,
                $projectWebfolderTarget,
                ['css']
            );
        }

        // Wenn ein Projektspezifischer Ordner vorhanden ist,
        // kopiere die statischen CSS-Dateien aus dem DemosPlanProject
        if (is_dir($projectWebsource)) {
            $this->copyStaticCss($projectWebsource, $projectWebfolderTarget);
        }

        return 0;
    }

    /**
     * Kopiere die statischen CSS-Dateien in den Webordner.
     *
     * @param string $source
     * @param string $target
     */
    protected function copyStaticCss($source, $target)
    {
        $targetDir = $target.'/css/';

        if (!@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
        }

        foreach (glob($source.'/css/*.css') as $filename) {
            $targetFile = basename($filename);
            echo 'Copy '.$filename.' to '.$targetDir.$targetFile."\n";
            copy($filename, $targetDir.$targetFile);
        }
    }

    /**
     * Delete files by filetype.
     *
     * @param string       $targetDir
     * @param SymfonyStyle $output
     *
     * @return bool
     */
    protected function cleanWebFolder($targetDir, $output)
    {
        if (!is_dir($targetDir)) {
            return false;
        }

        try {
            $fs = new DemosFilesystem();

            $output->writeln('Delete existing CSS Files, keep style.css');
            if ($fs->exists($targetDir.'/css/style.css')) {
                $fs->rename(
                    $targetDir.'/css/style.css',
                    $targetDir.'/css/style.bak'
                );
            }
            // delete files
            $fs->remove(glob($targetDir.'/css/*.css'));
            // restore style.css
            if ($fs->exists($targetDir.'/css/style.bak')) {
                $fs->rename(
                    $targetDir.'/css/style.bak',
                    $targetDir.'/css/style.css'
                );
            }

            collect(['js', 'img', 'pdf', 'video', 'fonts'])
                ->map(function ($folder) use ($fs, $targetDir, $output) {
                    $dirToDelete = $targetDir.'/'.$folder;
                    $output->writeln('DeleteFolder '.$dirToDelete);
                    $fs->remove(glob($dirToDelete));
                });
        } catch (Exception $e) {
            $output->error('Error deleting: ');
        }

        return true;
    }
}
