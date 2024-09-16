<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Pagination\PagePagination;
use Exception;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportService extends CoreService
{
    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;

    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly CustomerHandler $customerHandler,
        private readonly ReportRepository $reportRepository,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ViolationsException
     */
    public function persistAndFlushReportEntries(ReportEntry ...$reportEntries): void
    {
        $this->reportRepository->executeAndFlushInTransaction(
            function () use ($reportEntries) {
                $this->persistReportEntries($reportEntries);

                return null;
            }
        );
    }

    /**
     * @throws ViolationsException
     */
    public function persistAndFlushReportEntry(ReportEntry $reportEntry): void
    {
        $violations = $this->validator->validate($reportEntry);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }
        $this->reportRepository->addObject($reportEntry);
    }

    /**
     * Is the access of the statement from the user already logged?
     *
     * @param string $procedureId
     * @param string $statementId
     *
     * @return bool
     */
    public function statementViewLogged($procedureId, User $user, $statementId)
    {
        $report = $this->getDoctrine()
            ->getRepository(ReportEntry::class)
            ->findBy([
                'category'       => 'view',
                'userId'         => $user->getId(),
                'identifierType' => 'procedure',
                'identifier'     => $procedureId,
                'message'        => Json::encode(['statementId' => $statementId]),
            ]);

        return 0 < count($report);
    }

    /**
     * Reporteinträge zur Sichtbarkeitsänderung von InvitableInstitution.
     *
     * @throws Exception
     */
    public function getInvitableInstitutionShowlistChanges(): Pagerfanta
    {
        $currentCustomer = $this->customerHandler->getCurrentCustomer();
        $conditions = [
            $this->conditionFactory->propertyHasValue('orgaShowlistChange', ['category']),
            $this->conditionFactory->propertyHasValue('orga', ['group']),
            $this->conditionFactory->propertyHasValue($currentCustomer, ['customer']),
        ];

        $sorting = $this->sortMethodFactory->propertyDescending(['createDate']);
        $pagination = new PagePagination(9_999_999, 1);

        return $this->reportRepository->getEntitiesForPage($conditions, [$sorting], $pagination);
    }

    /**
     * Anonymize user data of report entries of this statement.
     * In case of no userId is given, anonymize the author data as well as the submitter data in the report entry.
     * There is currently only one case in which the author and submitter are different persons/users:
     * The statement is authored by a "Sachbearbeiter" and submitted by a "Koordinator".
     *
     * In the other cases:
     * (manual statement, authored by registered citizen, authored by unregistered citizen or authored by "Koordinator"),
     * the author will also be the submitter.
     * Therefore the data of the author and the data of the submitter will be anonymized.
     *
     * @param Statement $statement statement, whose ReportEntry will be searched
     * @param string    $userId    Identifies the user, which data should be anonymized on the given statement.
     *                             In case no Id is given, author- and submitter- data will be anonymized.
     *
     * @throws NotYetImplementedException will thrown in case of no userId is given but the submitter is not the author
     */
    public function anonymizeUserDataOfStatementReportEntries(Statement $statement, string $userId): Statement
    {
        $anonymizeSubmitterData = true;
        $anonymizeAuthorData = true;

        if (User::ANONYMOUS_USER_ID !== $userId && null !== $userId) {
            $anonymizeSubmitterData = $statement->isSubmitter($userId);
            $anonymizeAuthorData = $statement->isAuthor($userId);

            // anonymize data of user is more or less deprecated
            // instead via UI only submitter can revoke -> submitterdata as well as authordata will be anonymized
            if ($statement->isSubmitter($userId)
                && $statement->hasBeenAuthoredByInstitutionSachbearbeiterAndSubmittedByInstitutionKoordinator()) {
                // Means koordiantor is revoking GDPR-Consent, also anonyimize data of Sachbearbeiter:
                $anonymizeAuthorData = true;
            }
        }

        $isManualStatement = $statement->isManual();
        $reportEntriesToAnonymize = $this->reportRepository->getReportsOfStatement($statement);
        foreach ($reportEntriesToAnonymize as $reportEntry) {
            try {
                $this->overwriteUserDataOfStatementReportEntry(
                    $reportEntry,
                    $anonymizeSubmitterData,
                    $anonymizeAuthorData,
                    $isManualStatement
                );
            } catch (Exception $e) {
                $this->getLogger()->error('Error on anonymize user data of EeportEntry:'.$e);
            }
        }

        return $statement;
    }

    /**
     * 1005.
     *
     * Anonymize author- and/or submitter- data of a specific report entry of a statement.
     *
     * @param ReportEntry $reportEntry            the statement ReportEntry to anonymize
     * @param bool        $anonymizeSubmitterData true, if the user, whose data should be anonymize, is
     *                                            the submitter
     * @param bool        $anonymizeAuthorData    true, if the user, whose data should be anonymize, is
     *                                            the author
     * @param bool        $isManualStatement      true, in case of the related statement of the given
     *                                            report entry to anonymize, is a manual statement
     */
    public function overwriteUserDataOfStatementReportEntry(ReportEntry $reportEntry, bool $anonymizeSubmitterData, bool $anonymizeAuthorData, bool $isManualStatement)
    {
        $statementArray = $reportEntry->getMessageDecoded(true);

        if ($anonymizeSubmitterData) {
            $statementArray = $this->anonymizeSubmitUserData($statementArray);
        }

        if ($anonymizeAuthorData) {
            $statementArray = $this->anonymizeAuthorUserData($statementArray);
        }

        // In case of statement is not a manual statement the data of the author are stored directly on the report.
        if (!$isManualStatement
            && ReportEntry::CATEGORY_ADD === $reportEntry->getCategory()
            && ReportEntry::GROUP_STATEMENT === $reportEntry->getGroup()
        ) {
            $reportEntry->setUser(null);
            $reportEntry->setUserName($this->translator->trans('anonymized'));
        }

        $reportEntry->setMessage($statementArray);
        $this->reportRepository->updateObject($reportEntry);
    }

    /**
     * Remove/overwrite relational address data.
     * These fields can be only filled on statements of registered or unregistered users.
     * (In case of statement by organisation, meta->submitOrgaId is used.).
     */
    protected function anonymizeAddressData(array $decodedMessage): array
    {
        if (array_key_exists('meta', $decodedMessage)) {
            $decodedMessage['meta'] = $this->overwriteIfExists('orgaStreet', $decodedMessage['meta']);
            $decodedMessage['meta'] = $this->overwriteIfExists('houseNumber', $decodedMessage['meta']);
            $decodedMessage['meta'] = $this->overwriteIfExists('orgaEmail', $decodedMessage['meta']);
            $decodedMessage['meta'] = $this->overwriteIfExists('orgaPostalCode', $decodedMessage['meta']);
            $decodedMessage['meta'] = $this->overwriteIfExists('orgaCity', $decodedMessage['meta']);
        }

        return $decodedMessage;
    }

    /**
     * Remove/overwrite relational data of the submitter in given $decodedMessage.
     */
    protected function anonymizeSubmitUserData(array $decodedMessage): array
    {
        if (array_key_exists('meta', $decodedMessage)) {
            $decodedMessage['meta'] = $this->overwriteIfExists('submitUId', $decodedMessage['meta']);
            $decodedMessage['meta'] = $this->overwriteIfExists('submitName', $decodedMessage['meta'], $this->translator->trans('anonymized'));
        }

        return $this->anonymizeAddressData($decodedMessage);
    }

    /**
     * Remove/overwrite relational data of the author in given $decodedMessage.
     */
    protected function anonymizeAuthorUserData(array $decodedMessage): array
    {
        $decodedMessage = $this->overwriteIfExists('user', $decodedMessage);
        $decodedMessage = $this->overwriteIfExists('uId', $decodedMessage);
        $decodedMessage = $this->overwriteIfExists('uName', $decodedMessage, $this->translator->trans('anonymized'));
        if (array_key_exists('meta', $decodedMessage)) {
            $decodedMessage['meta'] = $this->overwriteIfExists('authorName', $decodedMessage['meta'], $this->translator->trans('anonymized'));
        }

        return $this->anonymizeAddressData($decodedMessage);
    }

    /**
     * Will overwrite the value of the given key in the given array, if existing.
     *
     * @param string $key   key to check for existing in given array and overwriting the related value
     * @param array  $array array in which will be searched the given key
     * @param string $value string wich will be used, to overwrite existing data in $array[$key]
     */
    protected function overwriteIfExists(string $key, array $array, string $value = ''): array
    {
        if (array_key_exists($key, $array)) {
            $array[$key] = $value;
        }

        return $array;
    }

    /**
     * Depending on incoming information in $event,
     * this method creates zero or multiple ReportEntries for anonymization of a statement.
     * The process is wrapped in a transaction to ensure the creation of reports can be cancelled in case of exceptions.
     * By setting occurred Exception into event, the following process of anonymization can be cancelled as well.
     *
     * @param StatementAnonymizeRpcEvent $event
     */
    public function addReportsOnStatementAnonymization($event): StatementAnonymizeRpcEvent
    {
        $doctrineConnection = $this->getDoctrine()->getConnection();
        $doctrineConnection->beginTransaction();
        try {
            if ($event->isAnonymizeStatementMeta()) {
                $this->addAnonymizationReport(ReportEntry::CATEGORY_ANONYMIZE_META, $event);
            }

            if ($event->isAnonymizeStatementText()) {
                $this->addAnonymizationReport(ReportEntry::CATEGORY_ANONYMIZE_TEXT, $event);
            }

            if ($event->isDeleteStatementTextHistory()) {
                $this->addAnonymizationReport(ReportEntry::CATEGORY_DELETE_TEXT_FIELD_HISTORY, $event);
            }

            if ($event->isDeleteStatementAttachments()) {
                $this->addAnonymizationReport(ReportEntry::CATEGORY_DELETE_ATTACHMENTS, $event);
            }
            $doctrineConnection->commit();
        } catch (Exception $exception) {
            $event->setException($exception);
            $event->stopPropagation();
            $doctrineConnection->rollBack();
        }

        return $event;
    }

    /**
     * This helper method creates an statement anonymize  report entry.
     * Depending on values in group and category the resulting report will be different in detail.
     *
     * @param string                     $category Defines the category of the ReportEntry
     * @param StatementAnonymizeRpcEvent $event    Holds the elementary information to make the ReportEntry an AnonymizationReportEntry
     *
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    protected function addAnonymizationReport(string $category, StatementAnonymizeRpcEvent $event): ReportEntry
    {
        $report = $this->statementReportEntryFactory->createAnonymizationEntry($category, $event);

        return $this->reportRepository->addObject($report);
    }

    public function persistReportEntries(array $reportEntries): void
    {
        foreach ($reportEntries as $reportEntry) {
            $violations = $this->validator->validate($reportEntry);
            if (0 !== $violations->count()) {
                throw ViolationsException::fromConstraintViolationList($violations);
            }
            $this->entityManager->persist($reportEntry);
        }
    }
}
