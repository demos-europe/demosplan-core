<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;


use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatementFromRowBuilderWithZipSupport extends AbstractStatementFromRowBuilder
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        protected readonly array $fileMap,
        protected readonly FileService $fileService,
        private readonly EntityManagerInterface $entityManager,
        private readonly StatementFromRowBuilder $baseStatementFromRowBuilder,
        private readonly StatementAttachmentService $statementAttachmentService
    ) {
        parent::__construct();
    }

    public function setOriginalFileReferences(Cell $cell): ?ConstraintViolationListInterface
    {
        // early return in case no file-reference is found
        $cellValue = $cell->getValue();
        if (null === $cellValue || '' === $cellValue) {
            return null;
        }

        $collectionContent = [$cellValue =>  new Required(new Type(File::class))];

        $keyConstraint = new Collection(
            $collectionContent,
            null,
            null,
            true,
            false
        );
        $violations = $this->validator->validate(
            $this->fileMap,
            [$keyConstraint]
        );
        if (0 !== $violations->count()) {
            return $violations;
        }

        $statement = $this->baseStatementFromRowBuilder->statement;
        /** @var File $fileEntity */
        $fileEntity = $this->fileMap[$cellValue];
        /**
         * The statement has to be persisted now in order to get an id.
         * This id needs to be used to persist a new { @link StatementAttachment }.
         * The StatementAttachment can not be flushed at this point as the also persisted statement
         * is not functional yet and will trigger constraints.
         */
        $this->entityManager->persist($statement);
        $statementAttachmentList = new ArrayCollection();
        $statementAttachment = $this->statementAttachmentService->createOriginalAttachment($statement, $fileEntity);
        $this->entityManager->persist($statementAttachment);
        $statementAttachmentList->add($statementAttachment);
        $statement->setAttachments($statementAttachmentList);

        return null;
    }

    public function setFileReferences(Cell $cell): ?ConstraintViolationListInterface
    {
        // early return in case no file-reference is found
        $cellValue = $cell->getValue();
        if (null === $cellValue || '' === $cellValue) {
            return null;
        }

        $references = explode(', ', $cell->getValue());

        $collectionContent = array_fill_keys(
            $references,
            new Required(
                new Type(File::class)
            )
        );
        $keyConstraint = new Collection(
            $collectionContent,
            null,
            null,
            true,
            false
       );
        $violations = $this->validator->validate(
            $this->fileMap,
            [$keyConstraint]
        );
        if (0 !== $violations->count()) {
            return $violations;
        }

        $statement = $this->baseStatementFromRowBuilder->statement;
        /**
         * The statement has to be persisted now in order to get an id.
         * This id needs to be used to persist a new fileContainer.
         * The fileContainer can not be flushed at this point as the also persisted statement
         * is not functional yet and will trigger constraints.
         */
        $this->entityManager->persist($statement);
        foreach ($references as $fileMapKey) {
            /** @var File $fileEntity */
            $fileEntity = $this->fileMap[$fileMapKey];
            $fileContainer = $this->fileService->addStatementFileContainer(
                $statement->getId(),
                $fileEntity->getId(),
                $fileEntity->getFileString(),
                false
            );
            $violations = $this->validator->validate($fileContainer, [new NotNull()]);
            if (0 !== $violations->count()) {
                return $violations;
            }
        }

        return null;
    }

    public function setExternId(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setExternId($cell);
    }

    public function setText(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setText($cell);
    }

    public function setPlanningDocumentCategoryName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setPlanningDocumentCategoryName($cell);
    }

    public function setOrgaName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setOrgaName($cell);
    }

    public function setDepartmentName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setDepartmentName($cell);
    }

    public function setAuthorName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setAuthorName($cell);
    }

    public function setSubmitterName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitterName($cell);
    }

    public function setSubmiterEmailAddress(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmiterEmailAddress($cell);
    }

    public function setSubmitterStreetName(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitterStreetName($cell);
    }

    public function setSubmitterHouseNumber(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitterHouseNumber($cell);
    }

    public function setSubmitterPostalCode(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitterPostalCode($cell);
    }

    public function setSubmitterCity(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitterCity($cell);
    }

    public function setSubmitDate(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setSubmitDate($cell);
    }

    public function setAuthoredDate(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setAuthoredDate($cell);
    }

    public function setInternId(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setInternId($cell);
    }

    public function setMemo(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setMemo($cell);
    }

    public function setFeedback(Cell $cell): ?ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->setFeedback($cell);
    }

    public function setNumberOfAnonymVotes(Cell $cell): ?ConstraintViolationListInterface
    {
       return $this->baseStatementFromRowBuilder->setNumberOfAnonymVotes($cell);
    }

    public function buildStatementAndReset(): StatementInterface|ConstraintViolationListInterface
    {
        return $this->baseStatementFromRowBuilder->buildStatementAndReset();
    }
}
