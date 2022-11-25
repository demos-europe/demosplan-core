<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use Exception;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\DataGeneratorInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\FakeDataGeneratorFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use function fopen;
use function fwrite;
use function in_array;
use function is_dir;
use function strrpos;
use function substr;

class ReplaceFilesCommand extends CoreCommand
{
    public static $defaultName = 'dplan:data:replace-files';

    /**
     * @var FileRepository
     */
    private $fileRepository;

    /**
     * @var FakeDataGeneratorFactory
     */
    private $generatorFactory;

    public function __construct(
        FakeDataGeneratorFactory $generatorFactory,
        FileRepository $fileRepository,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->fileRepository = $fileRepository;
        $this->generatorFactory = $generatorFactory;
    }

    public function configure(): void
    {
        $this->addArgument(
            'directory',
            InputArgument::REQUIRED,
            'The path/directory (absolute or relative) for the new files to be generated into according to their own relative file paths.'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Just list the files that would be replaced without replacing them'
        );

        parent::configure();
    }

    /**
     * @throws InvalidDataException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = $this->setupIo($input, $output, true, 'replace-files.log');

        $files = $this->fileRepository->findAll();
        $dryRun = $input->getOption('dry-run');
        $directory = $input->getArgument('directory');

        // total amount of files
        $countFiles = count($files);
        $output->writeln("Files found: {$countFiles}");

        foreach ($files as $number => $file) {
            if (0 === $file->getSize()) {
                $output->warning("Skipping {$file->getId()}, missing size");

                continue;
            }
            $slot = '#'.$number.' (of '.$countFiles.')';

            $this->generateDummyForFile($file, $slot, $output, $dryRun, $directory);
        }

        return 0;
    }

    /**
     * @throws InvalidDataException
     */
    private function generateDummyForFile(
        File $file,
        string $slot,
        SymfonyStyle $output,
        bool $dryRun,
        string $directory
    ): void {
        $filename = $file->getFilename();

        if (strrpos($filename, 'â')) {
            $filename = 'Corrupted file name, most likely from wrong encoding';
        }

        $extension = mb_strtolower(substr($filename, strrpos($filename, '.') + 1));

        if (!in_array($extension, FakeDataGeneratorFactory::FAKEABLE_EXTENSIONS, true)) {
            $output->warning("Non-fakable file extension: {$extension}");

            return;
        }

        $output->writeln($slot.' '.$filename);

        if (!$dryRun) {
            $this->generateFakeFile($file, $extension, $directory, $output);
        }
    }

    private function generateFakeFile(
        File $file,
        string $targetFormat,
        string $targetDirectory,
        SymfonyStyle $output
    ): void {
        try {
            $uploadedFile = fopen($this->getStoragePath($file, $targetDirectory), 'wb+');

            /** @var DataGeneratorInterface $generator */
            $generator = $this->generatorFactory->getFormat($targetFormat);
            fwrite($uploadedFile, $generator->generate($file->getSize()));

            fclose($uploadedFile);
            $output->success('Successful generation!');
        } catch (InvalidDataException $invalidDataException) {
            $output->error("Invalid data: {$invalidDataException->getMessage()}");
        } catch (Exception $exception) {
            $output->error($exception->getMessage());
        }
    }

    /**
     * @throws RuntimeException|InvalidDataException
     */
    private function getStoragePath(File $file, string $targetDirectory): string
    {
        if ('/' !== substr($targetDirectory, -1)) {
            $targetDirectory .= '/';
        }
        $dir = $targetDirectory;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $fileDir = $dir.$file->getPath();

        if (!is_dir($fileDir) && !mkdir($fileDir, 0755, true) && !is_dir($fileDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $fileDir));
        }

        return $dir.$file->getFilePathWithHash();
    }
}
