<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ManualListSorter;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttributeRepository;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DraftStatementResult;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PdfFile;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\SortMethodInterface;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\Terms;
use Exception;
use League\Flysystem\FilesystemOperator;
use ReflectionException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DraftStatementService extends CoreService
{
    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;

    /** @var Index */
    protected $esDraftStatementIndex;

    /** @var ElementsService */
    protected $elementsService;

    /** @var StatementService */
    protected $statementService;

    /** @var ServiceImporter */
    protected $serviceImporter;

    /** @var MapService */
    protected $serviceMap;

    /** @var Environment */
    protected $twig;

    /** @var ParagraphService */
    protected $paragraphService;

    /**
     * @var OrgaService
     */
    protected $orgaService;

    /**
     * @var FileService
     */
    protected $fileService;
    /**
     * @var StatementValidator
     */
    protected $statementValidator;

    public function __construct(
        CurrentUserInterface $currentUser,
        private readonly DateHelper $dateHelper,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly DraftStatementRepository $draftStatementRepository,
        private readonly DraftStatementVersionRepository $draftStatementVersionRepository,
        private readonly ElasticsearchFilterArrayTransformer $elasticsearchFilterArrayTransformer,
        ElementsService $elementsService,
        private readonly EntityHelper $entityHelper,
        Environment $twig,
        FileService $fileService,
        private readonly FilesystemOperator $defaultStorage,
        private readonly ManualListSorter $manualListSorter,
        MapService $serviceMap,
        private readonly MessageBagInterface $messageBag,
        private readonly NotificationReceiverRepository $notificationReceiverRepository,
        OrgaService $orgaService,
        ParagraphService $paragraphService,
        private readonly ParagraphVersionRepository $paragraphVersionRepository,
        private readonly ProcedureService $procedureService,
        private readonly ReportService $reportService,
        ServiceImporter $serviceImporter,
        private readonly SingleDocumentVersionRepository $singleDocumentVersionRepository,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly StatementAttributeRepository $statementAttributeRepository,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        StatementService $statementService,
        StatementValidator $statementValidator,
        private readonly TranslatorInterface $translator,
    ) {
        $this->currentUser = $currentUser;
        $this->elementsService = $elementsService;
        $this->fileService = $fileService;
        $this->orgaService = $orgaService;
        $this->paragraphService = $paragraphService;
        $this->serviceImporter = $serviceImporter;
        $this->serviceMap = $serviceMap;
        $this->statementService = $statementService;
        $this->statementValidator = $statementValidator;
        $this->twig = $twig;
    }

    /**
     * Ruft alle Stellungnahme Entwürfe eines Verfahrens ab.
     *
     * @param string      $procedureId
     * @param string      $scope
     * @param string|null $search
     * @param array|null  $sort
     * @param User        $user
     * @param string|null $manualSortScope
     * @param bool        $toLegacy
     *
     * @throws Exception
     */
    public function getDraftStatementList($procedureId, $scope, StatementListUserFilter $filters, $search, $sort, $user, $manualSortScope = null, $toLegacy = true): DraftStatementResult
    {
        if (null === $user) {
            throw new AccessDeniedException('No user given');
        }
        try {
            /*
             * Special fetching strategy for DraftStatements is needed:
             * 'own' in combination with getReleased to indicate
             * the DraftStatement belongs to the users organisation and has been created by the given user.
             */
            if ('own' === $scope && true === $filters->getReleased()
                && (null === $filters->getSubmitted() || false === $filters->getSubmitted())) {
                return $this->getDraftStatementReleasedOwnList($procedureId, $filters, $search, $sort, $user, $manualSortScope);
            }

            $conditions = [
                $this->conditionFactory->propertyHasValue($procedureId, ['procedure']),
                $this->conditionFactory->propertyHasValue(false, ['deleted']),
            ];

            // only show drafts from own organisation
            // Users see only drafts from their own organisation, when no gateway name is set (null or '').
            // When a gateway name is set, users see drafts from any organisation that has the same gateway name as own orga
            $userOrganisationGatewayName = $user->getOrga()->getGatewayName();
            if (null === $userOrganisationGatewayName || '' === $userOrganisationGatewayName) {
                $conditions[] = $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['organisation']);
            } else {
                $conditions[] = $this->conditionFactory->propertyHasValue($userOrganisationGatewayName, ['organisation', 'gatewayName']);
            }
            if ('ownCitizen' === $scope) {
                // In case of ownCitizen, previous ('own'-)logic can be executed:
                $conditions[] = $this->conditionFactory->propertyHasValue($user->getId(), ['user']);
                // add filter to be seen by elasticsearch
                $filters->setSomeOnesUserId($user->getId());
            }
            if ('own' === $scope) {
                // own means own organisation in this context.
                // filter out all private DraftStatements of own organisation if they are not authored by currentUser
                $conditions[] = $this->conditionFactory->anyConditionApplies(
                    $this->conditionFactory->propertyHasValue(false, ['authorOnly']),
                    $this->conditionFactory->propertyHasValue($user->getId(), ['user']),
                );
                $filters->setSomeOnesUserId($user->getId());
            }

            if (null !== $filters->getReleased()) {
                $conditions[] = $this->conditionFactory->propertyHasValue($filters->getReleased(), ['released']);
            }
            if (null !== $filters->getSubmitted()) {
                $conditions[] = $this->conditionFactory->propertyHasValue($filters->getSubmitted(), ['submitted']);
            }
            if (null !== $filters->getElement()) {
                $conditions[] = $this->conditionFactory->propertyHasValue($filters->getElement(), ['element']);
            }
            if (null !== $filters->getDepartment()) {
                $conditions[] = $this->conditionFactory->propertyHasValue($filters->getDepartment(), ['department']);
            }

            // Suche
            if (is_string($search) && 0 < strlen($search)) {
                $conditions[] = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue($search, ['text']);
            }

            // Sortierung
            $sortMethods = $this->createSortMethodsByAssociation($sort);
            array_unshift($sortMethods, $this->createSortMethod($sort));

            $results = $this->draftStatementRepository->getEntities($conditions, $sortMethods);

            $list = [];
            if (null !== $results) {
                foreach ($results as $result) {
                    if (is_array($result)) {
                        $result = $result[0];
                    }
                    if ($toLegacy) {
                        $list[] = $this->convertToLegacy($result);
                    } else {
                        $list[] = $result;
                    }
                }
            }

            // get Elasticsearch aggregations aka Userfilters
            $aggregation = $this->getElasticsearchDraftStatementAggregation($filters, $procedureId, $user, $search, $scope);

            return $this->toLegacyResult($list, $procedureId, $search, $filters->toArray(), $sort, $manualSortScope, $aggregation);
        } catch (Exception $e) {
            $this->logger->warning('get DraftStatement List failed. Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft alle eingereichten Stellungnahme Entwürfe anderer Beteiligten eines Verfahrens ab.
     * Die Beteiligten müssen die Entwürfe explizit zur Einsicht freigeben haben.
     *
     * @param string      $procedureId
     * @param string      $search
     * @param array|null  $sort
     * @param User        $user
     * @param string|null $manualSortScope
     *
     * @throws Exception
     */
    public function getDraftStatementListFromOtherCompanies($procedureId, StatementListUserFilter $filters, $search, $sort, $user, $manualSortScope = null): DraftStatementResult
    {
        try {
            $conditions = [
                $this->conditionFactory->propertyHasValue($procedureId, ['procedure']),
                $this->conditionFactory->propertyHasValue(false, ['deleted']),
                $this->conditionFactory->propertyHasNotValue($user->getOrganisationId(), ['organisation']),
                $this->conditionFactory->propertyHasValue(true, ['submitted']),
                $this->conditionFactory->propertyHasValue(true, ['showToAll']),
            ];

            // Suche
            if (is_string($search) && 0 < strlen($search)) {
                $conditions[] = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue($search, ['text']);
            }

            // Sortierung
            $sortMethods = $this->createSortMethodsByAssociation($sort);
            array_unshift($sortMethods, $this->createSortMethod($sort));

            $results = $this->draftStatementRepository->getEntities($conditions, $sortMethods);

            $list = [];
            foreach ($results as $result) {
                $list[] = $this->convertToLegacy($result);
            }

            // get Elasticsearch aggregations aka Userfilters
            $filters->setOtherCompaniesFilter(true);
            $filters->setSubmitted(true);
            $filters->setShowToAll(true);

            $aggregation = $this->getElasticsearchDraftStatementAggregation($filters, $procedureId, $user, $search);
            $filters->setOrganisationNameFilter(true);

            return $this->toLegacyResult($list, $procedureId, $search, $filters->toArray(), $sort, $manualSortScope, $aggregation);
        } catch (Exception $e) {
            $this->logger->warning('get DraftStatement List other companies failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * Returns all DraftStatements.
     *
     * @return DraftStatement[]
     */
    public function getAllDraftStatements(): array
    {
        try {
            return $this->draftStatementRepository->findAll();
        } catch (Exception $e) {
            $this->logger->error('DraftStatementService getAllDraftStatements() has thrown an exception: ', [$e]);

            return [];
        }
    }

    /**
     * @param string[] $procedureIds
     *
     * @return DraftStatement[]
     */
    public function getUnsubmittedDraftStatementsOfProcedures(array $procedureIds, bool $internal): array
    {
        return $this->draftStatementRepository->getUnsubmittedDraftStatementsProcedures($procedureIds, $internal);
    }

    /**
     * Returns all NotificationReceivers for a given procedure.
     *
     * @param string $procedureId
     *
     * @return NotificationReceiver|bool
     */
    public function getNotificationReceiversByProcedure($procedureId)
    {
        try {
            return $this->notificationReceiverRepository->getNotificationReceiversByProcedure($procedureId);
        } catch (Exception $e) {
            $this->logger->error('DraftStatementService getNotificationReceiversByProcedure() has thrown an exception: ', [$e]);

            return false;
        }
    }

    /**
     * Ruft alle Entwurfs Versionen einer Stellungnahme eines Verfahrens ab.
     *
     * @return array<int, DraftStatement>
     *
     * @throws UserNotFoundException
     */
    public function getVersionList(string $draftStatementId): array
    {
        return $this->draftStatementRepository->getVersionList(
            $draftStatementId,
            $this->currentUser->getUser()->getOrganisationId()
        );
    }

    /**
     * Speichert die manuelle Listensortierung.
     *
     * @param string $ident
     * @param string $context
     * @param string $sortIds
     *                        (Komma separierte Liste) / leer zum löschen
     *
     * @throws Exception
     */
    public function setManualSort($ident, $context, $sortIds): bool
    {
        // keine leerzeichen zwischen den Ids
        $sortIds = str_replace(' ', '', $sortIds);

        $data = [
            'ident'     => $ident,
            'namespace' => 'draftStatement',
            'context'   => $context,
            'sortIdent' => $sortIds,
        ];

        return $this->manualListSorter->setManualSort($context, $data);
    }

    /**
     * Ruft einen einzelnen DraftStatement auf.
     *
     * @param string $ident
     *
     * @return array
     *
     * @throws Exception
     *
     * @deprecated use {@link getDraftStatementEntity} instead
     */
    public function getSingleDraftStatement($ident)
    {
        $draftStatement = $this->draftStatementRepository->get($ident);

        return $this->convertToLegacy($draftStatement);
    }

    /**
     * Ruft einen einzelnen DraftStatement auf.
     *
     * @param string $ident
     *
     * @return array|null
     *
     * @deprecated use {@link getDraftStatementEntity} instead
     */
    public function getDraftStatement($ident)
    {
        $draftStatement = $this->getDraftStatementObject($ident);

        return $this->convertToLegacy($draftStatement);
    }

    /**
     * Ruft einen einzelnen DraftStatement als Objekt auf.
     *
     * @param string $ident
     * @param string $entityClass
     *
     * @return DraftStatement|DraftStatementVersion|null
     *
     * @deprecated use {@link getDraftStatementEntity} or {@link getDraftStatementVersionEntity} instead
     */
    public function getDraftStatementObject($ident, $entityClass = DraftStatement::class)
    {
        if (DraftStatementVersion::class === $entityClass) {
            return $this->draftStatementVersionRepository->get($ident);
        }

        return $this->draftStatementRepository->get($ident);
    }

    /**
     * @return DraftStatement|null
     *
     * @throws Exception
     */
    public function getDraftStatementEntity(string $draftStatementId)
    {
        return $this->draftStatementRepository->get($draftStatementId);
    }

    /**
     * @return DraftStatementVersion|null
     */
    public function getDraftStatementVersionEntity(string $draftStatementVersionId)
    {
        return $this->draftStatementVersionRepository->get($draftStatementVersionId);
    }

    /**
     * Eine Stellungsnahme wird zurück gewiesen.
     *
     * Nach dem Aufruf haben sich folgende Werte einer Stellungsnahme geändert.
     *
     * rejected = true
     * released = false
     * scope = own
     * reason = String
     *
     * @param string $ident
     * @param string $reason
     *
     * @return bool
     *
     * @throws Exception
     */
    public function rejectDraftStatement($ident, $reason)
    {
        $data = [
            'ident'          => $ident,
            'submitted'      => false,
            'released'       => false,
            'rejected'       => true,
            'rejectedReason' => $reason,
            'rejectedDate'   => new DateTime(),
            'releasedDate'   => DateTime::createFromFormat('d.m.Y', '2.1.1970'),
        ];
        $result = $this->updateDraftStatement($data);

        return is_array($result);
    }

    /**
     * Set all draftStatements of the given organisation, which are released and not submitted, to unreleased.
     *
     * @param Orga $organisation - organisation, whose draftStatements will be set to unreleased
     *
     * @return bool - true, if all found draftStatements are successfully reset
     */
    public function resetDraftStatementsOfProceduresOfOrga(Orga $organisation)
    {
        $allDraftStatementIds = collect([]);
        $draftStatements = $this->draftStatementRepository
            ->findBy([
                'organisation' => $organisation->getId(),
                'released'     => true,
                'submitted'    => false,
                'deleted'      => false,
            ]);

        foreach ($draftStatements as $draftStatement) {
            $allDraftStatementIds->push($draftStatement->getId());
        }

        return $this->resetReleasedDraftStatements($allDraftStatementIds->toArray());
    }

    /**
     * The release status of the given DraftStatements will be reset to false.
     *
     * @param string[] $draftStatementIds
     *
     * @return bool - true if all given DraftStatements are successfully updated
     */
    public function resetReleasedDraftStatements(array $draftStatementIds)
    {
        $success = 0;

        foreach ($draftStatementIds as $id) {
            $data = [
                'ident'        => $id,
                'released'     => false,
                'releasedDate' => DateTime::createFromFormat('d.m.Y', '2.1.1970'),
            ];

            $result = $this->updateDraftStatement($data);

            if ($result instanceof DraftStatement || is_array($result)) {
                ++$success;
            }
        }

        return $success === count($draftStatementIds);
    }

    /**
     * Eine Stellungsnahme wird freigegeben.
     *
     * Nach dem Aufruf haben sich folgende Werte einer Stellungsnahme geändert.
     *
     * rejected = false
     * released = true
     * scope = group
     *
     * @return bool
     */
    public function releaseDraftStatement(array $idents)
    {
        $success = true;

        if (!is_array($idents)) {
            $idents = [$idents];
        }

        foreach ($idents as $ident) {
            $data = [
                'ident'        => $ident,
                'released'     => true,
                'releasedDate' => new DateTime(),
            ];
            $result = $this->updateDraftStatement($data);
            if (false === $result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Stellungsnahmen werden eingereicht.
     *
     * After calling this method - the following attributes of the draft-statement(s) will be altered:
     * submitted = true
     * rejected = false
     * rejectedReason = ''
     *
     * @param string|array $draftStatementIds
     * @param User         $user
     * @param bool         $gdprConsentReceived true if the GDPR consent was received
     *
     * @retrun array<int, array>
     *
     * @throws MessageBagException
     * @throws NonUniqueResultException
     */
    public function submitDraftStatement(
        $draftStatementIds,
        $user,
        ?NotificationReceiver $notificationReceiver = null,
        bool $gdprConsentReceived = false,
        bool $convertToLegacy = true,
    ): array {
        if (!is_array($draftStatementIds)) {
            $draftStatementIds = [$draftStatementIds];
        }

        return $this->submitDraftStatements(
            $draftStatementIds,
            $user,
            $notificationReceiver,
            $gdprConsentReceived,
            $convertToLegacy
        );
    }

    /**
     * @param User $user
     * @param bool $gdprConsentReceived true if the GDPR consent was received
     *
     * @retrun array<int, array>|array<int, Statement>
     *
     * @throws MessageBagException
     * @throws NonUniqueResultException
     */
    protected function submitDraftStatements(
        array $draftStatementIds,
        $user,
        ?NotificationReceiver $notificationReceiver = null,
        bool $gdprConsentReceived = false,
        bool $convertToLegacy = true,
    ): array {
        $submittedStatements = [];

        foreach ($draftStatementIds as $draftStatementId) {
            $draftStatement = $this->draftStatementRepository->get($draftStatementId);

            if (!$draftStatement) {
                $this->getLogger()->warning('DraftStatement could not be fetched', [$draftStatementId]);
                continue;
            }

            if ($draftStatement->isSubmitted()) {
                $this->getLogger()->warning("DraftStatement {$draftStatementId} already submitted");

                $this->messageBag->add(
                    'warning',
                    'warning.draftStatement.already.submitted',
                    ['draftStatementId' => $draftStatement->getNumber()]
                );

                continue;
            }

            $data = [
                'ident'             => $draftStatementId,
                'submitted'         => true,
                'released'          => true,
                'submittedDate'     => new DateTime(),
                'rejected'          => false,
                'rejectedReason'    => '',
            ];

            $draftStatement = $this->updateDraftStatement($data, false);

            if (false === $draftStatement) {
                $this->getLogger()->warning('DraftStatement could not be updated', [$data]);

                continue;
            }
            // Copy DraftStatement to Statement
            $submitResult = $this->statementService->submitDraftStatement(
                $draftStatement,
                $user,
                $notificationReceiver,
                $gdprConsentReceived
            );

            if (false === $submitResult) {
                $this->getLogger()->warning('DraftStatement could not be submitted: '.DemosPlanTools::varExport($submitResult, true));
                continue;
            }

            if ($convertToLegacy) {
                $submitResult = $this->statementService->convertToLegacy($submitResult);
            }

            $submittedStatements[] = $submitResult;

            try {
                // format the fancy new statement to legacy for reports
                if (!$convertToLegacy) {
                    $submitResult = $this->statementService->convertToLegacy($submitResult);
                }

                $this->addReport($submitResult);
            } catch (Exception $e) {
                $this->logger->warning('Add Report in submitDraftStatement() failed Message: ', [$e]);
            }
        }

        return $submittedStatements;
    }

    /**
     * Adds a report.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    protected function addReport(array $statement): void
    {
        $entry = $this->statementReportEntryFactory->createSubmittedStatementEntry($statement);
        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * Fügt eine Stellungsnahme Entwurf hinzu.
     *
     * wird der Parameter uId oder oId weggelassen, werden diese Werte aus der Session übernommen.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function addDraftStatement($data)
    {
        // validate visibility of related paragraph in case of related paragraph is set
        if (array_key_exists('paragraphId', $data) && !is_null($data['paragraphId']) && '' !== $data['paragraphId']) {
            $paragraph = $this->paragraphService->getParaDocumentObject($data['paragraphId']);
            if (!is_null($paragraph) && 1 != $paragraph->getVisible()) {
                $this->getLogger()->error('On addDraftStatement(): selected paragraph '.$paragraph->getId().' is not released!');
                throw new Exception();
            }
        }

        /** @var DraftStatement $draftStatement */
        $draftStatement = $this->draftStatementRepository->executeAndFlushInTransaction(function ($em) use ($data): DraftStatement {
            // Create and use versions of paragraph and Element
            $data = $this->getEntityVersions($data);
            $draftStatement = $this->draftStatementRepository->add($data);

            // Validate DraftStatement and delete if invalid
            $violations = $this->statementValidator->validate($draftStatement);
            if (0 !== $violations->count()) {
                throw ViolationsException::fromConstraintViolationList($violations);
            }

            return $draftStatement;
        });

        // generate Screenshot if necessary
        if (array_key_exists('polygon', $data) && 0 < strlen((string) $data['polygon'])) {
            $mapService = $this->getServiceMap();
            $mapService->createMapScreenshot($data['pId'], $draftStatement->getId());
        }

        if (array_key_exists('statementAttributes', $data) && is_array($data['statementAttributes'])) {
            $attrRepo = $this->statementAttributeRepository;

            if (array_key_exists('noLocation', $data['statementAttributes'])
                && true == $data['statementAttributes']['noLocation']) {
                $attrRepo->setNoLocation($draftStatement);
            } elseif (array_key_exists('county', $data['statementAttributes']) && 0 < strlen((string) $data['statementAttributes']['county'])) {
                try {
                    $attrRepo->addCounty($draftStatement, $data['statementAttributes']['county']);
                } catch (Exception) {
                    $attrRepo->removeCounty($draftStatement);
                }
            }

            // Speichere das Vorragnggebiet in den statementAttributes zwischen, damit
            // es später dem Statement zugewiesen werden kann
            $this->addPriorityAreaAttribute(
                $data,
                $draftStatement,
                $attrRepo
            );
        }

        return $this->convertToLegacy($draftStatement);
    }

    public function deleteDraftStatementById(string $draftStatementId): bool
    {
        return $this->deleteDraftStatementsByIds([$draftStatementId]);
    }

    /**
     * @param string[] $draftStatementIds
     */
    public function deleteDraftStatementsByIds(array $draftStatementIds): bool
    {
        // @improve T12809
        $draftStatements = array_map($this->draftStatementRepository->get(...), $draftStatementIds);

        return $this->deleteDraftStatements($draftStatements);
    }

    /**
     * Deletes a DraftStatement.
     *
     * @param DraftStatement[] $draftStatements
     */
    public function deleteDraftStatements(array $draftStatements): bool
    {
        $success = true;

        foreach ($draftStatements as $draftStatement) {
            try {
                $this->draftStatementRepository->deleteObject($draftStatement);
            } catch (Exception $e) {
                $this->logger->error('Fehler beim Löschen eines DraftStatements: ', [$e]);
                $success = false;
            }
        }

        // @improve T12803
        return $success;
    }

    /**
     * Generates a pdf from a list of draftstatements.
     *
     * @param array<int, array> $draftStatementList
     * @param string            $type               list_released_group|single|finalCitizen|list_final_group
     * @param string            $procedureId
     * @param array|null        $itemsToExport
     *
     * @throws Exception
     */
    public function generatePdf($draftStatementList, $type, $procedureId, $itemsToExport = null): PdfFile
    {
        $templateVars = [];
        $outputResult = [];
        $template = 'list_export';
        switch ($type) {
            case 'list_released_group':
                $templateVars['titleTransKey'] = 'statements.grouprelease';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_released_group.suffix');
                break;
            case 'single':
                // Der Pdfexport einzelner Stellungnahmen (der Bürger) weicht etwas von den anderen Fällen ab
                $template = 'single_export_public';

                $filenameSuffix = $this->translator->trans('export.filenames.statement.single.suffix');
                // Die Stellungnahme kann nicht übergeben werden, rufe sie ab
                $templateVars['statement'] = $this->getSingleDraftStatement(
                    $itemsToExport[0]
                );
                $itemsToExport = null;
                break;
            case 'list_final_group_citizen':
                $template = 'list_final_group_citizen_export';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_final_group_citizen.suffix');
                break;
            case 'list_draft':
                $templateVars['titleTransKey'] = 'statements.draft';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_draft.suffix');
                break;
            case 'list_released':
                $templateVars['titleTransKey'] = 'statements.ownrelease';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_released.suffix');
                break;
            case 'list_final_other':
                $templateVars['titleTransKey'] = 'statements.final_other';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_final_group.suffix');
                break;
            case 'list_final_group':
            default:
                $templateVars['titleTransKey'] = 'statements.final';
                $filenameSuffix = $this->translator->trans('export.filenames.statement.list_final_group.suffix');
                break;
        }

        $filenameSuffix .= '.pdf';

        $selectedStatementsToExport = isset($itemsToExport)
            ? explode(',', $itemsToExport)
            : null;

        $filteredStatementList = collect($draftStatementList)->filter(fn ($statement) => null === $selectedStatementsToExport || in_array($this->entityHelper->extractId($statement), $selectedStatementsToExport))->map(function (array $statement) use ($procedureId) {
            $statement['documentlist'] = $this->paragraphService->getParaDocumentObjectList($procedureId, $statement['elementId']);
            $statement = $this->checkMapScreenshotFile($statement, $procedureId);

            return $statement;
        })->all();

        $firstOrganisationId = $filteredStatementList[0]['oId'] ?? '';

        $templateVars['citizenOrganisationId'] = User::ANONYMOUS_USER_ORGA_ID;
        $templateVars['citizenDepartmentId'] = User::ANONYMOUS_USER_DEPARTMENT_ID;

        // do not display name of anonymous user organisation
        if ('' !== $firstOrganisationId
            && User::ANONYMOUS_USER_ORGA_ID !== $firstOrganisationId
            && $this->isSameOrganisationIdInAllStatements($filteredStatementList, $firstOrganisationId)) {
            $templateVars['globalOrganisationName'] = $filteredStatementList[0]['oName'] ?? '';
        }

        $outputResult['statementlist'] = $filteredStatementList;
        $templateVars['list'] = $outputResult;
        $templateVars['procedure'] = $procedureId;
        // set listLineWidth for pdf vertical format (portrait) view not split - Text only
        $templateVars['listwidth'] = 17;
        $procedure = $this->procedureService->getProcedure($procedureId);

        $content = $this->twig->render('@DemosPlanCore/DemosPlanStatement/'.$template.'.tex.twig', [
            'procedure'    => $procedure,
            'templateVars' => $templateVars,
            'title'        => 'DPlan',
        ]);

        $pictures = [];

        $pictures = $this->getPicturesFromStatementList($outputResult['statementlist'], $pictures);
        $pictures = $this->extractPicturesFromContent($content, $pictures);

        $this->getLogger()->debug('Send Content to tex2pdf consumer: '.DemosPlanTools::varExport($content, true));

        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $this->profilerStart('Rabbit PDF');
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content), $pictures);
        $this->profilerStop('Rabbit PDF');
        $file = new PdfFile(
            $filenameSuffix,
            base64_decode($response)
        );

        $this->getLogger()->debug('Got Response: '.DemosPlanTools::varExport($file->getContent(), true));

        return $file;
    }

    /**
     * @param array<int,array<string,mixed>> $statements
     */
    protected function isSameOrganisationIdInAllStatements(array $statements, string $organisationId): bool
    {
        return collect($statements)
            ->pluck('oId')
            ->every(static fn (string $oId) => $oId === $organisationId);
    }

    /**
     * Check the given array 'statementList' for a polygon and the related mapfile.
     * Try to create screenshot of map if not existing.
     * The information of the file where each screenshot/picture was found, will be added to the given array 'pictures'.
     *
     * @param array $statementList - array of Statements as array
     * @param array $pictures      - array where the information about the file will be added to
     *
     * @return array - informations about the file where the picture was found in special structure
     *
     * @throws Exception
     */
    protected function getPicturesFromStatementList($statementList, $pictures)
    {
        foreach ($statementList as $statementData) {
            try {
                // ensure that current mapfile exists when polygon is drawn
                $statementData = $this->checkMapScreenshotFile($statementData, $statementData['pId']);
                // hat das Statement ein Screenshot?
                if (0 < strlen($statementData['mapFile'] ?? '')) {
                    $this->getLogger()->info('DraftStatement hat einen Screenshot.');
                    $file = $this->fileService->getFileInfoFromFileString($statementData['mapFile']);
                    $pictures = $this->addEntryOfFoundPicture($file, $pictures);
                }
            } catch (Exception $e) {
                $this->getLogger()->warning('Exception in Screenshotter: ', [$e]);
            }
        }

        return $pictures;
    }

    protected function checkMapScreenshotFile(array $statementArray, string $procedureId): array
    {
        // hat das Statement einen Screenshot aber kein Polygon?
        if (0 < strlen((string) $statementArray['polygon']) && 0 === strlen($statementArray['mapFile'] ?? '')) {
            $this->getLogger()->info('DraftStatement hat ein Polygon, aber keinen Screenshot. Erzeuge ihn');
            $statementArray['mapFile'] = $this->getServiceMap()->createMapScreenshot($procedureId, $statementArray['ident']);
        }
        // hat das Statement ein Screenshot?
        if (0 < strlen($statementArray['mapFile'] ?? '')) {
            $this->getLogger()->info('DraftStatement hat einen Screenshot.');
            $fileInfo = $this->fileService->getFileInfoFromFileString($statementArray['mapFile']);
            // Wenn der Screenshot da sein müsste, es aber nicht ist, versuche ihn neu zu generieren
            if (!$this->defaultStorage->fileExists($fileInfo->getAbsolutePath())) {
                $this->getLogger()->info('Screenshot konnte nicht gefunden werden');
                if (0 < strlen((string) $statementArray['polygon'])) {
                    $this->getLogger()->info('Erzeuge Screenshot neu');
                    $statementArray['mapFile'] = $this->getServiceMap()->createMapScreenshot($procedureId, $statementArray['ident']);
                }
            }
        }

        return $statementArray;
    }

    /**
     * Extract included graphics from a LaTeX-Document.
     * Is looking for the key '/includegraphics'-string in given content.
     * The information of the file where each picture was found, will be added to the given array 'pictures'.
     *
     * @param string $content  - is a LaTeX-Document
     * @param array  $pictures - array where the information about the file will be added to
     *
     * @return array - informations about the file where the picture was found in special structure
     *
     * @throws Exception
     */
    protected function extractPicturesFromContent($content, $pictures)
    {
        $imagematches = [];

        preg_match_all('/includegraphics\[.*]*\]\{(.*)\}/Usi', $content, $imagematches);
        if (isset($imagematches[1])) {
            $this->getLogger()->info('Pdf: Gefundene Bilder: '.(is_countable($imagematches[1]) ? count($imagematches[1]) : 0));
            foreach ($imagematches[1] as $match) {
                $file = $this->fileService->getFileInfo($match);
                $pictures = $this->addEntryOfFoundPicture($file, $pictures);
            }
        }

        return $pictures;
    }

    /**
     * Add information of found picture in the file to the given array in a specific structure.
     * The structure of the array is used to send it to a specific service for generating a pdf-document.
     * Also checking and logging if the given file is existing.
     *
     * @param FileInfo $file     - existing file, where the picture was found
     * @param array    $pictures - array where the information about the file will be added to
     *
     * @return array - informations about the file where the picture was found in special structure
     */
    protected function addEntryOfFoundPicture(FileInfo $file, $pictures)
    {
        $index = count($pictures);

        if ($this->defaultStorage->fileExists($file->getAbsolutePath())) {
            $this->getLogger()->debug('Picture found: ', [$file->getAbsolutePath()]);
            $fileContent = $this->defaultStorage->read($file->getAbsolutePath());
            $pictures['picture'.$index] = $file->getHash().'###'.$file->getFileName().'###'.base64_encode($fileContent);
        } else {
            $this->getLogger()->error('Picture not found: ', [$file->getAbsolutePath()]);
        }

        return $pictures;
    }

    /**
     * Update eines DraftStatement.
     *
     * @param array $data
     * @param bool  $useLegacy
     * @param bool  $createVersion - determines if a DraftStatementVersion will be created
     *
     * @return array|false|DraftStatement
     */
    public function updateDraftStatement($data, $useLegacy = true, $createVersion = true)
    {
        try {
            if (!isset($data['ident'])) {
                return false;
            }

            // Create and use versions of paragraph and Element
            $data = $this->getEntityVersions($data);

            // before updating the draftstatement - check if a version allready exists
            // if versioning is requested and no version exists yet - create a version of the original state as well
            // before updating the entity. refs T32960:
            if ($createVersion && 0 === count($this->getVersionList($data['ident']))) {
                $draftStatementBeforeUpdate = $this->draftStatementRepository->get($data['ident']);
                if (null !== $draftStatementBeforeUpdate) {
                    $this->draftStatementVersionRepository->createVersion($draftStatementBeforeUpdate);
                }
            }

            $draftStatement = $this->draftStatementRepository
                ->update($data['ident'], $data);

            $attrRepo = $this->statementAttributeRepository;

            // generate Screenshot if necessary
            if (array_key_exists('polygon', $data) && 0 < strlen((string) $data['polygon'])) {
                $mapService = $this->getServiceMap();
                $mapFile = $mapService->createMapScreenshot($draftStatement->getPId(), $data['ident']);
                $draftStatement->setMapFile($mapFile);
                $attrRepo->unsetNoLocation($draftStatement);
                $attrRepo->removeCounty($draftStatement);
            }

            if (array_key_exists('statementAttributes', $data) && is_array($data['statementAttributes'])) {
                if (array_key_exists('priorityAreaKey', $data['statementAttributes'])) {
                    $this->setPriorityAreaAttributes($data, $draftStatement, $attrRepo);
                }

                if (array_key_exists('noLocation', $data['statementAttributes'])
                    && true == $data['statementAttributes']['noLocation']) {
                    $attrRepo->setNoLocation($draftStatement);
                    $draftStatement->setMapFile('');
                    $draftStatement->setPolygon('');
                } elseif (array_key_exists('county', $data['statementAttributes']) && 0 < strlen((string) $data['statementAttributes']['county'])) {
                    try {
                        $attrRepo->addCounty($draftStatement, $data['statementAttributes']['county']);
                        $attrRepo->unsetNoLocation($draftStatement);
                        $draftStatement->setMapFile('');
                        $draftStatement->setPolygon('');
                    } catch (Exception) {
                        $attrRepo->removeCounty($draftStatement);
                    }
                }
            }
            if (null == $draftStatement->getElement()) {
                // Get Id of "Gesamtstellungnahme"
                $headElementId = $this->determineStatementCategory($draftStatement->getProcedure()->getId(), []);
                $element = $this->elementsService->getElementObject($headElementId);
                $draftStatement->setElement($element);
            }

            // refs T8573: avoid creating new Version of DraftStatement if not wanted (e.g. createMapScreenshot())
            if ($createVersion) {
                $this->draftStatementVersionRepository->createVersion($draftStatement);
            }

            // Convert to legacy if needed
            if ($useLegacy) {
                $draftStatement = $this->convertToLegacy($draftStatement);
            }

            return $draftStatement;
        } catch (Exception $e) {
            $this->logger->error('Update DraftStatement failed:', [$e]);

            return false;
        }
    }

    /**
     * Convert DraftStatementObject to legacy.
     *
     * @param DraftStatement|DraftStatementVersion|null $draftStatement
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function convertToLegacy($draftStatement)
    {
        if (is_null($draftStatement)) {
            return null;
        }
        $statementAttributes = $draftStatement->getStatementAttributes();
        $draftStatement = $this->entityHelper->toArray($draftStatement);
        if ($draftStatement['element'] instanceof Elements) {
            $draftStatement['element'] = $this->entityHelper->toArray($draftStatement['element']);
            if ($draftStatement['element']['documents'] instanceof Collection) {
                $draftStatement['element']['documents'] = $this->entityHelper->toArray($draftStatement['element']['documents']);
            }
            if ($draftStatement['element']['organisations'] instanceof Collection) {
                $draftStatement['element']['organisations'] = $this->entityHelper->toArray($draftStatement['element']['organisations']);
            }
        }
        if ($draftStatement['paragraph'] instanceof ParagraphVersion) {
            try {
                // Legacy wird der Paragraph und nicht ParagraphVersion zurückgegeben!
                $parentParagraph = $draftStatement['paragraph']->getParagraph();
                $draftStatement['paragraph'] = $this->entityHelper->toArray($parentParagraph);
            } catch (Exception) {
                // Einige alte Einträge verweisen möcglicherweise noch nicht auf eine ParagraphVersion
                $this->logger->error('No ParagraphVersion found for Id '.DemosPlanTools::varExport($draftStatement['paragraph']->getId(), true));
                unset($draftStatement['paragraph']);
                $draftStatement['paragraphId'] = null;
            }
        } else {
            unset($draftStatement['paragraphId'], $draftStatement['paragraph']);
        }
        // Lege ein mit der Stellungnahme verknüpftes SingleDocument auf oberster Ebene in das Array
        if (!is_null($draftStatement['documentId'])) {
            $singleDocument = $this->singleDocumentVersionRepository->get($draftStatement['documentId']);
            $draftStatement['document'] = $this->entityHelper->toArray($singleDocument);
        } else {
            unset($draftStatement['documentId'], $draftStatement['documentTitle'], $draftStatement['document']);
        }

        if (count($statementAttributes) > 0) {
            $draftStatement['statementAttributesObject'] = $statementAttributes;
            $draftStatement['statementAttributes'] = [];
        }
        foreach ($statementAttributes as $sa) {
            if (isset($draftStatement['statementAttributes'][$sa->getType()])) {
                if (!is_array($draftStatement['statementAttributes'][$sa->getType()])) {
                    $v = $draftStatement['statementAttributes'][$sa->getType()];
                    $draftStatement['statementAttributes'][$sa->getType()] = [$v];
                } else {
                    $draftStatement['statementAttributes'][$sa->getType()][] = $sa->getValue();
                }
            } else {
                $draftStatement['statementAttributes'][$sa->getType()] = $sa->getValue();
            }
        }

        return $this->dateHelper->convertDatesToLegacy($draftStatement);
    }

    /**
     * Create and use versions of Paragraph & SingleDoc.
     *
     * @param array $data
     *
     * @return array $data
     *
     * @throws Exception
     */
    protected function getEntityVersions($data)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = null;

        // get existing entity to avoid creation of versions on already existing versions
        if (array_key_exists('ident', $data)) {
            $entity = $this->getDraftStatementObject($data['ident']);
        }

        if (array_key_exists('paragraph', $data) && $data['paragraph'] instanceof Paragraph) {
            // check whether existing paragraph equals given paragraphId
            if (is_null($entity) || $data['paragraph']->getId() != $entity->getParagraphId()) {
                $data['paragraph'] = $this->createParagraphVersion(
                    $data['paragraph']
                );
            }
        }
        if (array_key_exists('paragraphId', $data) && 0 < strlen((string) $data['paragraphId'])) {
            // check whether existing paragraph equals given paragraphId
            if (is_null($entity) || $data['paragraphId'] != $entity->getParagraphId()) {
                $data['paragraph'] = $this->createParagraphVersion(
                    $em->find(
                        Paragraph::class,
                        $data['paragraphId']
                    )
                );
            }
        }

        if (array_key_exists('document', $data) && $data['document'] instanceof SingleDocument) {
            // check whether existing document equals given documentId
            if (is_null($entity) || $data['document']->getId() != $entity->getDocumentId()) {
                $data['document'] = $this->createSingleDocumentVersion(
                    $data['document']
                );
            }
        }
        if (array_key_exists('documentId', $data) && 0 < strlen((string) $data['documentId'])) {
            // check whether existing document equals given documentId
            if (is_null($entity) || $data['documentId'] != $entity->getDocumentId()) {
                $data['document'] = $this->createSingleDocumentVersion(
                    $em->find(
                        SingleDocument::class,
                        $data['documentId']
                    )
                );
            }
        }

        return $data;
    }

    /**
     * Creates and adds a Version of the given paragraph.
     *
     * @throws Exception
     */
    protected function createParagraphVersion(Paragraph $paragraph): ParagraphVersion
    {
        $paragraphVersion = $this->paragraphVersionRepository->createVersion($paragraph);

        return $this->paragraphVersionRepository->addObject($paragraphVersion);
    }

    /**
     * Create Version of SingleDocument.
     *
     * @return SingleDocumentVersion
     *
     * @throws Exception
     */
    protected function createSingleDocumentVersion(SingleDocument $singleDocument)
    {
        return $this->singleDocumentVersionRepository->createVersion($singleDocument);
    }

    /**
     * Convert Result to Legacy.
     *
     * @param array       $list
     * @param string      $procedureId
     * @param array|null  $filters
     * @param array|null  $sort
     * @param string|null $manualSortScope
     * @param array       $aggregation     Elasticsearch aggregation converted to legacy
     *
     * @internal param array $filter
     */
    protected function toLegacyResult($list, $procedureId, ?string $search = '', $filters = [], $sort = [], $manualSortScope = null, $aggregation = []): DraftStatementResult
    {
        $sorted = [];
        // Is the list manually sorted?
        $sorted['sorted'] = false;
        if (isset($manualSortScope) && 0 < strlen($manualSortScope)) {
            $sorted = $this->manualListSorter->orderByManualListSort($manualSortScope, $procedureId, 'draftStatement', $list);
            $list = $sorted['list'];
        }

        $filterSet = [
            'total'   => count($aggregation),
            'offset'  => 0,
            'limit'   => 0,
            'filters' => $aggregation,
        ];
        if (!is_null($filters)) {
            foreach ($filters as $filterKey => $filterValue) {
                // Wenn ein Filter in der Aggregation gefunden wurde, ist er via Interface ausgewählt und aktiv
                if (array_key_exists($filterKey, $aggregation)) {
                    $filterSet['activeFilters'][$filterKey] = $filterValue;
                }
            }
        }
        $sortingSet = [];
        if (!is_null($sort)) {
            $sortingSet[] = [
                'active'  => true,
                'sorting' => $sort['to'],
                'name'    => $sort['by'],
            ];
        }

        return new DraftStatementResult(
            $list,
            $filterSet,
            $sortingSet,
            count($list),
            $search ?? '',
            $sorted['sorted'] ?? false
        );
    }

    /**
     * Ruft die unveränderten freigegebenen Stellungnahmen eines Nutzers ab.
     *
     * @param string      $procedureId
     * @param array       $filters
     * @param string      $search
     * @param array|null  $sort            deprecated
     * @param User        $user
     * @param string|null $manualSortScope
     *
     * @return array DraftStatementVersionList
     *
     * @throws Exception
     */
    public function getDraftStatementReleasedOwnList(
        $procedureId,
        StatementListUserFilter $filters,
        $search,
        $sort,
        $user,
        $manualSortScope = null,
    ): DraftStatementResult {
        try {
            $results = $this->draftStatementVersionRepository->getOwnReleasedList(
                $procedureId,
                $user,
                $filters->getElement(),
                $search
            );

            $list = [];
            if (!is_null($results)) {
                foreach ($results as $result) {
                    $list[] = $this->convertToLegacy($result);
                }
            }

            $draftStatementIds = array_map(fn ($draftStatement) => $draftStatement->getId(), $results);

            // get Elasticsearch aggregations aka Userfilters
            // add user to Filter
            $filters->setSomeOnesUserId($user->getIdent());
            $aggregation = $this->getElasticsearchDraftStatementAggregationByIds($draftStatementIds, $procedureId, $user);

            return $this->toLegacyResult($list, $procedureId, $search, $filters->toArray(), $sort, $manualSortScope, $aggregation);
        } catch (Exception $e) {
            $this->logger->warning('get DraftStatament List failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * Given an array of Statement ids, returns Elasticsearch aggregations to use as facetted filters.
     *
     * @return array
     */
    protected function getElasticsearchDraftStatementAggregationByIds(array $ids, string $procedureId, User $user)
    {
        $aggregation = [];
        $boolQuery = new BoolQuery();

        try {
            $this->profilerStart('ES');

            // Base Filters to apply always
            $boolQuery->addMust(new Terms('_id', $ids));

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            // generate Aggregation

            $query = $this->addEsAggregation($query, 'oName.raw');
            $query = $this->addEsAggregation($query, 'dId');
            $query = $this->addEsAggregation($query, 'elementId');

            // we do not need results, only addgregation
            $query->setSize(0);

            $this->logger->debug('Elasticsearch DraftStatementList Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->getEsDraftStatementIndex();
            $draftStatements = $search->search($query);
            $aggregations = $draftStatements->getAggregations();

            // transform Buckets info existing Filterstructure
            $aggregation = [];
            if (isset($aggregations['oName.raw'])) {
                $aggregation['orga'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['oName.raw']['buckets']
                );
            }
            if (isset($aggregations['dId'])) {
                $departmentMap = $this->getDepartmentMap($user);
                $aggregation['department'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['dId']['buckets'],
                    $departmentMap
                );
            }
            if (isset($aggregations['elementId'])) {
                $elementMap = $this->getElementMap($procedureId);
                $aggregation['element'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['elementId']['buckets'],
                    $elementMap
                );
            }

            $this->profilerStop('ES');
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getDraftStatementAggregation failed.', [$e]);
        }

        return $aggregation;
    }

    /**
     * Gets Aggegations from Elasticsearch to use as facetted filters.
     *
     * @param string $procedureId
     * @param User   $user
     * @param string $search
     *
     * @return array
     */
    protected function getElasticsearchDraftStatementAggregation(StatementListUserFilter $userFilters, $procedureId, $user, $search = '', string $scope = '')
    {
        $aggregation = [];
        $boolQuery = new BoolQuery();
        // List may only be generated if orga is set
        if (is_null($procedureId)) {
            return $aggregation;
        }
        try {
            $this->profilerStart('ES');

            // if a Searchterm is set use it
            if (null !== $search && 0 < strlen($search)) {
                $baseQuery = new MatchQuery();
                $baseQuery->setFieldQuery('text', $search);
                $boolQuery->addMust($baseQuery);
            }

            // Base Filters to apply always
            $boolMustFilter = [
                new Terms('pId', [$procedureId]),
                new Terms('deleted', [false]),
            ];
            // usually include only own statements
            if (true !== $userFilters->getOtherCompaniesFilter()) {
                // own Organisation or all Statements of same GatewayGroup?
                if (is_null($user->getOrga()->getGatewayName()) || '' === $user->getOrga()->getGatewayName()) {
                    $boolMustFilter[] = new Terms('oId', [$user->getOrganisationId()]);
                } else {
                    $boolMustFilter[] = new Terms('oGatewayName', [$user->getOrga()->getGatewayName()]);
                }
            }

            if ('own' === $scope) {
                // 'own' means own organisation in this context
                // filters private drafts from orga if not owned by user
                $shouldBool = new BoolQuery();
                $shouldBool->addShould(new Terms('authorOnly', [false]));
                $shouldBool->addShould(new Terms('uId', [$userFilters->getSomeOnesUserId()]));
                $boolMustFilter[] = $shouldBool;
            } elseif (null !== $userFilters->getSomeOnesUserId()) {
                $uId = [$userFilters->getSomeOnesUserId()];
                $boolMustFilter[] = new Terms('uId', $uId);
            }

            // Filters set by users.
            if (null !== $userFilters->getReleased()) {
                $released = [$userFilters->getReleased()];
                $boolMustFilter[] = new Terms('released', $released);
            }
            if (null !== $userFilters->getSubmitted()) {
                $submitted = [$userFilters->getSubmitted()];
                $boolMustFilter[] = new Terms('submitted', $submitted);
            }
            if (null !== $userFilters->getElement()) {
                $element = [$userFilters->getElement()];
                $boolMustFilter[] = new Terms('elementId', $element);
            }
            if (null !== $userFilters->getDepartment()) {
                $department = [$userFilters->getDepartment()];
                $boolMustFilter[] = new Terms('dId', $department);
            }
            if (null !== $userFilters->getShowToAll()) {
                $showToAll = [$userFilters->getShowToAll()];
                $boolMustFilter[] = new Terms('showToAll', $showToAll);
            }

            array_map($boolQuery->addMust(...), $boolMustFilter);

            $boolMustNotFilter = [];

            // usually include only own statements
            if (true === $userFilters->getOtherCompaniesFilter()) {
                $boolMustNotFilter[] = new Terms('oId', [$user->getOrganisationId()]);
            }

            // do not include procedures in configuration
            if (0 < count($boolMustNotFilter)) {
                array_map($boolQuery->addMustNot(...), $boolMustNotFilter);
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            // generate Aggregation

            $query = $this->addEsAggregation($query, 'oName.raw');
            $query = $this->addEsAggregation($query, 'dId');
            $query = $this->addEsAggregation($query, 'elementId');

            // we do not need results, only addgregation
            $query->setSize(0);

            $this->logger->debug('Elasticsearch DraftStatementList Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $this->getEsDraftStatementIndex();
            $draftStatements = $search->search($query);
            $aggregations = $draftStatements->getAggregations();

            // transform Buckets info existing Filterstructure
            $aggregation = [];
            if (isset($aggregations['oName.raw'])) {
                $aggregation['orga'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['oName.raw']['buckets']
                );
            }
            if (isset($aggregations['dId'])) {
                $departmentMap = $this->getDepartmentMap($user);
                $aggregation['department'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['dId']['buckets'],
                    $departmentMap
                );
            }
            if (isset($aggregations['elementId'])) {
                $elementMap = $this->getElementMap($procedureId);
                $aggregation['element'] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
                    $aggregations['elementId']['buckets'],
                    $elementMap
                );
            }

            $this->profilerStop('ES');
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch getDraftStatementAggregation failed.', [$e]);
        }

        return $aggregation;
    }

    /**
     * @return Index
     */
    protected function getEsDraftStatementIndex()
    {
        return $this->esDraftStatementIndex;
    }

    /**
     * @param Index $esDraftStatementIndex
     */
    public function setEsDraftStatementIndex($esDraftStatementIndex)
    {
        $this->esDraftStatementIndex = $esDraftStatementIndex;
    }

    protected function getOrgaService(): OrgaService
    {
        return $this->orgaService;
    }

    /**
     * @return MapService
     */
    protected function getServiceMap()
    {
        return $this->serviceMap;
    }

    /**
     * Create Label => Value map of procedureelements.
     *
     * @param string $procedureId
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getElementMap($procedureId)
    {
        $elementMap = [];
        $elementList = $this->elementsService->getElementsListObjects($procedureId);
        if (is_null($elementList)) {
            return $elementMap;
        }
        /** @var Elements $element */
        foreach ($elementList as $element) {
            $elementMap[$element->getId()] = $element->getTitle();
        }

        return $elementMap;
    }

    /**
     * Create Label => Value map of Organisation departments.
     *
     * @param User $user
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getDepartmentMap($user)
    {
        $departmentMap = [];
        $orgaIds = [];

        if (is_null($user->getOrga()->getGatewayName()) || '' === $user->getOrga()->getGatewayName()) {
            $orgaIds = [$user->getOrganisationId()];
        } else {
            $orgaEntities = $this->getOrgaService()->getOrgaByFields(['gatewayName' => $user->getOrga()->getGatewayName()]);
            /** @var Orga $orgaEntity */
            foreach ($orgaEntities as $orgaEntity) {
                $orgaIds[] = $orgaEntity->getId();
            }
        }

        foreach ($orgaIds as $orgaId) {
            $orga = $this->getOrgaService()->getOrga($orgaId);
            if (is_null($orga)) {
                return $departmentMap;
            }
            $departmentList = $orga->getDepartments();

            /** @var Department $department */
            foreach ($departmentList as $department) {
                $departmentMap[$department->getId()] = $department->getName();
            }
        }

        return $departmentMap;
    }

    /**
     * @param array $sort $sort('by' => '', 'to' => 'ASC')
     */
    protected function createSortMethod($sort): SortMethodInterface
    {
        $sortProperty = 'createdDate';

        if (is_array($sort) && array_key_exists('by', $sort)) {
            switch ($sort['by']) {
                case 'createdDate':
                    $sortProperty = 'createdDate';
                    break;
                case 'submittedDate':
                    $sortProperty = 'submittedDate';
                    break;
                case 'department':
                    $sortProperty = 'dName';
                    break;
                default:
                    break;
            }
        }

        $sortDirection = $this->determineDirection($sort, true);

        return ('asc' === $sortDirection)
            ? $this->sortMethodFactory->propertyAscending([$sortProperty])
            : $this->sortMethodFactory->propertyDescending([$sortProperty]);
    }

    /**
     * @param array $sort $sort('by' => '', 'to' => 'ASC')
     */
    protected function createSortMethodsByAssociation($sort): array
    {
        $sortDir = $this->determineDirection($sort, false);

        $sortMethodPaths = [];
        if (is_array($sort) && array_key_exists('by', $sort)) {
            switch ($sort['by']) {
                case 'paragraph':
                    $sortMethodPaths[] = ['document', 'element', 'order'];
                    $sortMethodPaths[] = ['paragraph', 'order'];
                    break;
                case 'document':
                    $sortMethodPaths[] = ['element', 'title'];
                    break;
                default:
                    break;
            }
        }

        return collect($sortMethodPaths)->map(fn (array $path): SortMethodInterface => 'asc' === $sortDir
            ? $this->sortMethodFactory->propertyAscending($path)
            : $this->sortMethodFactory->propertyDescending($path))->all();
    }

    /**
     * @param array $sort $sort('by' => '', 'to' => 'ASC')
     *
     * @return string either `asc` or `desc`
     */
    protected function determineDirection($sort, bool $defaultAsc): string
    {
        $sortDir = $defaultAsc ? 'asc' : 'desc';
        if (is_array($sort) && array_key_exists('to', $sort)) {
            $allowedDirs = ['asc', 'desc'];
            if (in_array(strtolower((string) $sort['to']), $allowedDirs)) {
                $sortDir = $sort['to'];
            }
        }

        return $sortDir;
    }

    /**
     * @param string      $key
     * @param string|null $orderBy
     * @param string|null $orderDir asc|desc
     *
     * @return Query
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/guide/1.x/_intrinsic_sorts.html
     */
    protected function addEsAggregation(Query $query, $key, $orderBy = null, $orderDir = null)
    {
        $aggPriority = new \Elastica\Aggregation\Terms($key);
        $aggPriority->setField($key);
        $aggPriority->setSize(10000);
        if (!is_null($orderBy)) {
            $aggPriority->setOrder($orderBy, $orderDir);
        }
        $query->addAggregation($aggPriority);

        return $query;
    }

    /**
     * Adds PriorityArea Values to the Attributes.
     *
     * @param array                        $data
     * @param DraftStatement               $draftStatement
     * @param StatementAttributeRepository $attrRepo
     */
    protected function addPriorityAreaAttribute($data, $draftStatement, $attrRepo)
    {
        if (array_key_exists(
            'priorityAreaKey',
            $data['statementAttributes']
        ) && 0 < strlen((string) $data['statementAttributes']['priorityAreaKey'])
        ) {
            try {
                $dataKey = [
                    'draftStatement' => $draftStatement,
                    'type'           => 'priorityAreaKey',
                    'value'          => $data['statementAttributes']['priorityAreaKey'],
                ];
                $attrRepo->add($dataKey);

                $dataType = [
                    'draftStatement' => $draftStatement,
                    'type'           => 'priorityAreaType',
                    'value'          => $data['statementAttributes']['priorityAreaType'],
                ];
                $attrRepo->add($dataType);
            } catch (Exception $e) {
                $this->getLogger()->warning(
                    'add priorityAreaKey to DraftStatement failed: '.$e
                );
            }
        }
    }

    /**
     * Removes priorityAreaAttributes from given draftStatement, creates a new one and add it as related attribute.
     *
     * @param DraftStatement               $draftStatement
     * @param array                        $data
     * @param StatementAttributeRepository $statementAttributeRepository
     *
     * @throws Exception
     */
    protected function setPriorityAreaAttributes($data, $draftStatement, $statementAttributeRepository)
    {
        $statementAttributeRepository->removePriorityAreaAttributes($draftStatement);
        $this->addPriorityAreaAttribute(
            $data,
            $draftStatement,
            $statementAttributeRepository
        );
    }

    /**
     * @param string $organisationId - identifies the organisation, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getDraftStatementsOfOrga($organisationId)
    {
        return $this->draftStatementRepository->getAllDraftStatementsOfOrga($organisationId);
    }

    /**
     * @param string $departmentId - identifies the department, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getDraftStatementsOfDepartment($departmentId)
    {
        return $this->draftStatementRepository->getAllDraftStatementsOfDepartment($departmentId);
    }

    /**
     * @param string $userId - identifies the department, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getDeletableDraftStatementOfUser($userId)
    {
        return $this->draftStatementRepository->getDeletableDraftStatementOfUser($userId);
    }

    /**
     * Deletes all draftStatements of an organisation.
     *
     * @param string $organisationId - identifies the organisation, whose draftstatements will be deleted
     *
     * @return bool - true if draftStatements was successfully deleted, otherwise false
     */
    public function deleteDraftStatementsOfOrga($organisationId)
    {
        try {
            $draftStatements = $this->getDraftStatementsOfOrga($organisationId);
            // @improve T12803
            $this->deleteDraftStatements($draftStatements);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der draftStatements: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Determines the category ID of the statement data.
     * Every Procedure has his own category ids.
     * A Statement only can have one category.
     *
     * @param string $procedureId Procedure
     * @param array  $data        incoming procedure data
     *
     * @return string result category-ID
     *
     * @throws Exception
     */
    public function determineStatementCategory($procedureId, $data)
    {
        $elementService = $this->elementsService;
        $determinedElementId = '';
        // get standard element:
        $defaultStatementElement = $elementService->getStatementElement($procedureId);
        if ($defaultStatementElement instanceof Elements) {
            $determinedElementId = $defaultStatementElement->getId();
        }

        if (array_key_exists('elementId', $data)) {
            $data['r_element_id'] = $data['elementId'];
        }

        if (array_key_exists('element_id', $data)) {
            $data['r_element_id'] = $data['element_id'];
        }

        // overwrite with current if draftStatement is given:
        if (array_key_exists('r_ident', $data)) {
            $currentDraftStatement = $this->getDraftStatementObject($data['r_ident']);
            $determinedElementId = $currentDraftStatement instanceof DraftStatement ? $currentDraftStatement->getElementId() : '';
        }

        // #0.5 document without attached paragraph
        if (array_key_exists('r_element_id', $data)) {
            // if '' or null given as elementId -> means reset to defaultStatementElement:
            if ('' === $data['r_element_id'] || is_null($data['r_element_id'])) {
                $determinedElementId = '';
                if ($defaultStatementElement instanceof Elements) {
                    $determinedElementId = $defaultStatementElement->getId();
                }
            } else {
                $determinedElementId = $data['r_element_id'];
            }
        }

        // #1: Einzeichnung/Planzeichnung:
        $geoData = $this->extractGeoData($data, []);
        if (array_key_exists('polygon', $geoData) && 0 !== strlen((string) $geoData['polygon'])) {
            $determinedElementId = $elementService->getMapElement($procedureId)->getId();
        }

        // #2: get values for negative Report (Fehlanzeige), if set
        if (array_key_exists('r_isNegativeReport', $data)) {
            if ('1' === $data['r_isNegativeReport']) {
                $negativeReportElement = $elementService->getNegativeReportElement($procedureId);
                if ($negativeReportElement instanceof Elements) {
                    // set element category into data array to be processed later
                    $determinedElementId = $negativeReportElement->getId();
                }
            }
            if ('0' === $data['r_isNegativeReport']) {
                $defaultStatementElement = $elementService->getStatementElement($procedureId);
                if ($defaultStatementElement instanceof Elements) {
                    // set element category into data array to be processed later
                    $determinedElementId = $defaultStatementElement->getId();
                }
            }
        }

        // #3: category: dokument
        if (array_key_exists('r_document_id', $data) && 0 !== strlen((string) $data['r_document_id'])) {
            $determinedElementId = $data['r_element_id'];
        }
        if (array_key_exists('r_documentID', $data) && 0 !== strlen((string) $data['r_documentID'])) {
            $determinedElementId = array_key_exists('r_elementID', $data) ? $data['r_elementID'] : '';
        }

        // #4: strongest category: absatz
        if (array_key_exists('r_paragraph_id', $data) && 0 !== strlen((string) $data['r_paragraph_id'])) {
            $determinedElementId = $data['r_element_id'];
        }
        if (array_key_exists('r_paragraphID', $data) && 0 !== strlen((string) $data['r_paragraphID'])) {
            $determinedElementId = array_key_exists('r_elementID', $data) ? $data['r_elementID'] : '';
        }

        return $determinedElementId;
    }

    /**
     * Generate StatementAttributes from geoData.
     *
     * @param array $data
     * @param array $statement
     *
     * @return array
     */
    public function extractGeoData($data, $statement)
    {
        if (array_key_exists('r_location', $data) && 'notLocated' === $data['r_location']) {
            $statement['statementAttributes']['noLocation'] = true;
            // delete existing spatial data
            $statement['polygon'] = '';
            $statement['statementAttributes']['priorityAreaKey'] = '';
            $statement['statementAttributes']['priorityAreaType'] = '';
        }

        // in 3 Fällen wird r_location == point übergeben: Ortsbezug, Vorranggebietsauswahl und Ortseinzeichung
        if (array_key_exists('r_location', $data) && 'point' === $data['r_location']) {
            // Punkteinzeichnung
            if (array_key_exists('r_location_geometry', $data) && 0 < strlen((string) $data['r_location_geometry'])) {
                $statement['polygon'] = $data['r_location_geometry'];
            }

            // Vorranggebiet
            if (array_key_exists('r_location_priority_area_key', $data) && 0 < strlen((string) $data['r_location_priority_area_key'])) {
                $statement['statementAttributes']['priorityAreaKey'] = $data['r_location_priority_area_key'];
                $statement['statementAttributes']['priorityAreaType'] = $data['r_location_priority_area_type'];
            }

            // Ortsbezug
            if (array_key_exists('r_location_point', $data) && 0 < strlen((string) $data['r_location_point'])) {
                try {
                    // wandle die Punktkoordinate in ein valides GeoJson um
                    $statement['polygon'] = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":['.$data['r_location_point'].']},"properties":null}]}';
                } catch (Exception $e) {
                    $this->logger->warning('Could not create Point Polygon', ['data' => $data['r_location_point'], 'exception' => $e]);
                }
            }
        }

        if (array_key_exists('r_county', $data)) {
            $statement['statementAttributes']['county'] = '';

            if (0 < strlen((string) $data['r_county'])) {
                $statement['statementAttributes']['county'] = $data['r_county'];
            }
        }

        return $statement;
    }

    /**
     * @param string[] $draftStatementIds
     *
     * @return array<int,DraftStatement>
     */
    public function getByIds(array $draftStatementIds): array
    {
        return $this->draftStatementRepository->findBy(['id' => $draftStatementIds]);
    }

    /**
     * @throws UserNotFoundException
     */
    public function findCurrentUserDraftStatements(string $procedureId): array
    {
        return $this->draftStatementRepository->findBy(
            [
                'user'      => $this->currentUser->getUser()->getId(),
                'procedure' => $procedureId,
            ]
        );
    }
}
