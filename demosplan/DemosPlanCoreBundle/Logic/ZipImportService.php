<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Patchwork\Utf8;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use ZipArchive;

use function is_string;

use const DIRECTORY_SEPARATOR;

class ZipImportService
{
    private Finder $finder;

    public function __construct(
        private readonly CurrentContextProvider $currentContextProvider,
        private readonly LoggerInterface $logger,
        private readonly FileService $fileService
    ) {
        $this->finder = Finder::create();
    }

    /**
     * @throws Exception|Throwable
     */
    public function doEverythingWithZip(FileInfo $fileInfo, string $procedureId): array
    {
        $fileMap = [];
        $extractDir = $this->extractZipToTempFolder($fileInfo, $procedureId);
        $this->finder->files()->in($extractDir);
        if ($this->finder->hasResults()) {
            /** @var SplFileInfo $file */
            foreach ($this->finder as $file) {
                $extension = $file->getExtension();
                if ('pdf' === $extension) {
                    $fileMap[$file->getFilename()] = $this->saveAsDemosFile($file, $procedureId);
                }
                if ('txt' === $extension || 'xlsx' === $extension) {
                    $fileMap[$file->getFilename()] = $file;
                }
            }
        }
        // delete zip after everything got extracted.
        $this->fileService->deleteFile($fileInfo->getHash());

        return $fileMap;
    }

    /**
     * @throws Throwable
     */
    public function saveAsDemosFile(SplFileInfo $file, string $procedureId): File
    {
        $fileHash = $this->fileService->createHash();

        return $this->fileService->saveTemporaryFile(
            $file->getRealPath(), // filename might be missing here...
            $file->getFilename(),
            null,
            $procedureId,
            FileService::VIRUSCHECK_ASYNC,
            $fileHash
        );
    }

    /**
     * @throws Exception
     */
    public function extractZipToTempFolder(FileInfo $fileInfo, string $procedureId): ?string
    {
        $fn = $fileInfo->getAbsolutePath();
        $zip = new ZipArchive();
        $res = $zip->open($fn);
        if (true === $res) {
            for ($indexInZipFile = 0; $indexInZipFile < $zip->numFiles; ++$indexInZipFile) {
                $filenameOrig = $zip->getNameIndex($indexInZipFile);
                if (false === $filenameOrig || str_ends_with($filenameOrig, '/')) {
                    continue;
                }

                $fileInfo = pathinfo($filenameOrig);

                // T5659 only filter filenames for bad chars, do not translit
                $filename = Utf8::filter($fileInfo['basename']);
                $dirname = Utf8::filter($fileInfo['dirname']);

                $user = $this->currentContextProvider->getCurrentUser();
                $extractDir = $this->getElementImportDir($procedureId, $user);
                // T8843 zip-slip: check whether path is in valid location
                $destination = $extractDir.'/'.$dirname;
                // if path contains any relative path immediately skip file
                if (0 !== mb_substr_count($destination, '../')) {
                    $this->logger->error(
                        'Possible Zip-slip-Attack. File not extracted. Destination:'
                        .DemosPlanTools::varExport($destination, true)
                    );
                    continue;
                }

                // Falls gar kein valider Filename ermittelt werden konnte, lieber einen Hash als nix
                if ('' === $filename) {
                    $filename = md5((string) random_int(0, 9999));
                    $this->logger->warning(
                        'Es konnte via kein gültiger Name gefunden werden. RandomHash: '
                        .DemosPlanTools::varExport($filename, true)
                    );
                }

                $this->logger->info(
                    'DocumentImport set Filename '
                    .DemosPlanTools::varExport($filename, true)
                    .' Dirname: '
                    .DemosPlanTools::varExport($dirname, true)
                    .' Orig base64encoded: '
                    .DemosPlanTools::varExport($filenameOrig, true)
                );
                $zip->renameIndex($indexInZipFile, $dirname.'/'.$filename);
                $zip->extractTo($extractDir, $zip->getNameIndex($indexInZipFile));
            }
        }
        $zip->close();

        $returnVal = null;
        if (isset($extractDir, $dirname) && is_string($extractDir) && is_string($dirname)) {
            $returnVal = $extractDir.DIRECTORY_SEPARATOR.$dirname;
        }

        return $returnVal;
    }

    public function getElementImportDir(string $procedureId, UserInterface $user): string
    {
        $tmpDir = sys_get_temp_dir().'/'.$user->getId().'/'.$procedureId;
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
        }

        return $tmpDir;
    }
}
