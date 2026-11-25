<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ManualOriginalStatementCreatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\OcxEntryFailedException;
use demosplan\DemosPlanCoreBundle\Exception\OcxProcessingException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementToLegacyConverter;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function array_map;
use function array_unique;
use function array_values;
use function iterator_to_array;
use function mb_substr;

/**
 * Creates the statement of an OCX `statement` entry the way the statement import does: an original
 * statement and its assessable copy, the copy split into segments with tags by {@link OcxSectionWriter}.
 *
 * Each entry is its own transaction. A failed entry leaves nothing behind except the entities still
 * attached to the entity manager, which the caller has to clear.
 */
class OcxStatementCreator implements ResetInterface
{
    private const SUBMITTER_ORGANISATION = 'organisation';
    private const SUBMITTER_ANONYMOUS = 'anonymous';

    public function __construct(
        private readonly ElementsService $elementsService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileService $fileService,
        private readonly OcxRichText $richText,
        private readonly OcxSectionWriter $sectionWriter,
        private readonly StatementAttachmentService $statementAttachmentService,
        private readonly StatementCopier $statementCopier,
        private readonly StatementService $statementService,
        private readonly StatementToLegacyConverter $statementToLegacyConverter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @var array<string, int> last externId number handed out, by procedure id
     */
    private array $lastExternIds = [];

    public function reset(): void
    {
        $this->lastExternIds = [];
    }

    /**
     * @param array<string, mixed>                                 $entry               a `statementEntry` valid against the OCX schema
     * @param array<string, array{sha256: string, fileId: string}> $receivedAttachments attachments of the delivery by OCX id
     *
     * @throws OcxEntryFailedException
     * @throws Throwable
     */
    public function create(Procedure $procedure, User $sender, array $entry, array $receivedAttachments): OcxCreatedStatement
    {
        $existing = $this->findExisting($procedure, $entry['senderId']);
        if (null !== $existing) {
            return $existing;
        }

        $context = new OcxEntryContext($procedure->getId(), $receivedAttachments);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $original = $this->buildOriginal($procedure, $sender, $entry, $context);
            // the file containers below need the id assigned on persist
            $this->entityManager->persist($original);
            $this->attachFiles($original, $entry['attachments'], $context);
            $this->validate($original);
            $this->entityManager->flush();

            $copy = $this->statementCopier->copyStatementObjectWithinProcedureWithRelatedFiles($original, false, true);
            $response = $this->richText->filter($entry['response'], '/response', $context);
            if ('' !== $response) {
                $copy->setRecommendation($response);
            }
            $this->sectionWriter->write($copy, $entry, $context);
            $this->entityManager->flush();

            // the report stores the legacy array as its message
            $this->statementService->addReportNewStatement($this->statementToLegacyConverter->convert($copy));
            $this->eventDispatcher->dispatch(
                new ManualOriginalStatementCreatedEvent($copy),
                ManualOriginalStatementCreatedEventInterface::class,
            );
            $this->entityManager->flush();

            $connection->commit();
        } catch (Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }

        return new OcxCreatedStatement(true, $copy->getId(), $copy->getExternId(), $context->getWarnings(), $context->getUsedFileIds());
    }

    /**
     * Any statement imported from the same sender id counts, also one whose copy was deleted since.
     */
    private function findExisting(Procedure $procedure, string $senderId): ?OcxCreatedStatement
    {
        $statements = $this->entityManager->getRepository(Statement::class)->findBy([
            'procedure'         => $procedure,
            'sourceStatementId' => $senderId,
        ]);
        if ([] === $statements) {
            return null;
        }

        foreach ($statements as $statement) {
            if ($statement->isAssessmentTableRow()) {
                return new OcxCreatedStatement(false, $statement->getId(), $statement->getExternId());
            }
        }

        return new OcxCreatedStatement(false, null, null);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function buildOriginal(Procedure $procedure, User $sender, array $entry, OcxEntryContext $context): Statement
    {
        $statement = new Statement();
        $meta = $statement->getMeta();

        $statement->setProcedure($procedure);
        $statement->setManual();
        $statement->setPhaseDefinition($procedure->getPhaseObject()->getPhaseDefinition());
        $statement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        $statement->setExternId($this->nextExternId($procedure));
        $statement->setInternId($entry['receiptNumber']);
        $statement->setSourceStatementId($entry['senderId']);
        $statement->setSourceSystem(mb_substr(Statement::SOURCE_SYSTEM_INTAKE_PREFIX.$sender->getLogin(), 0, 255));

        $statement->setText($this->richText->filter($entry['text'], '/text', $context));
        $statement->setMemo($this->richText->filter($entry['note'], '/note', $context));
        $statement->setNumberOfAnonymVotes($entry['supporterCount']);
        $statement->setSubmit(Carbon::parse($entry['submittedAt'])->toDate());
        $meta->setAuthoredDate(null === $entry['authoredAt'] ? null : Carbon::parse($entry['authoredAt'])->toDate());

        $this->applySubmitter($statement, $meta, $entry['submitter']);
        $meta->setSubmitOrgaId($sender->getOrganisationId());

        $gdprConsent = new GdprConsent();
        $gdprConsent->setStatement($statement);
        $statement->setGdprConsent($gdprConsent);

        $statement->setElement($this->resolveElement($procedure, $entry['planningReference'], $context));

        return $statement;
    }

    /**
     * People and anonymous submitters become citizens of the anonymous organisation, organisations
     * public agencies, the same split as in the statement import.
     *
     * @param array<string, ?string> $submitter
     */
    private function applySubmitter(Statement $statement, StatementMeta $meta, array $submitter): void
    {
        $meta->setSubmitName($submitter['name'] ?? '');
        $meta->setAuthorName($submitter['authorName'] ?? $submitter['name'] ?? '');
        $meta->setOrgaEmail($submitter['email'] ?? '');
        $meta->setOrgaStreet($submitter['street'] ?? '');
        $meta->setHouseNumber($submitter['houseNumber'] ?? '');
        $meta->setOrgaPostalCode($submitter['postalCode'] ?? '');
        $meta->setOrgaCity($submitter['city'] ?? '');

        if (self::SUBMITTER_ORGANISATION === $submitter['type']) {
            $statement->setPublicStatement(StatementInterface::INTERNAL);
            $meta->setOrgaName($submitter['organisation'] ?? $submitter['name'] ?? '');
            $meta->setOrgaDepartmentName($submitter['department'] ?? '');
            $meta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'publicagency');

            return;
        }

        $statement->setPublicStatement(StatementInterface::EXTERNAL);
        $statement->setOrganisation($this->entityManager->getReference(Orga::class, User::ANONYMOUS_USER_ORGA_ID));
        $meta->setOrgaName(User::ANONYMOUS_USER_ORGA_NAME);
        $meta->setOrgaDepartmentName(User::ANONYMOUS_USER_DEPARTMENT_NAME);
        $meta->setSubmitUId(User::ANONYMOUS_USER_ID);
        $meta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'citizen');
        if (self::SUBMITTER_ANONYMOUS === $submitter['type']) {
            $meta->setSubmitName('');
            $meta->setAuthorName('');
        }
    }

    /**
     * The planning document category by its title, else the statement as a whole. Documents and
     * paragraphs are versioned per statement in dplan and not resolved yet.
     *
     * @param array{documentCategory: ?string, document: ?string, paragraph: ?string} $reference
     */
    private function resolveElement(Procedure $procedure, array $reference, OcxEntryContext $context): Elements
    {
        $element = null;
        $category = $reference['documentCategory'];
        if (null !== $category) {
            $candidates = $this->entityManager->getRepository(Elements::class)->findBy([
                'procedure' => $procedure,
                'title'     => $category,
                'deleted'   => false,
            ]);
            if (1 === count($candidates)) {
                $element = $candidates[0];
            } else {
                $context->warn('planning_reference_unresolved', '/planningReference/documentCategory', 'Unknown or ambiguous document category; the statement refers to the procedure as a whole.');
            }
        }
        foreach (['document', 'paragraph'] as $field) {
            if (null !== $reference[$field]) {
                $context->warn('planning_reference_unresolved', '/planningReference/'.$field, 'References to single documents and paragraphs are not applied; the document category is kept.');
            }
        }

        return $element ?? $this->elementsService->getStatementElement($procedure->getId());
    }

    /**
     * @param list<array{id: string, role: string}> $attachmentRefs
     *
     * @throws OcxEntryFailedException
     */
    private function attachFiles(Statement $statement, array $attachmentRefs, OcxEntryContext $context): void
    {
        $fileStrings = [];
        foreach ($attachmentRefs as $ref) {
            $fileId = $context->getFileId($ref['id']);
            $file = null === $fileId ? null : $this->entityManager->find(File::class, $fileId);
            if (!$file instanceof File) {
                throw OcxEntryFailedException::attachmentMissing($ref['id']);
            }
            $context->useFile($fileId);

            if ('original' === $ref['role']) {
                $attachment = $this->statementAttachmentService->createOriginalAttachment($statement, $file);
                $this->entityManager->persist($attachment);
                $statement->getAttachments()->add($attachment);
                continue;
            }

            $container = $this->fileService->addStatementFileContainer($statement->getId(), $file->getId(), $file->getFileString(), false);
            if (null === $container) {
                throw new OcxProcessingException('OCX attachment could not be linked to the statement');
            }
            $fileStrings[] = $container->getFileString();
        }
        if ([] !== $fileStrings) {
            // copied from the original onto the copy by the statement copier
            $statement->setFiles(array_values(array_unique($fileStrings)));
        }
    }

    /**
     * @throws OcxEntryFailedException
     */
    private function validate(Statement $statement): void
    {
        $violations = $this->validator->validate($statement, null, [StatementInterface::IMPORT_VALIDATION]);
        if (0 === $violations->count()) {
            return;
        }

        // property paths only: the messages may quote the content
        $fields = array_map(
            static fn (ConstraintViolationInterface $violation): string => '' === $violation->getPropertyPath() ? 'statement' : $violation->getPropertyPath(),
            iterator_to_array($violations)
        );
        throw OcxEntryFailedException::invalidEntry(array_values(array_unique($fields)));
    }

    /**
     * Computing the next externId reads every externId of the procedure, so it is done once per delivery
     * and counted up from there; a number someone else took in the meantime triggers a new computation.
     */
    private function nextExternId(Procedure $procedure): string
    {
        $procedureId = $procedure->getId();
        $number = $this->lastExternIds[$procedureId] ?? null;
        if (null !== $number && $this->isExternIdTaken($procedureId, $number)) {
            ++$number;
        }
        // a failed entry leaves its number free for the next one
        if (null === $number || $this->isExternIdTaken($procedureId, $number)) {
            $number = (int) $this->statementService->getNextValidExternalIdForProcedure($procedureId);
        }
        $this->lastExternIds[$procedureId] = $number;

        return 'M'.$number;
    }

    private function isExternIdTaken(string $procedureId, int $number): bool
    {
        return null !== $this->entityManager->createQueryBuilder()
            ->select('statement.id')
            ->from(Statement::class, 'statement')
            ->where('statement.procedure = :procedureId')
            ->andWhere('statement.externId IN (:externIds)')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('externIds', [(string) $number, 'M'.$number])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
