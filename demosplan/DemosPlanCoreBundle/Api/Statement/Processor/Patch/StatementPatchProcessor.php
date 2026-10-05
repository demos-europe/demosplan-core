<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Statement\Processor\Patch;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Closure;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Api\Serializer\FieldPermissionResolver;
use demosplan\DemosPlanCoreBundle\ApiResources\StatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceAccess\StatementAccessChecker;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

/**
 * Handles the PATCH of the Statement resource: checks what the user may change, changes the
 * statement and answers with the Statement resource, printed like a GET.
 *
 * Mirrors the update rules of the EDT StatementResourceType.
 */
class StatementPatchProcessor implements ProcessorInterface
{
    /** All other properties can only be changed on manual statements, unless the user may change any. */
    private const CHANGEABLE_ON_EVERY_STATEMENT = ['memo'];

    public function __construct(
        private readonly StatementAccessChecker $accessChecker,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
        private readonly FieldPermissionResolver $fieldPermissions,
        private readonly CurrentUserInterface $currentUser,
        private readonly EntityContentChangeService $entityContentChangeService,
        #[Autowire(service: PersistProcessor::class)] private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StatementResource
    {
        Assert::isInstanceOf($data, UpdateStatement::class);

        if (!$this->accessChecker->isAvailable() || !$this->accessChecker->isUpdateAllowed()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to update statements');
        }

        $statement = $this->loadStatement($uriVariables['id']);
        $sentProperties = $this->getSentProperties($data);

        // everything is checked before anything is changed, so a rejected request changes nothing
        $this->assertPropertiesMayBeChanged($statement, $sentProperties);

        $this->applyChanges($statement, $data, $sentProperties);

        if ($this->currentUser->hasPermission('feature_statement_content_changes_save')) {
            $this->entityContentChangeService->trackChanges($statement, Statement::class);
        }
        $this->persistProcessor->process($statement, $operation, $uriVariables, $context);

        return StatementResource::fromEntity($statement, $this->getProcessingStatus($statement));
    }

    private function loadStatement(string $id): Statement
    {
        try {
            $statement = $this->statementRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Statement %s not found', $id));
        }

        return $statement;
    }

    /**
     * @return list<string> the names of the properties the user sent
     */
    private function getSentProperties(UpdateStatement $data): array
    {
        return array_keys(array_filter(get_object_vars($data), static fn (mixed $value): bool => null !== $value));
    }

    /**
     * @param list<string> $sentProperties
     */
    private function assertPropertiesMayBeChanged(Statement $statement, array $sentProperties): void
    {
        // A permission that is missing must be rejected rather than silently dropped.
        foreach ($sentProperties as $property) {
            if (!$this->fieldPermissions->isPropertyAllowed(UpdateStatement::class, $property)) {
                throw new AccessDeniedHttpException(sprintf("Access denied: insufficient permissions to change '%s'", $property));
            }
        }

        $needsManualStatement = [] !== array_diff($sentProperties, self::CHANGEABLE_ON_EVERY_STATEMENT);
        if ($needsManualStatement
            && !$statement->isManual()
            && !$this->currentUser->hasPermission('feature_allow_update_on_non_manual_statements')) {
            throw new AccessDeniedHttpException('Access denied: only manual statements can be changed');
        }
    }

    /**
     * @param list<string> $sentProperties
     */
    private function applyChanges(Statement $statement, UpdateStatement $data, array $sentProperties): void
    {
        $changers = $this->getChangers();

        foreach ($sentProperties as $property) {
            Assert::keyExists($changers, $property, "The property '$property' cannot be changed.");
            $changers[$property]($statement, $data->$property);
        }
    }

    /**
     * @return array<string, Closure(Statement, string): mixed>
     */
    private function getChangers(): array
    {
        return [
            'memo'                              => static fn (Statement $statement, string $value) => $statement->setMemo($value),
            'internId'                          => $this->changeInternId(...),
            'authorName'                        => static fn (Statement $statement, string $value) => $statement->getMeta()->setAuthorName($value),
            'submitName'                        => static fn (Statement $statement, string $value) => $statement->getMeta()->setSubmitName($value),
            'initialOrganisationName'           => static fn (Statement $statement, string $value) => $statement->getMeta()->setOrgaName($value),
            'initialOrganisationDepartmentName' => static fn (Statement $statement, string $value) => $statement->getMeta()->setOrgaDepartmentName($value),
            'initialOrganisationStreet'         => static fn (Statement $statement, string $value) => $statement->getMeta()->setOrgaStreet($value),
            'initialOrganisationHouseNumber'    => static fn (Statement $statement, string $value) => $statement->getMeta()->setHouseNumber($value),
            'initialOrganisationPostalCode'     => static fn (Statement $statement, string $value) => $statement->getMeta()->setOrgaPostalCode($value),
            'initialOrganisationCity'           => static fn (Statement $statement, string $value) => $statement->getMeta()->setOrgaCity($value),
            'submitType'                        => static fn (Statement $statement, string $value) => $statement->setSubmitType($value),
            // the submit date comes before the authored date, which may not be later than it
            'submitDate'                        => static fn (Statement $statement, string $value) => $statement->setSubmit(new DateTime($value)),
            'authoredDate'                      => $this->changeAuthoredDate(...),
        ];
    }

    private function changeInternId(Statement $statement, string $internId): void
    {
        if ($internId === $statement->getInternId()) {
            return;
        }

        $procedureId = $statement->getProcedureId();
        if (!$this->statementService->isInternIdUniqueForProcedure($internId, $procedureId)) {
            throw new UnprocessableEntityHttpException("The internId $internId already exists in a procedure with the ID $procedureId");
        }

        $statement->getOriginal()->setInternId($internId);
    }

    /**
     * An authored date later than the submit date is set to the submit date.
     */
    private function changeAuthoredDate(Statement $statement, string $value): void
    {
        $authoredDate = new DateTime($value);
        // 0 if the statement has no submit date
        $submitTimestamp = $statement->getSubmit();
        if (0 !== $submitTimestamp && $authoredDate->getTimestamp() > $submitTimestamp) {
            $authoredDate = (new DateTime())->setTimestamp($submitTimestamp);
        }

        $statement->getMeta()->setAuthoredDate($authoredDate);
    }

    /**
     * The processing status lazy-loads the segments, so it is only computed if the answer shows it.
     */
    private function getProcessingStatus(Statement $statement): ?string
    {
        return $this->fieldPermissions->isPropertyAllowed(StatementResource::class, 'status')
            ? $this->statementService->getProcessingStatus($statement)
            : null;
    }
}
