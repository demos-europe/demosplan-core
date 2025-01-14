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
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Psr\Log\LoggerInterface;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use ValueError;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;
use ZipArchive;

use function is_string;
use function Symfony\Component\String\u;

use const DIRECTORY_SEPARATOR;

class ZipImportService
{
    private readonly Finder $finder;
    private const ZIP_CONTAINS_ERROR_TXT_FILE = 'File is not valid. It contains an errors.txt file indicating a faulty export';
    private const IMPORT_FILE_TYPES_TO_NOT_BE_SAVED = [
        'xlsx',
    ];

    public function __construct(
        private readonly CurrentContextProvider $currentContextProvider,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly TranslatorInterface $translator,
        private readonly FileService $fileService,
    ) {
        $this->finder = Finder::create();
    }

    /**
     * @return array<string, File|SplFileInfo> // Filehash => File || FileName => SplFileInfo
     *
     * @throws InvalidDataException
     * @throws InvalidArgumentException
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

                    if (!is_array($fileNameParts) || !is_string($fileNameParts[0])) {
                        $this->logger->error('Filename could not be exploded.');
                        throw new InvalidDataException('Filename of attachments in ZIP could not be exploded.');
                    }

                    $fileHash = $fileNameParts[0];
                    Assert::notContains('errors.txt', $file->getFilename(), self::ZIP_CONTAINS_ERROR_TXT_FILE);

                    if (in_array($extension, self::IMPORT_FILE_TYPES_TO_NOT_BE_SAVED, true)) {
                        $fileMap[$fileHash] = $file;
                    } else {
                        $fileMap[$fileHash] = $this->fileService->saveTemporaryLocalFile(
                            $file->getRealPath(),
                            $file->getFilename(),
                            null,
                            $procedureId,
                            FileService::VIRUSCHECK_ASYNC,
                            $this->fileService->createHash()
                        );
                    }
                }
            }

            return $fileMap;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('statement import failed', ['exception' => $e]);
            $this->messageBag->add(
                'error',
                $this->translator->trans('error.statements.zip.import.contains.error.textfile')
            );

            throw new DemosException('statement import failed');
        } catch (Throwable $e) {
            $this->logger->error('statement import failed', ['exception' => $e]);
            $this->messageBag->add(
                'error',
                $this->translator->trans('error.file.could.not.be.read'),
                ['files' => implode('', array_keys($fileMap))]
            );

            throw new DemosException('statement import failed');
        }
    }

    /**
     * @throws InvalidDataException|ValueError
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

                $filename = u($fileInfo['basename'])->ascii()->replace(' ', '_')->toString();
                $dirname = u($fileInfo['dirname'])->ascii()->replace(' ', '_')->toString();

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

                // In case of no valid filename could be determined, create random hash.
                if ('' === $filename) {
                    $filename = md5((string) random_int(0, 9999));
                    $this->logger->warning(
                        'No valid name could be found. RandomHash: '
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
        if (isset($extractDir, $dirname) && is_string($dirname)) {
            $extractedTo = $extractDir.DIRECTORY_SEPARATOR.$dirname;
        }

        return $extractedTo;
    }

    /**
     * @throws InvalidDataException
     */
    public function getStatementAttachmentImportDir(
        string $procedureId,
        string $tempFileFolder,
        UserInterface $user,
    ): string {
        $tmpDir = DemosPlanPath::getTemporaryPath($user->getId().'/'.$procedureId.'/'.$tempFileFolder);

        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new InvalidDataException('The filename does not exists or is not a directory. Directory was not created');
        }

        return $tmpDir;
    }
}
