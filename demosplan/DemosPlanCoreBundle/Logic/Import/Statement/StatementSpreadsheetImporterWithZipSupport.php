<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\ZipImportService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class StatementSpreadsheetImporterWithZipSupport extends StatementSpreadsheetImporter
{
    private ?array $fileMap = null;

    public function __construct(
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        ElementsService $elementsService,
        OrgaService $orgaService,
        StatementCopier $statementCopier,
        StatementService $statementService,
        TranslatorInterface $translator,
        ValidatorInterface $validator,
        private readonly ZipImportService $zipImportService,
        private readonly FileService $fileService,
        private readonly EntityManagerInterface $entityManager,
        private readonly StatementAttachmentService $statementAttachmentService
    ) {
        parent::__construct($currentProcedureService, $currentUser, $elementsService, $orgaService, $statementCopier, $statementService, $translator, $validator, $this->entityManager);
    }

    private function getStatementFromRowBuilder(StatementFromRowBuilder $baseStatementBuilder): StatementFromRowBuilderWithZipSupport
    {
        return new StatementFromRowBuilderWithZipSupport(
            $this->validator,
            $this->getFileMap(),
            $this->fileService,
            $this->entityManager,
            $baseStatementBuilder,
            $this->statementAttachmentService
        );
    }

    protected function getColumnMapping(): array
    {
        [$baseColumns, $builder] = parent::getColumnMapping();
        $builder = $this->getStatementFromRowBuilder($builder);
        // add new columns
        $baseColumns['Referenzen auf Anhänge'] = $builder->setFileReferences(...);
        $baseColumns['Originalanhang'] = $builder->setOriginalFileReferences(...);

        return [$baseColumns, $builder];
    }

    /**
     * @throws UserNotFoundException
     * @throws CopyException
     * @throws UnexpectedWorksheetNameException
     * @throws DemosException
     * @throws StatementElementNotFoundException
     * @throws MissingPostParameterException
     * @throws InvalidDataException
     */
    public function process(SplFileInfo $workbook): void
    {
        $this->fileMap = $this->zipImportService->createFileMapFromZip(
            $workbook,
            $this->currentProcedureService->getProcedure()->getId()
        );
        Assert::minCount($this->fileMap, 1, 'Zip file does not contain any Files');
        $xlsFiles = array_filter(
            $this->fileMap,
            static fn (File|SplFileInfo $entry): bool => $entry instanceof SplFileInfo && 'xlsx' === $entry->getExtension()
        );
        Assert::count($xlsFiles, 1, 'Only %d xls File per Zip supported, got %d');

        parent::process(reset($xlsFiles));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getFileMap(): array
    {
        Assert::notNull($this->fileMap);

        return $this->fileMap;
    }
}
