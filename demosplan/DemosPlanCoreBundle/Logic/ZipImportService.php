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
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Patchwork\Utf8;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Webmozart\Assert\Assert;
use ZipArchive;

use function is_string;

use const DIRECTORY_SEPARATOR;

class ZipImportService
{
    private Finder $finder;

    public function __construct(
        private readonly CurrentContextProvider $currentContextProvider,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly TranslatorInterface $translator,
        private readonly FileService $fileService
    ) {
        $this->finder = Finder::create();
    }

    /**
     * @throws DemosException
     */
    public function createFileMapFromZip(SplFileInfo $fileInfo, string $procedureId): array
    {
        try {
            $fileMap = [];
            $extractDir = $this->extractZipToTempFolder($fileInfo, $procedureId);
            Assert::notNull($extractDir);
            $this->finder->files()->in($extractDir);
            if ($this->finder->hasResults()) {
                /** @var SplFileInfo $file */
                foreach ($this->finder as $file) {
                    $extension = $file->getExtension();
                    $fileNameParts = explode('_', $file->getFilename());
                    $fileHash = reset($fileNameParts);
                    Assert::string($fileHash);
                    if ('pdf' === $extension) {
                        $fileMap[$fileHash] = $this->saveAsDemosFile($file, $procedureId);
                    }
                    // fixme txt case: throw exception, return violation - or ignore?
                    if ('txt' === $extension || 'xlsx' === $extension) {
                        $fileMap[$fileHash] = $file;
                    }
                }
            }

            return $fileMap;
        } catch (Throwable $e) {
            $this->logger->error('statement import failed', ['exception' => $e]);
            $this->messageBag->add(
                'error',
                $this->translator->trans('error.file.could.not.be.read'),
                ['files' => implode(array_keys($fileMap))]
            );

            throw new DemosException('statement import failed');
        }

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
    public function extractZipToTempFolder(SplFileInfo $fileInfo, string $procedureId): ?string
    {
        $fn = $fileInfo->getRealPath();
        $tempFileFolder = $fileInfo->getFilename();
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
                $extractDir = $this->getStatementAttachmentImportDir($procedureId, $tempFileFolder, $user);
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
                    .' FileNameOrig: '
                    .DemosPlanTools::varExport($filenameOrig, true)
                );
                $zip->renameIndex($indexInZipFile, $dirname.'/'.$filename);
                $zip->extractTo($extractDir, $zip->getNameIndex($indexInZipFile));
            }
        }
        if (ZipArchive::ER_OPEN !== $res) {
            $zip->close();
        }

        $extractedTo = null;
        if (isset($extractDir, $dirname) && is_string($extractDir) && is_string($dirname)) {
            $extractedTo = $extractDir.DIRECTORY_SEPARATOR.$dirname;
        }

        return $extractedTo;
    }

    public function getStatementAttachmentImportDir(string $procedureId, string $tempfileFolder,  UserInterface $user): string
    {
        $tmpDir = sys_get_temp_dir().'/'.$user->getId().'/'.$procedureId.'/'.$tempfileFolder;
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
        }

        return $tmpDir;
    }
}
