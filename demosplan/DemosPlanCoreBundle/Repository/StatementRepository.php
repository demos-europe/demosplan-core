<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use Carbon\Carbon;
use DateInterval;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsentRevokeToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementLike;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVersionField;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Statement\AdditionalStatementDataEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateInternIdException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadyConnectedToGdprConsentRevokeTokenException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\FluentStatementQuery;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\FluentQueries\FluentQuery;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\Reindexer;
use Exception;
use Illuminate\Support\Collection;
use Pagerfanta\Pagerfanta;
use ReflectionException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_combine;

/**
 * @template-extends CoreRepository<Statement>
 */
class StatementRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    public function __construct(
        DqlConditionFactory $dqlConditionFactory,
        Reindexer $reindexer,
        private readonly EventDispatcherInterface $eventDispatcher,
        ManagerRegistry $registry,
        SortMethodFactory $sortMethodFactory,
        string $entityClass,
        private readonly CustomerService $customerService
    ) {
        parent::__construct($dqlConditionFactory, $registry, $reindexer, $sortMethodFactory, $entityClass);
    }

    /**
     * @return FluentStatementQuery
     */
    public function createFluentQuery(): FluentQuery
    {
        return new FluentStatementQuery($this->conditionFactory, $this->sortMethodFactory, $this->objectProvider);
    }

    /**
     * Get Entity by Id, add files.
     *
     * @param string $entityId
     *
     * @return Statement|null
     *
     * @throws Exception
     */
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['id' => $entityId]);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get all Files of a procedure.
     *
     * @param string $procedureId
     *
     * @return array|null
     */
    public function getFilesByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('s.file')
            ->addSelect('s.mapFile')
            ->from(Statement::class, 's')
            ->where('s.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of Statements failed ', [$e]);

            return null;
        }
    }

    /**
     * Add new ClusterStatement, what basically is a Statement with a cluster of Statements.
     *
     * @return Statement - headStatement of the created statement-cluster
     *
     * @throws Exception
     */
    public function addCluster(Statement $headStatement, array $statementIdsToCluster)
    {
        try {
            $manager = $this->getEntityManager();
            $statements = [];

            foreach ($statementIdsToCluster as $statementId) {
                if (is_string($statementId)) {
                    $statements[] = $manager->getReference(Statement::class, $statementId);
                } else {
                    $statements[] = $statementId;
                }
            }

            // only if there statements left to generate a cluster, create the headStatement
            if (0 < count($statements)) {
                $headStatement = $this->addObject($headStatement);
                $headStatement->setCluster($statements);
                $manager->persist($headStatement);
                $manager->flush();
            }

            return $headStatement;
        } catch (Exception $e) {
            $this->logger->warning('Create Statement failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entityobject to database.
     *
     * @param Statement $statement
     *
     * @throws Exception
     */
    public function addObject($statement): Statement
    {
        try {
            $manager = $this->getEntityManager();
            if (is_null($statement->getMeta())) {
                $statement->setMeta(new StatementMeta());
            }
            $statement->setText($this->sanitize($statement->getText(), [$this->obscureTag]));
            $manager->persist($statement);
            $manager->flush();

            return $statement;
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'internId_procedure')) {
                throw DuplicateInternIdException::create('Eingangsnummer', $statement->getProcedureId());
            }
            throw $e;
        } catch (Exception $e) {
            $this->getLogger()->warning('Add StatementObject failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return Statement
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            if (!array_key_exists('pId', $data)) {
                throw new \InvalidArgumentException('Trying to add a statement without ProcedureKey pId');
            }

            $statement = new Statement();
            $statement->setMeta(new StatementMeta());
            $statement = $this->generateObjectValues($statement, $data);

            return $this->addObject($statement);
        } catch (Exception $e) {
            $this->logger->warning('Create Statement failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Submit Draft Statement.
     *
     * @param DraftStatement            $draftStatement
     * @param User                      $user
     * @param NotificationReceiver|null $notificationReceiver
     * @param bool                      $gdprConsentReceived  true if the GDPR consent was received from the submitter
     *
     * @return Statement|object
     *
     * @throws Exception
     */
    public function submitDraftStatement($draftStatement, $user, $notificationReceiver = null, bool $gdprConsentReceived = false)
    {
        try {
            $em = $this->getEntityManager();

            $statement = new Statement();
            $statement->setMeta(new StatementMeta());

            $statement = $this->generateObjectValuesFromObject($statement, $draftStatement, $em);

            // reichere die restlichen Werte an, die nicht automatisch befüllt werden können
            $statement->setExternId($draftStatement->getNumber());
            $statement->setDraftStatement($draftStatement);
            $statement->setPublicStatement($draftStatement->getPublicDraftStatement());
            // In der Regel müssen Stellungnahmen nicht überprüft werden
            $statement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
            // Hinweis für den Fachplaner, dass die SN überprüft werden muss
            if ($draftStatement->isPublicAllowed()) {
                $statement->setPublicVerified(Statement::PUBLICATION_PENDING);
            }
            $statement->setSubmit(new DateTime());
            $statement->setDeletedDate(new DateTime());
            $statementMeta = $statement->getMeta();
            // speichere bei InstitutionStellungnahmen den Einreicher
            if (DraftStatement::INTERNAL === $draftStatement->getPublicDraftStatement()) {
                $statementMeta->setSubmitUId($user->getIdent());
                $statementMeta->setSubmitName($user->getFullname());
            }
            $statementMeta->setAuthorName($draftStatement->getUName());
            $statementMeta->setOrgaName($draftStatement->getOName());
            $statementMeta->setOrgaDepartmentName($draftStatement->getDName());
            $statementMeta->setOrgaDepartmentName($draftStatement->getDName());
            $statementMeta->setOrgaStreet($draftStatement->getUStreet());
            $statementMeta->setOrgaPostalCode($draftStatement->getUPostalCode());
            $statementMeta->setOrgaCity($draftStatement->getUCity());
            $statementMeta->setOrgaEmail($draftStatement->getUEmail());
            $statementMeta->setAuthoredDate(new DateTime());
            $statementMeta->setHouseNumber($draftStatement->getHouseNumber());
            $statementMeta->setAuthorFeedback($draftStatement->getUFeedback());
            // Bundesteilhabegesetz
            $statementMeta->setMiscDataValue(
                StatementMeta::USER_GROUP, $draftStatement->getMiscDataValue(StatementMeta::USER_GROUP)
            );
            $statementMeta->setMiscDataValue(
                StatementMeta::USER_ORGANISATION, $draftStatement->getMiscDataValue(StatementMeta::USER_ORGANISATION)
            );
            $statementMeta->setMiscDataValue(
                StatementMeta::USER_POSITION, $draftStatement->getMiscDataValue(StatementMeta::USER_POSITION)
            );
            $statementMeta->setMiscDataValue(
                StatementMeta::USER_STATE, $draftStatement->getMiscDataValue(StatementMeta::USER_STATE)
            );
            // unvalidated data given by unregistered user
            $statementMeta->setMiscDataValue(
                StatementMeta::SUBMITTER_ROLE, $draftStatement->getMiscDataValue(StatementMeta::SUBMITTER_ROLE)
            );
            $statementMeta->setMiscDataValue(
                StatementMeta::USER_PHONE, $draftStatement->getMiscDataValue(StatementMeta::USER_PHONE)
            );

            if (!is_null($notificationReceiver)) {
                $statement->setCountyNotified(true);
                $statement->setText(
                    $statement->getText().'<p>'.$notificationReceiver->getLabel().' wurde über diese Stellungnahme benachrichtigt.</p>'
                );
            }

            if (!$statement->isOriginal()) {
                throw new InvalidDataException('Expected original statement: GDPR consent must only be added to Statement entities which are original statements');
            }

            if ($statement->isManual()) {
                throw new InvalidDataException('Expected non-manual statement: manual statements are handled elsewhere');
            }

            // add GDPR consent to original statement
            $consenteeIds = $this->getInitialConsenteeIds($statement);

            // handle submit consent
            $gdprConsent = new GdprConsent();
            $gdprConsent->setStatement($statement);

            if ($gdprConsentReceived) {
                $gdprConsent->setConsentReceivedDate($statement->getSubmitObject());
                $gdprConsent->setConsentReceived(true);
            }
            try {
                $submitConsentee = $em->getRepository(User::class)->find($consenteeIds['submitter']);
            } catch (ORMException) {
                $submitConsentee = null;
            }

            $gdprConsent->setConsentee($submitConsentee);
            $statement->setGdprConsent($gdprConsent);

            // TODO: handle author consent here
            // Currently Statements and GdprConsents are connected by a one-to-one
            // relation where only the submitter consent is stored. The author consent
            // needs to be handled separatly later.

            // GdprConsent will be automatically persisted when the statement is persisted (persist cascade)
            $em->persist($statement);
            $em->flush();

            // StatementAttribute "county" must be passed on to counties-field
            foreach ($draftStatement->getStatementAttributes() as $sa) {
                if ('county' === $sa->getType()) {
                    /** @var CountyRepository $countyRepository */
                    $countyRepository = $em->getRepository(County::class);
                    $county = $countyRepository->get($sa->getValue());
                    if (!is_null($county)) {
                        $statement->addCounty($county);
                    }
                }
            }

            return $statement;
        } catch (Exception $e) {
            $this->logger->warning('Create Statement failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return Statement
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $statement = $this->get($entityId);
            $statement = $this->generateObjectValues($statement, $data);

            $em->persist($statement);
            $em->flush();

            return $statement;
        } catch (Exception $e) {
            $this->logger->warning(
                'Update Statement failed. Message: ', [$e, $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * @param Statement $statement Must always be a Statement object. null will result in a null pointer exception.
     *                             Type enforcement currently not possible due to parent method.
     *
     * @return Statement The updated statement. Always an object. Never null.
     *
     * @throws Exception
     */
    public function updateObject($statement)
    {
        try {
            $em = $this->getEntityManager();
            $statement->setText($this->sanitize($statement->getText(), [$this->obscureTag]));
            $statement = $this->ensureHasMeta($statement);
            $em->persist($statement);
            $em->flush();

            return $statement;
        } catch (Exception $e) {
            $this->logger->warning(
                'Update Statement failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Unlike {@see updateObject()} this method allows the caller to be sure
     * what to expect as return type.
     *
     * @throws Exception
     */
    public function updateStatementObject(Statement $statement): Statement
    {
        return $this->updateObject($statement);
    }

    public function getFirstUnclaimedSegmentableStatement(string $procedureId): ?Statement
    {
        $query = $this->createFluentQuery();
        $query->getConditionDefinition()
            ->inProcedureWithId($procedureId)
            ->unassigned()
            ->isNonOriginal()
            ->hasNoSegments($procedureId)
            ->notClusterRelated();

        $sliceDefinition = $query->getSliceDefinition();
        $sliceDefinition->setLimit(1);
        $list = $query->getEntities();
        if ([] === $list) {
            return null;
        }

        return array_pop($list);
    }

    public function getFirstClaimedSegmentableStatement(string $procedureId, User $user): ?Statement
    {
        $query = $this->createFluentQuery();
        $query->getConditionDefinition()
            ->inProcedureWithId($procedureId)
            ->assignedToUser($user)
            ->isNonOriginal()
            ->hasNoSegments($procedureId)
            ->notClusterRelated();

        $sliceDefinition = $query->getSliceDefinition();
        $sliceDefinition->setLimit(1);
        $list = $query->getEntities();
        if ([] === $list) {
            return null;
        }

        return array_pop($list);
    }

    public function getSegmentableStatementsCount(string $procedureId, User $user): int
    {
        $query = $this->createFluentQuery();

        $query->getConditionDefinition()
            ->inProcedureWithId($procedureId)
            ->isNonOriginal()
            ->hasNoSegments($procedureId)
            ->notClusterRelated();
        $query->getConditionDefinition()->anyConditionApplies()
            ->assignedToUser($user)
            ->unassigned();

        return $query->getCount();
    }

    /**
     * @return array<int, Statement|Segment>
     */
    public function getEntities(array $conditions, array $sortMethods, int $offset = 0, ?int $limit = null): array
    {
        return parent::getEntities($conditions, $sortMethods, $offset, $limit);
    }

    /**
     * @return Pagerfanta<Statement|Segment>
     */
    public function getEntitiesForPage(array $conditions, array $sortMethods, PagePagination $pagination): Pagerfanta
    {
        return parent::getEntitiesForPage($conditions, $sortMethods, $pagination);
    }

    /**
     * @param non-empty-string $procedureId
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getExternIdsInUse(string $procedureId): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('statement.externId')
            ->from(Statement::class, 'statement')
            ->where('statement.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        $externIds = array_column($query->getScalarResult(), 'externId');

        return array_combine($externIds, $externIds);
    }

    /**
     * @param non-empty-string $procedureId
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getInternIdsInUse(string $procedureId): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('statement.internId')
            ->from(Statement::class, 'statement')
            ->where('statement.procedure = :procedureId')
            ->andWhere('statement.internId IS NOT NULL')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        $internIds = array_column($query->getScalarResult(), 'internId');

        return array_combine($internIds, $internIds);
    }

    /**
     * Some Statements does not have any StatementMeta. Whysoever.
     */
    protected function ensureHasMeta(Statement $statement): Statement
    {
        $statement->getMeta()->setStatement($statement);

        return $statement;
    }

    /**
     * Delete Entity.
     *
     * @param string $statementId Id of an statement
     *
     * @throws Exception
     */
    public function delete($statementId): bool
    {
        return $this->deleteObject($this->get($statementId));
    }

    /**
     * @param Statement $statement
     */
    public function deleteObject($statement): bool
    {
        try {
            $em = $this->getEntityManager();

            // T8488 T8847: also delete placeholder if exists
            // manual detach placeholder from moved Statement, to avoid constraint violation on delete:
            if ($statement->wasMoved()) {
                $placeholder = $statement->getPlaceholderStatement();
                $placeholder->setMovedStatement(null);
                $statement->setPlaceholderStatement(null);
                $em->persist($placeholder);
                $em->persist($statement);
                $em->flush();

                $em->remove($placeholder);
            }

            $em->remove($statement);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Statement failed ', [$e]);

            return false;
        }
    }

    /**
     * Adds a Version of the recommendation.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function addRecommendationVersion($data): StatementVersionField
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('stId', $data)) {
                throw new \InvalidArgumentException('Trying to add a Recommendationversion without StatementKey stId');
            }

            $statementVersionField = new StatementVersionField();
            $statementVersionField->setStatement(
                $em->getReference(Statement::class, $data['stId'])
            );
            $statementVersionField->setUserName($data['userName']);
            $statementVersionField->setName($data['name']);
            $statementVersionField->setType($data['type']);
            $statementVersionField->setValue($data['value']);

            $em->persist($statementVersionField);
            $em->flush();

            return $statementVersionField;
        } catch (Exception $e) {
            $this->logger->warning(
                'Create StatementVersionField failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return StatementLike
     *
     * @throws Exception
     */
    public function addLike(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('statement', $data)) {
                throw new \InvalidArgumentException('Trying to add a like without statementreference');
            }

            $statementLike = new StatementLike();
            $statementLike->setStatement($data['statement']);
            if (array_key_exists('user', $data)) {
                $statementLike->setUser($data['user']);
            }

            $em->persist($statementLike);
            $em->flush();

            return $statementLike;
        } catch (Exception $e) {
            $this->logger->warning('Create StatementLike failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param Statement $statement
     *
     * @return Statement
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws Exception
     */
    public function generateObjectValues($statement, array $data)
    {
        if (null === $statement) {
            return null;
        }

        $em = $this->getEntityManager();

        // Bei einem Bürger nimm die Standarddaten.
        if (array_key_exists('civic', $data) && true === $data['civic']) {
            $data['publicStatement'] = Statement::EXTERNAL;
            $data['uId'] = User::ANONYMOUS_USER_ID;
            $data['submitUId'] = User::ANONYMOUS_USER_ID;
            $data['oId'] = User::ANONYMOUS_USER_ORGA_ID;
            $data['orga_name'] = User::ANONYMOUS_USER_ORGA_NAME;
            $data['orga_department_name'] = User::ANONYMOUS_USER_DEPARTMENT_NAME;
        }

        $statement = $this->ensureHasMeta($statement);

        if (array_key_exists('author_feedback', $data)) {
            $statement->getMeta()->setAuthorFeedback($data['author_feedback']);
        }
        if (array_key_exists('author_name', $data) && 0 < strlen((string) $data['author_name'])) {
            $statement->getMeta()->setAuthorName($data['author_name']);
        }

        if (array_key_exists('isManualStatement', $data) && true === $data['isManualStatement']) {
            $statement->setManual(true);
        }

        if (array_key_exists('case_worker', $data) && 0 < strlen((string) $data['case_worker'])) {
            $statement->getMeta()->setCaseWorkerName($data['case_worker']);
        }

        if (array_key_exists('deleted', $data)) {
            $statement->setDeleted($data['deleted']);
        }
        if (array_key_exists('document', $data)) {
            $statement->setDocument($data['document']);
        }
        if (!array_key_exists('document', $data)
            && array_key_exists('documentId', $data)
            && 36 === strlen((string) $data['documentId'])
        ) {
            $statement->setDocument($em->getReference(SingleDocumentVersion::class, $data['documentId']));
        }
        if (!array_key_exists('document', $data) && array_key_exists('documentId', $data) && '' === $data['documentId']) {
            $statement->setDocument(null);
        }
        if (!array_key_exists('document', $data) && array_key_exists('documentId', $data) && '' === $data['documentId']) {
            $statement->setDocument(null);
        }
        if (array_key_exists('element', $data)) {
            $statement->setElement($data['element']);
        }
        if (array_key_exists('elementId', $data) && 36 === strlen((string) $data['elementId'])) {
            $statement->setElement($em->getReference(Elements::class, $data['elementId']));
        }
        if (array_key_exists('elementId', $data) && '' === $data['elementId']) {
            $statement->setElement(null);
        }
        if (array_key_exists('externId', $data)) {
            $statement->setExternId($data['externId']);
        }
        if (array_key_exists('internId', $data) && '' !== $data['internId']) {
            $statement->setInternId($data['internId']);
        }
        if (array_key_exists('feedback', $data)) {
            $statement->setFeedback($data['feedback']);
        }
        if (array_key_exists('fileupload', $data)) {
            $statement->setFile($data['fileupload']);
        }
        if (array_key_exists('file', $data) && is_string($data['file'])) {
            $statement->setFile($data['file']);
        }
        if (array_key_exists('files_'.StatementAttachment::SOURCE_STATEMENT, $data)
            && $data['files_'.StatementAttachment::SOURCE_STATEMENT] instanceof StatementAttachment) {
            $statement->addAttachment($data['files_'.StatementAttachment::SOURCE_STATEMENT]);
        }
        if (array_key_exists('mapFile', $data)) {
            $statement->setMapFile($data['mapFile']);
        }
        if (array_key_exists('memo', $data)) {
            $statement->setMemo($data['memo']);
        }
        if (array_key_exists('phone', $data)) {
            if (!array_key_exists('miscData', $data)) {
                $data['miscData'] = [];
            }
            $data['miscData'][StatementMeta::USER_PHONE] = $data['phone'];
        }
        if (array_key_exists(StatementMeta::USER_ORGANISATION, $data)) {
            if (!array_key_exists('miscData', $data)) {
                $data['miscData'] = [];
            }
            $data['miscData'][StatementMeta::USER_ORGANISATION] = $data[StatementMeta::USER_ORGANISATION];
        }
        if (array_key_exists('miscData', $data)) {
            $miscData = $statement->getMeta()->getMiscData();
            foreach ($data['miscData'] as $miscDataKey => $miscDataValue) {
                $miscData[$miscDataKey] = $miscDataValue;
            }
            $statement->getMeta()->setMiscData($miscData);
        }
        if (array_key_exists('oId', $data) && 36 === strlen((string) $data['oId'])) {
            $statement->setOrganisation($em->getReference(Orga::class, $data['oId']));
        }
        if (array_key_exists('orga_city', $data) && 0 < strlen((string) $data['orga_city'])) {
            $statement->getMeta()->setOrgaCity($data['orga_city']);
        }
        if (array_key_exists('orga_department_name', $data) && 0 < strlen((string) $data['orga_department_name'])) {
            $statement->getMeta()->setOrgaDepartmentName($data['orga_department_name']);
        }
        if (array_key_exists('orga_email', $data) && 0 < strlen((string) $data['orga_email'])) {
            $statement->getMeta()->setOrgaEmail($data['orga_email']);
        }
        if (array_key_exists('orga_name', $data) && 0 < strlen((string) $data['orga_name'])) {
            $statement->getMeta()->setOrgaName($data['orga_name']);
        }
        if (array_key_exists('orga_postalcode', $data) && 0 < strlen((string) $data['orga_postalcode'])) {
            $statement->getMeta()->setOrgaPostalCode($data['orga_postalcode']);
        }
        if (array_key_exists('orga_street', $data) && 0 < strlen((string) $data['orga_street'])) {
            $statement->getMeta()->setOrgaStreet($data['orga_street']);
        }

        if (array_key_exists('paragraph', $data)) {
            $statement->setParagraph($data['paragraph']);
        }

        // nutze die paragraphId nur, wenn nicht schon das Objekt direkt gesetzt wurde
        if (!array_key_exists('paragraph', $data)
            && array_key_exists('paragraphId', $data)
            && 36 === strlen((string) $data['paragraphId'])) {
            $statement->setParagraph($em->getReference(ParagraphVersion::class, $data['paragraphId']));
        }

        if (!array_key_exists('paragraph', $data)
            && array_key_exists('paragraphId', $data)
            && '' === $data['paragraphId']) {
            $statement->setParagraph(null);
        }

        if (array_key_exists('phase', $data)) {
            $statement->setPhase($data['phase']);
        }
        if (array_key_exists('pId', $data) && 36 === strlen((string) $data['pId'])) {
            $statement->setProcedure($em->getReference(Procedure::class, $data['pId']));
        }
        if (array_key_exists('polygon', $data)) {
            $statement->setPolygon($data['polygon']);
        }
        if (array_key_exists('priority', $data)) {
            $statement->setPriority($data['priority']);
        }
        if (array_key_exists('publicStatement', $data)) {
            $statement->setPublicStatement($data['publicStatement']);
        }
        if (array_key_exists('publicUseName', $data)) {
            $statement->setPublicUseName($data['publicUseName']);
        }
        if (array_key_exists('publicVerified', $data)) {
            $statement->setPublicVerified($data['publicVerified']);
        }
        if (array_key_exists('recommendation', $data)) {
            $statement->setRecommendation($data['recommendation']);
        }
        if (array_key_exists('representationCheck', $data) && ('on' === $data['representationCheck'] || '1' === $data['representationCheck'])) {
            $statement->setRepresentationCheck(true);
        } else {
            $statement->setRepresentationCheck(false);
        }
        if (array_key_exists('status', $data)) {
            $statement->setStatus($data['status']);
        }
        if (array_key_exists('submit_name', $data) && 0 < strlen((string) $data['submit_name'])) {
            $statement->getMeta()->setSubmitName($data['submit_name']);
        }
        if (array_key_exists('submitUId', $data) && 36 === strlen((string) $data['submitUId'])) {
            $statement->getMeta()->setSubmitUId($data['submitUId']);
        }

        if (array_key_exists('submittedDate', $data)) {
            $date = Carbon::createFromTimestamp(strtotime((string) $data['submittedDate']))->toDateTime();
            if ($date instanceof DateTime) {
                $statement->setSubmit($date);
            }
        }
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $tags = [];
            foreach ($data['tags'] as $tagId) {
                if ('' === $tagId) {
                    continue;
                }
                if ($tagId instanceof Tag) {
                    $tags[] = $tagId;
                } else {
                    $tags[] = $em->getReference(Tag::class, $tagId);
                }
            }
            $statement->setTags($tags);
        }
        if (array_key_exists('counties', $data) && is_array($data['counties'])) {
            $counties = [];
            foreach ($data['counties'] as $countyId) {
                if ('' === $countyId) {
                    continue;
                }
                if ($countyId instanceof County) {
                    $counties[] = $countyId;
                } else {
                    $counties[] = $em->getReference(County::class, $countyId);
                }
            }
            $statement->setCounties($counties);
        }
        if (array_key_exists('municipalities', $data) && is_array($data['municipalities'])) {
            $municipalities = [];
            foreach ($data['municipalities'] as $municipalityId) {
                if ('' === $municipalityId) {
                    continue;
                }
                if ($municipalityId instanceof Municipality) {
                    $municipalities[] = $municipalityId;
                } else {
                    $municipalities[] = $em->getReference(Municipality::class, $municipalityId);
                }
            }
            $statement->setMunicipalities($municipalities);
        }
        if (array_key_exists('priorityAreas', $data) && is_array($data['priorityAreas'])) {
            $priorityAreas = [];
            foreach ($data['priorityAreas'] as $priorityAreaId) {
                if ('' === $priorityAreaId) {
                    continue;
                }
                if ($priorityAreaId instanceof PriorityArea) {
                    $priorityAreas[] = $priorityAreaId;
                } else {
                    $priorityAreas[] = $em->getReference(PriorityArea::class, $priorityAreaId);
                }
            }
            $statement->setPriorityAreas($priorityAreas);
        }

        if (array_key_exists('text', $data)) {
            $statement->setText($this->sanitize($data['text'], [$this->obscureTag]));
        }
        if (array_key_exists('title', $data)) {
            $statement->setTitle($data['title']);
        }
        if (array_key_exists('uId', $data) && 36 === strlen((string) $data['uId'])) {
            $statement->setUser($em->getReference(User::class, $data['uId']));
        }
        if (array_key_exists('voteStk', $data)) {
            $statement->setVoteStk($data['voteStk']);
        }
        if (array_key_exists('votePla', $data)) {
            $statement->setVotePla($data['votePla']);
        }

        if (array_key_exists('numberOfAnonymVotes', $data)) {
            $number = is_string($data['numberOfAnonymVotes']) ? (int) $data['numberOfAnonymVotes'] : $data['numberOfAnonymVotes'];
            $statement->setNumberOfAnonymVotes($number);
        }

        if (array_key_exists('sentAssessment', $data)) {
            $statement->setSentAssessment($data['sentAssessment']);
        }
        if (array_key_exists('authoredDate', $data) && 0 < strlen((string) $data['authoredDate'])) {
            $dateTime = new DateTime();
            $date = $dateTime->createFromFormat('d.m.Y', $data['authoredDate']);
            if ($date instanceof DateTime) {
                $statement->getMeta()->setAuthoredDate($date);
            }
        }

        if (array_key_exists('submitOrgaId', $data) && 36 === strlen((string) $data['submitOrgaId'])) {
            $statement->getMeta()->setSubmitOrgaId($data['submitOrgaId']);
        }

        if (array_key_exists('numberOfAnonymVotes', $data)) {
            $statement->setNumberOfAnonymVotes($data['numberOfAnonymVotes']);
        }

        // on create new statement, the statement hasn't filled all necessary fields here
        // on creating votes on the fly, the related statement isn't existing
        // on flush this, an error occurs
        if (array_key_exists('votes', $data) && !$statement->isOriginal()) {
            $this->handleVotesOnStatement($statement, $data['votes']);
        }

        if (array_key_exists('name', $data)) {
            $statement->setName($data['name']);
        }

        if (array_key_exists('submitterEmailAddress', $data)) {
            $statement->getMeta()->setOrgaEmail($data['submitterEmailAddress']);
        }

        if (array_key_exists('submitterType', $data)) {
            $statement->getMeta()->setOrgaName($data['submitterType']);
        }

        if (array_key_exists('submitType', $data)) {
            $statement->setSubmitType($data['submitType']);
        }
        if (array_key_exists('submit_type', $data)) {
            $statement->setSubmitType($data['submit_type']);
        }

        if (array_key_exists('departmentName', $data)) {
            $statement->getMeta()->setOrgaDepartmentName($data['departmentName']);
        }

        if (array_key_exists('phase', $data)) {
            $statement->setPhase($data['phase']);
        }

        if (array_key_exists('replied', $data)) {
            $statement->setReplied($data['replied']);
        }

        // T14715: never attach an original STN to an headStatement
        if (!$statement->isOriginal() && array_key_exists('headStatementId', $data) && 36 === strlen((string) $data['headStatementId'])) {
            /** @var Statement $relatedHeadStatement */
            $relatedHeadStatement = $em->getReference(Statement::class, $data['headStatementId']);
            $relatedHeadStatement->addStatement($statement);
        }

        if (array_key_exists('houseNumber', $data)) {
            $statement->getMeta()->setHouseNumber($data['houseNumber']);
        }

        // Throw an event to allow addons to handle specific keys in $data
        $this->eventDispatcher->dispatch(new AdditionalStatementDataEvent($statement, $data));

        return $statement;
    }

    /**
     * Will execute the required actions to "set" the given StatementVotes to the related Statement.
     * In detail:
     * StatementVotes, which are not contained in $votesToSet, will be deleted.
     * StatementVotes, which are contained in $votesToSet, will be created if not existing, or updated.
     *
     * @param Statement        $statement  - related Statement
     * @param array|Collection $votesToSet - StatementVotes to set on the given Statement
     *
     * @return Collection<int, StatementVote>
     *
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function handleVotesOnStatement(Statement $statement, $votesToSet)
    {
        $newVoteObjects = collect([]);
        $votesToSet = collect($votesToSet);
        $currentVotes = collect($statement->getVotes());

        /** @var StatementVoteRepository $voteRepository */
        $voteRepository = $this->getEntityManager()
                ->getRepository(StatementVote::class);

        $votesToDelete = $currentVotes->filter(fn ($vote) => !$votesToSet->keyBy('id')->has($vote->getId()));

        $votesToUpdate = $votesToSet->filter(fn ($vote) => array_key_exists('id', $vote) && '' != $vote['id']);

        $votesToCreate = $votesToSet->filter(fn ($vote) => !array_key_exists('id', $vote) || '' == $vote['id']);

        /** @var StatementVote $voteToDelete */
        foreach ($votesToDelete as $voteToDelete) {
            // only manual votes are allowed to be deleted
            if ($voteToDelete->isManual()) {
                // delete entry from database
                $voteRepository->delete($voteToDelete);
            } else {
                $newVoteObjects->push($voteToDelete);
            }
        }

        /** @var array $voteToUpdate */
        foreach ($votesToUpdate as $voteToUpdate) {
            $voteToUpdate['statement'] = $statement;
            $vote = $voteRepository->get($voteToUpdate['id']);
            // only manual votes are allowed to be changed
            $allowedToProcess = $vote->isManual();
            if ($allowedToProcess) {
                // always update, even if nothing changed. Might be improved some day
                $vote = $voteRepository->update($voteToUpdate['id'], $voteToUpdate);
            }
            // as votes are only updated they should be kept
            $newVoteObjects->push($vote);
        }

        /** @var array $voteToCreate */
        foreach ($votesToCreate as $voteToCreate) {
            $voteToCreate['manual'] = true;
            $voteToCreate['statement'] = $statement;

            // do not flush here because associated STN may not be a valid object here
            $createdVote = $voteRepository->generateObjectValues(new StatementVote(), $voteToCreate);
            // vote will be cascading persisted via statement
            $newVoteObjects->push($createdVote);
        }

        $statement->setVotes($newVoteObjects->toArray());

        return collect($newVoteObjects);
    }

    /**
     * Returns the internId of the newest/youngest statement,
     * which internId is not NULL and is related to the given procedure.
     *
     * @param string $procedureId - identifies the procedure, whose related statements will be included
     *
     * @return string|null null if be none found, otherwise the found ID as string
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getNewestInternId($procedureId)
    {
        $manager = $this->getEntityManager();
        $query = $manager->createQueryBuilder()
            ->select('statement.created, statement.internId')
            ->from(Statement::class, 'statement')
            ->where('statement.internId IS NOT NULL')
            ->andWhere('statement.procedure = :procedureId')->setParameter('procedureId', $procedureId)
            ->orderBy('statement.created', 'DESC') // by date
            ->addOrderBy('statement.internId * 1', 'DESC') // then, by int
            ->addOrderBy('statement.internId', 'ASC') // if strings remain (M123), start with last (M999)
            ->setMaxResults(1)
            ->getQuery();
        $statements = $query->getResult();
        if (0 === (is_countable($statements) ? count($statements) : 0)) {
            return null;
        }

        return $statements[0]['internId'];
    }

    /**
     * Returns all internIds of the statements of the given procedure as array.
     *
     * @param string $procedureId - identifies the procedure
     *
     * @return array
     */
    public function getInternIdsOfStatementsOfProcedure($procedureId)
    {
        $manager = $this->getEntityManager();
        $query = $manager->createQueryBuilder()
            ->select('statement.internId')
            ->from(Statement::class, 'statement')
            ->where('statement.internId IS NOT NULL')
            ->andWhere('statement.procedure = :procedureId')->setParameter('procedureId', $procedureId)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Copy properties from SourceObject to TargetObject.
     *
     * @param Statement      $copyToEntity
     * @param DraftStatement $copyFromEntity
     * @param array          $excludeProperties
     *
     * @return Statement
     *
     * @throws ReflectionException
     */
    protected function generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties = [])
    {
        return parent::generateObjectValuesFromObject($copyToEntity, $copyFromEntity);
    }

    /**
     * @return array<int, Statement>
     */
    public function getStatementsOfProcedureAndOrganisation(string $procedureId, string $organisationId): array
    {
        // get all original statements from statements
        return $this->getEntityManager()->createQueryBuilder()
            ->select('original.id, original.created, original.externId')
            ->from(Statement::class, 'statement')
            ->leftJoin('statement.original', 'original')
            ->leftJoin('original.meta', 'meta')
            ->andWhere('original.original IS NULL')
            ->andWhere('statement.deleted = false')
            ->andWhere('original.deleted = false')
            ->andWhere('statement.movedStatement IS NULL') // isPlaceholder === false
            ->andWhere('statement.procedure = :procedureId')
            ->andWhere('original.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->andWhere('statement.original IS NOT NULL')
            ->andWhere('meta.submitOrgaId = :orgaId')
            ->setParameter('orgaId', $organisationId)
            ->orderBy('original.created', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * First gets all Statements which are member of a cluster.
     * Then collect each HeadStatementID once.
     * Get the object for each HeadStatementID.
     *
     * @return Statement[]
     */
    public function getHeadStatements()
    {
        // get all member of cluster
        $memberStatements = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.headStatement IS NOT NULL')
            ->getQuery()->getResult();

        // collect each headstatementId once
        $headStatementIds = collect([]);
        /** @var Statement $memberStatement */
        foreach ($memberStatements as $memberStatement) {
            if (!$headStatementIds->contains($memberStatement->getHeadStatementId())) {
                $headStatementIds->push($memberStatement->getHeadStatementId());
            }
        }

        // get object of collected headStatementIds
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.original IS NOT NULL')
            ->andWhere('statement.id IN (:ids)')
            ->setParameter('ids', $headStatementIds->toArray(), Connection::PARAM_STR_ARRAY)
            ->getQuery()->getResult();

        return $result;
    }

    /**
     * Returns Statements of Headstatements of given statementIds.
     * If there not headstatement in the given statementIds, this will return no Statement.
     * This method can include duplicats!
     *
     * @return Statement[]
     */
    public function getAllStatementsOfHeadStatements(array $statementIds)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.headStatement IN (:ids)')
            ->setParameter('ids', $statementIds, Connection::PARAM_STR_ARRAY)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Load the Statements of the given Ids.
     *
     * @param string[] $statementIds
     *
     * @return Statement[]
     */
    public function getStatements($statementIds)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.id IN (:ids)')
            ->setParameter('ids', $statementIds, Connection::PARAM_STR_ARRAY)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Returns the next valid externalId for a procedure.
     * In the past those values where random, so we let sql sort
     * the values to get the highest and increment it by 1.
     *
     * @param string $procedureId
     *
     * @return int externalId
     */
    public function getNextValidManualExternalIdForProcedure($procedureId)
    {
        return 'M'.$this->getNextValidExternalIdForProcedure($procedureId);
    }

    /**
     * Returns the increment of the biggest externId in use of Statements AND Draftstatements within a specific procedure.
     * Take also the prefixed externIds into account.
     */
    public function getNextValidExternalIdForProcedure(string $procedureId): int
    {
        $query1 = $this->getEntityManager()->createQueryBuilder()
            ->select('statement.externId')
            ->from(Statement::class, 'statement')
            ->where('statement.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->groupBy('statement.externId')
            ->getQuery();
        $statementArrays = $query1->getResult();

        $query2 = $this->getEntityManager()->createQueryBuilder()
            ->select('draftStatement.number')
            ->from(DraftStatement::class, 'draftStatement')
            ->where('draftStatement.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        $draftStatementArrays = $query2->getResult();

        $externIds = collect(['0']);

        foreach ($statementArrays as $entityArray) {
            $externId = $entityArray['externId'];
            if (false === is_numeric($externId)) {
                preg_match('/([0-9]).*/', (string) $externId, $matches);
                $externId = array_key_exists(0, $matches) ? $matches[0] : 0;
            }
            // with is_numeric we exclude external ids from segments which have a
            // dash (so would break when being treated as a number)
            if (is_numeric($externId)) {
                $externIds->push($externId);
            }
        }

        foreach ($draftStatementArrays as $entityArray) {
            if (is_numeric($entityArray['number'])) {
                $externIds->push(strval($entityArray['number']));
            }
        }

        $highestId = $externIds->max();

        return $highestId + 1;
    }

    public function getPartOfStatementAsArray($statementId)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->addSelect('statement.manual')
            ->addSelect('statement.meta')
            ->addSelect('statement.publicStatement')
            ->addSelect('statement.user')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.id = :statementId')
            ->setParameter('statementId', $statementId)
            ->getQuery();

        return $query->getSingleResult()['manual'];
    }

    /**
     * @param string $statementId
     *
     * @return array
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @deprecated use {@link Statement::isManual()} instead
     */
    public function isManualStatement($statementId)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('statement.manual')
            ->from(Statement::class, 'statement')
            ->andWhere('statement.id = :statementId')
            ->setParameter('statementId', $statementId)
            ->getQuery();

        return $query->getSingleResult()['manual'];
    }

    /**
     * @param string $procedureId string
     *
     * @return Statement[]
     */
    public function getCitizenStatementsByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->where('statement.original IS NOT NULL')
            ->andWhere('statement.deleted = FALSE')
            ->andWhere('statement.procedure = :procedureId')
            ->andWhere('statement.organisation = :organisationId')
            ->orderBy('statement.externId', 'ASC')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('organisationId', User::ANONYMOUS_USER_ORGA_ID)
            ->distinct(true)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param string $procedureId string
     *
     * @return Statement[]
     */
    public function getInstitutionStatementsByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->where('statement.original IS NOT NULL')
            ->andWhere('statement.deleted = FALSE')
            ->andWhere('statement.procedure = :procedureId')
            ->andWhere('statement.organisation != :organisationId')
            ->orderBy('statement.created', 'DESC')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('organisationId', User::ANONYMOUS_USER_ORGA_ID)
            // ->distinct(true)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Takes a list of strings and returns those strings that match a placeholder statement in the database.
     *
     * @param string[] $statementIds
     *
     * @return string[]
     */
    public function getPlaceholderStatementIds(array $statementIds): array
    {
        if ([] === $statementIds) {
            return [];
        }
        $statements = $this->getStatements($statementIds);

        return collect($statements)->filter(
            static fn (Statement $statement) => $statement->isPlaceholder()
        )->map(
            static fn (Statement $statement) => $statement->getId()
        )->toArray();
    }

    /**
     * Determines for a given statement how many consents are needed and by whom.
     *
     * The returned IDs do not indicate that the consent was received but that the consent of the user would relevant
     * for the given statement.
     *
     * @return array The relevant IDs. May contain null values when a consent of a person would relevant but no
     *               User entity could be found for that person in the database. The first value will be the
     *               submitter (key 'submitter'), the second one (if it is different from the submitter) the author
     *               (key 'author').
     *
     * @throws InvalidDataException
     */
    public function getInitialConsenteeIds(Statement $statement): array
    {
        // In case of manual statements the author and submitter is the one sending the
        // letter (and thus the same person). It is not the Fachplaner creating the manual statement.
        // Hence we do not know any user ID but we know the author and submitter were the same and
        // return an array with only one element so only a single GdprConsent will be created.
        if ($statement->isManual()) {
            return ['submitter' => null];
        }
        // In case of statements submitted by unregistered
        // citizens we need a single GdprConsent instance without a connection to any user
        // as the author and submitter are the same person which can not be connected
        // to any User entity here.
        if ($statement->hasBeenSubmittedAndAuthoredByUnregisteredCitizen()) {
            return ['submitter' => null];
        }
        // In case of statements submitted by registered citizens the author and
        // submitter are the same. Hence we only need one GdprConsent instance and
        // hence return (only) the submitter ID.
        if ($statement->hasBeenSubmittedAndAuthoredByRegisteredCitizen()) {
            return ['submitter' => $statement->getSubmitterId()];
        }
        // In case of statements submitted by Institution-Koordinators we may need two consents,
        // one for the author (Institution-Sachbearbeiter) and one for the submitter (Institution-Koordinator). However
        // the Institution-Koordinator (without a double role) may be the author as well as the submitter. And in
        // case of a double role (Institution-Koordinator is a Institution-Sachbearbeiter at the same time) the author
        // and submitter will be the same person as well.
        if ($statement->hasBeenSubmittedAndAuthoredByInvitableInstitutionKoordinator()) {
            return ['submitter' => $statement->getSubmitterId()];
        }
        /*
         * Statement was authored by a Institution-Sachbearbeiter and submitted by a different
         * person in the role of a Institution-Koordinator.
         */
        if ($statement->hasBeenAuthoredByInstitutionSachbearbeiterAndSubmittedByInstitutionKoordinator()) {
            return ['submitter' => $statement->getSubmitterId(), 'author' => $statement->getAuthorId()];
        }

        throw new InvalidDataException('Unknown submission type');
    }

    /**
     * Get all statements that were either submitted or authored by the user with the given ID.
     *
     * To check for submitted statements the value of Statement.meta.submitUid is compared with
     * the given user ID. To check for authored statements the value of Statements.user is
     * compared with the given user ID.
     *
     * @param string $userId must be a valid UUID or no results are found (empty array)
     *
     * @return Statement[] Only original statements will be returned. The result is sorted
     *                     descending using the value of {@see Statement::created}. If the $userId
     *                     is invalid an empty array is returned.
     */
    public function getSubmittedOrAuthoredStatements(string $userId): array
    {
        $manager = $this->getEntityManager();
        $queryBuilder = $manager->createQueryBuilder();
        $query = $queryBuilder
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->leftJoin('statement.meta', 'meta')
            ->andWhere('statement.original IS NULL')
            ->andWhere($queryBuilder->expr()->orX(
                // submitted statements
                $queryBuilder->expr()->eq('meta.submitUId', ':userId'),
                // authored statements
                $queryBuilder->expr()->eq('statement.user', ':userId')
            ))
            ->setParameter('userId', $userId)
            ->orderBy('statement.created', 'DESC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param array $procedureIds the procedure IDs to get the number of original statements for
     *
     * @return array<string, int> unordered array with procedure IDs as keys and the count of corresponding
     *                            original statements which are no placeholders and not deleted as values
     */
    public function getOriginalStatementsCounts(array $procedureIds): array
    {
        if ([] === $procedureIds) {
            return [];
        }

        $queryBuilder = $this->createStatementsCountsQueryBuilder($procedureIds);
        $queryBuilder->andWhere($queryBuilder->expr()->isNull('statement.original'));
        $queryResult = $queryBuilder->getQuery()->getArrayResult();

        return array_map(static fn (string $count): int => (int) $count, array_column($queryResult, 'count', 'procedureId'));
    }

    /**
     * @param array $procedureIds the procedure IDs to get the number of statements for
     *
     * @return array<string, int> unordered array with procedure IDs as keys and the count of corresponding
     *                            statements which are no placeholders and not deleted as values
     */
    public function getStatementsCounts(array $procedureIds): array
    {
        if ([] === $procedureIds) {
            return [];
        }

        $queryBuilder = $this->createStatementsCountsQueryBuilder($procedureIds);
        $queryBuilder->andWhere($queryBuilder->expr()->isNotNull('statement.original'));
        $queryResult = $queryBuilder->getQuery()->getArrayResult();

        return array_map(static fn (string $count): int => (int) $count, array_column($queryResult, 'count', 'procedureId'));
    }

    /**
     * @param array $procedureIds the procedure IDs to get the number of statements for
     */
    private function createStatementsCountsQueryBuilder(array $procedureIds): QueryBuilder
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder
            ->select('IDENTITY(statement.procedure) as procedureId')
            ->addSelect('count(statement.id) as count')
            ->from(Statement::class, 'statement')
            ->groupBy('statement.procedure')
            ->andWhere('statement.deleted = :deleted')
            ->setParameter('deleted', false)
            ->andWhere('statement.clusterStatement = :clusterStatement')
            ->setParameter('clusterStatement', false)
            ->andWhere($queryBuilder->expr()->isNull('statement.movedStatement'))
            ->andWhere($queryBuilder->expr()->in('statement.procedure', $procedureIds));

        return $queryBuilder;
    }

    /**
     * Deletes all Statements of given procedure.
     *
     * @param string $procedureId identifies the Procedure
     *
     * @return int Amount of deleted Statements
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function deleteByProcedure(string $procedureId): int
    {
        $deletedEntities = 0;
        $this->copyOriginalStatementToProceduresOfChildrenInOtherProcedures($procedureId);
        $this->resolveAllStatementRelationsOfProcedure($procedureId);

        /** @var Statement[] $statementsToDelete */
        $statementsToDelete = $this->findBy(['procedure' => $procedureId]);

        foreach ($statementsToDelete as $statementToDelete) {
            try {
                $this->hardDelete($statementToDelete, true);
                ++$deletedEntities;
            } catch (Exception $e) {
                $this->getLogger()->error('deleteByProcedure failed ', [$e, $statementToDelete->getId()]);
            }
        }
        $this->getEntityManager()->flush();

        return $deletedEntities;
    }

    /**
     * This method will create an invalid state of statements in procedure
     * only use on delete procedure after usage of this method.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function resolveAllStatementRelationsOfProcedure(string $procedureId)
    {
        $statements = $this->findBy(['procedure' => $procedureId]);

        /** @var Statement $statement */
        foreach ($statements as $statement) {
            $statement->setClusterStatement(false);
            $statement->setHeadStatement(null);
            $statement->setOriginal(null);
            $statement->setParent(null);
            $this->getEntityManager()->persist($statement);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param bool $allowDeletionOfOriginal
     *
     * @return bool|null
     *
     * @throws Exception
     */
    protected function hardDelete(Statement $statementToDelete, $allowDeletionOfOriginal = false)
    {
        if (!$allowDeletionOfOriginal && $statementToDelete->isOriginal()) {
            $this->getLogger()->warning('Trying to delete original Statement.');
            throw new BadRequestException('Trying to delete original Statement.');
        }

        if ($statementToDelete->isOriginal()) {
            // isOriginal findet alle, STNs die children haben!
            /** @var Statement $child */
            foreach ($statementToDelete->getChildren() as $child) {
                if ($child->getProcedureId() !== $statementToDelete->getProcedureId()) {
                    $this->getLogger()->warning('Child '.$child->getId().'of Statement '.$statementToDelete->getId().' is in foreign procedure. This has to be resolved before deleting is allowed.');
                    throw new BadRequestException('Child '.$child->getId().'of Statement '.$statementToDelete->getId().' is in foreign procedure. This has to be resolved before deleting is allowed.');
                }
                $this->hardDelete($child, $allowDeletionOfOriginal);
            }
        }

        // todo: what should happens in case of headStatement will be deleted?
        if ($statementToDelete->isClusterStatement()) {
            // resolve and delete $statementToDelete
            $this->resolveCluster($statementToDelete);

            return null;
        }

        // detach from cluster:
        if ($statementToDelete->isInCluster()) {
            $headStatement = $statementToDelete->getHeadStatement();
            $statementToDelete->detachFromCluster();
            $this->getEntityManager()->persist($headStatement);
        }

        // detach form movedStatement:
        $movedStatement = $statementToDelete->getMovedStatement();
        if ($movedStatement instanceof Statement) {
            $movedStatement->setPlaceholderStatement(null);
            $this->getEntityManager()->persist($movedStatement);
            $statementToDelete->setMovedStatement(null);
            $this->getEntityManager()->persist($statementToDelete);
        }

        // detach form placeholder:
        $placeholderStatement = $statementToDelete->getPlaceholderStatement();
        if ($placeholderStatement instanceof Statement) {
            $placeholderStatement->setMovedStatement(null);
            $this->getEntityManager()->persist($placeholderStatement);
            $statementToDelete->setPlaceholderStatement(null);
            $this->getEntityManager()->persist($statementToDelete);
        }

        // detach original and children: (this cant be handelet by docrine cascading or DB cascading because
        // of chlidren is used as inversed site by orgiginal AND parent! (copies)
        /** @var Statement $child */
        foreach ($statementToDelete->getChildren() as $child) {
            $child->setParent(null);
            $child->setOriginal(null);
            $this->getEntityManager()->persist($child);
        }
        $statementToDelete->setChildren(null);
        $statementToDelete->setOriginal(null);
        $this->getEntityManager()->persist($statementToDelete);

        $this->getEntityManager()->remove($statementToDelete);

        return null;
    }

    /**
     * Detaches all Statements of the custer and deletes the headStatement.
     *
     * If there are Statements in the cluster, which can not be detached from the cluster,
     * the cluster will not be deleted.
     *
     * @throws Exception
     */
    public function resolveCluster(Statement $headStatement)
    {
        $this->getLogger()->debug('11111 resolve cluster statement...');

        if (!$headStatement->isClusterStatement()) {
            $this->getLogger()->warning('Given Statement is not a HeadStatement.');
            throw new BadRequestException('Given Statement is not a HeadStatement.');
        }

        $notDetachedStatements = collect([]);
        $statementsOfCluster = $headStatement->getCluster();

        foreach ($statementsOfCluster as $statement) {
            $this->getLogger()->debug('11111 set headstatement of clusteredSTatemetn to null...');
            $statement->setHeadStatement(null);
            $removedStatement = $this->updateObject($statement);
            if (!$removedStatement instanceof Statement) {
                $notDetachedStatements->push($statement);
                throw new Exception('error.statement.cluster.resolve'.$headStatement->getId());
            }
        }

        if (0 === $notDetachedStatements->count()) {
            $this->getLogger()->debug('11111 not detached statements!: '.$notDetachedStatements->count());
            try {
                $this->getEntityManager()->remove($headStatement);
                $this->getEntityManager()->flush();
            } catch (Exception) {
                $this->getLogger()->debug('11111 exeption on resolve cluster!');
            }
        } else {
            $headStatement->setCluster($notDetachedStatements->toArray());
            $this->updateObject($headStatement);
            throw new Exception('Some statements of Cluster {$headStatement->getId()} are not detached.');
        }
    }

    /**
     * @return Statement[]
     */
    protected function getChildrenInOtherProcedures(string $procedureId): array
    {
        $originalStatementsOfProcedure = $this->findBy(['procedure' => $procedureId, 'original' => null]);

        return $this->getEntityManager()->createQueryBuilder()
            ->select('statement')
            ->from(Statement::class, 'statement')
            ->where('statement.procedure != :procedureId')
            ->andWhere('statement.original IN (:originalStatementsOfProcedure)')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('originalStatementsOfProcedure', $originalStatementsOfProcedure)
            ->getQuery()
            ->execute();
    }

    /**
     * Determines if a given internal id is unique in the scope of a procedure.
     *
     * @param string $internId
     * @param string $procedureId
     */
    public function isInternIdUniqueForProcedure($internId, $procedureId): bool
    {
        $result = [];

        if (null !== $internId) {
            $result = $this->findBy(['procedure' => $procedureId, 'internId' => $internId]);
        }

        return 0 === count($result);
    }

    /**
     * Coping an original statement.
     *
     * @param string|null $internIdToSet
     *
     * @throws InvalidDataException
     * @throws ORMException
     * @throws NonUniqueResultException
     */
    public function copyOriginalStatement(
        Statement $originalToCopy,
        Procedure $targetProcedure,
        ?GdprConsent $gdprConsentToSet = null,
        $internIdToSet = null
    ): Statement {
        if (!$originalToCopy->isOriginal()) {
            throw new InvalidArgumentException('Given Statement is not an OriginalStatement.');
        }

        if (null !== $gdprConsentToSet && null !== $gdprConsentToSet->getStatement()) {
            throw new InvalidArgumentException('Given GdprConsent is already in use.');
        }

        $newOriginalStatement = clone $originalToCopy;

        // create new gdprConsent for copied original statement
        if (null === $gdprConsentToSet && null !== $originalToCopy->getGdprConsent()) {
            $gdprConsentToSet = clone $originalToCopy->getGdprConsent();
            $gdprConsentToSet->setStatement($newOriginalStatement);
        }

        if ('' === $internIdToSet) {
            throw new InvalidArgumentException('Given internID cant be empty string.');
        }

        // no internID given? try to copy of original statement to copy:
        $internIdIsUnique = $this->isInternIdUniqueForProcedure($internIdToSet, $targetProcedure->getId());
        if (!$internIdIsUnique) {
            throw new InvalidArgumentException('Given internID has to be unique in target procedure.');
        }

        $newOriginalStatement->setId(null);
        $newOriginalStatement->setInternId($internIdToSet);
        $newOriginalStatement->setCreated(new DateTime());
        $newOriginalStatement->setDeletedDate(new DateTime());
        $newOriginalStatement->setModified(new DateTime());
        $newOriginalStatement->setSubmit($originalToCopy->getSubmitObject()->add(new DateInterval('PT1S')));
        $newStatementMeta = clone $originalToCopy->getMeta();
        $newOriginalStatement->setMeta($newStatementMeta);
        $newOriginalStatement->setProcedure($targetProcedure);
        $newOriginalStatement->setChildren(null);

        /**
         * @improve: this copy-attachment-logic belongs into the {@see StatementAttachmentService}
         * but we can't access it from here. To solve this move this whole function out of the
         * repository into a service and split the logic into multiple functions in the appropriate
         * services.
         */
        $copiedAttachments = new ArrayCollection();
        /** @var StatementAttachment $attachment */
        foreach ($originalToCopy->getAttachments() as $attachment) {
            $copiedAttachment = $this->copyAttachment($newOriginalStatement, $attachment);
            $copiedAttachments->add($copiedAttachment);
        }
        $newOriginalStatement->setAttachments($copiedAttachments);

        // persist statement here to create an uuid which is needed for copying files
        $this->getEntityManager()->persist($newOriginalStatement);
        $this->copyFileContainers($originalToCopy, $newOriginalStatement);

        $newOriginalStatement->setGdprConsent($gdprConsentToSet);
        $entityManager = $this->getEntityManager();
        /** @var GdprConsentRevokeTokenRepository $gdprConsentRevokeTokenRepository */
        $gdprConsentRevokeTokenRepository = $entityManager->getRepository(GdprConsentRevokeToken::class);
        try {
            $gdprConsentRevokeTokenRepository->maybeConnectStatementToTokenInOtherStatementAndPersist(
                $newOriginalStatement,
                $originalToCopy
            );
        } catch (StatementAlreadyConnectedToGdprConsentRevokeTokenException $e) {
            // This should not be possible but if it happens it should not break things right now
            // and should be possible to be fixed later. Hence we log with a high level (as this
            // needs to be investigated!) and continue the execution.
            $this->getLogger()->error('The new original statement is already connected to a token.', [$e]);
        }

        $newExternId = $this->getNextValidExternalIdForProcedure($targetProcedure->getId());
        if ($originalToCopy->isManual()) {
            $newExternId = 'M'.$newExternId;
        }

        // todo. load clusterprefix from config
        //            $clusterPrefix = $this->getServiceStatement()->getGlobalConfig()->getClusterPrefix();
        if ($originalToCopy->isClusterStatement()) {
            $newExternId = 'G'.$newExternId;
        }

        $newOriginalStatement->setExternId($newExternId);

        if ($originalToCopy->getProcedureId() !== $targetProcedure->getId()) {
            // remove all tags, because procedure specific -> impossible to keep:
            /** @var Tag $tag */
            foreach ($newOriginalStatement->getTags() as $tag) {
                $newOriginalStatement->removeTag($tag);
            }
        }

        return $newOriginalStatement;
    }

    /**
     * This method handles associations between statements and original statements
     * to prevent errors on deleting all statements of a specific procedure.
     *
     * In case of moved Statements, there may be Statements in other procedures, which have an association to
     * original Statements of the procedure, which is determined to delete.
     * This would lead to Database errors, because the DB is modeled to restrict deletion of associated statements
     * of an original statement.
     *
     * To handle this, all statements which have originals in the procedure to delete will be collected.
     * For each "foreign"Procedure which contains a statement which is associated to a original statement
     * of the procedure to delete, will be created a new original (copy of original in the procedure to delete)
     * and associated with the new created copied original statement.
     *
     * Because of an original statement can have multiple children in multiple "foreign" procedures, we need to
     * use a multi dimensional array, to map the necessary information about which "foreign" procedure
     * has which statements.
     * In the second step, it will be created a original statement in each "foreign" procedure and all
     * found children (of the original statement of the procedure to be delete) in the current "foreign" procedure,
     * will be associated to the new created original statement in the "foreign" procedure.
     *
     * @param string $procedureId identifies the procedure to delete
     *
     * @throws ORMException
     * @throws InvalidDataException
     */
    protected function copyOriginalStatementToProceduresOfChildrenInOtherProcedures(string $procedureId): int
    {
        $copied = 0;
        $childrenInOtherProcedures = $this->getChildrenInOtherProcedures($procedureId);
        $originalsWhichHaveChildrenInAnotherProcedures = [];
        // order in array structure, to simplify access:
        foreach ($childrenInOtherProcedures as $child) {
            $originalsWhichHaveChildrenInAnotherProcedures[$child->getOriginalId()][$child->getProcedureId()][] = $child;
        }

        // all originals, which have minimum one child in another procedure
        foreach ($originalsWhichHaveChildrenInAnotherProcedures as $originalId => $childrenInAnotherProcedures) {
            foreach ($childrenInAnotherProcedures as $foreignProcedureId => $children) {
                /** @var Statement $originalStatement */
                $originalStatement = $this->get($originalId);

                // 1. Create new original in foreign procedure:
                /** @var Procedure $foreignProcedure */
                $foreignProcedure = $this->getEntityManager()->getReference(Procedure::class, $foreignProcedureId);

                // Special case: reuse gdprConsent from original Statement. Detaching and use for copied statement.
                $originalStatement->setGdprConsent(null);
                $newOriginalInForeignProcedure = $this->copyOriginalStatement(
                    $originalStatement,
                    $foreignProcedure,
                    $originalStatement->getGdprConsent()
                );

                $this->getEntityManager()->persist($originalStatement);
                $this->getEntityManager()->persist($newOriginalInForeignProcedure);

                // Set new original for children of foreign procedure
                foreach ($children as $child) {
                    // set each children of current original STN in current foreign procedure to new created original STN
                    $child->setOriginal($newOriginalInForeignProcedure);
                    $this->getEntityManager()->persist($child);
                }
                ++$copied;
            }
        }
        $this->getEntityManager()->flush();

        return $copied;
    }

    /**
     * @return StatementMeta[]
     */
    public function getAllStatementMetas(): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('meta')
            ->from(StatementMeta::class, 'meta')
            ->getQuery()->getResult();
    }

    /**
     * @return StatementVersionField[]
     */
    public function getAllStatementVersionFields()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('versionField')
            ->from(StatementVersionField::class, 'versionField')
            ->getQuery()->getResult();
    }

    /**
     * Returns only original statements and these whose related procedure is not deleted.
     *
     * @return array<int, array<string, mixed>>
     * @throws CustomerNotFoundException
     */
    public function getOriginalStatements(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb->select('statement.publicStatement')
            ->addSelect('meta.submitUId')
            ->addSelect('user.id as userId')
            ->addSelect('statement.manual as isManual')
            ->addSelect('procedure.id as procedureId')
            ->from(Statement::class, 'statement')
            ->leftJoin('statement.procedure', 'procedure')
            ->leftJoin('procedure.customer', 'customer')
            ->leftJoin('statement.meta', 'meta')
            ->leftJoin('statement.user', 'user')
            ->andWhere('statement.deleted = :deleted')
            ->andWhere('statement.original IS NULL')
            ->andWhere('procedure.deleted = :deleted')
            ->andWhere('procedure.master = :master')
            ->andWhere('customer.id = :customerId')
            ->setParameter('deleted', false)
            ->setParameter('master', false)
            ->setParameter('customerId', $this->customerService->getCurrentCustomer())
            ->getQuery()
            ->getResult();
    }

    public function copyAttachment(Statement $targetStatement, StatementAttachment $attachment): StatementAttachment
    {
        $copiedAttachment = new StatementAttachment();
        $copiedAttachment->setStatement($targetStatement);
        $copiedAttachment->setType($attachment->getType());

        // use new file for new attachment
        $fileCopy = $this->copyFile($attachment->getFile(), $targetStatement);
        $copiedAttachment->setFile($fileCopy);

        return $copiedAttachment;
    }

    /**
     * This copy-files-logic belongs into the {@see FileService}.
     *
     * Copying the reverences to an existing file on the storage by copying the FileContainer entries.
     * The result is, that the created originalStatement will keep the reverence to the already existing file.
     *
     * @throws ORMException
     */
    public function copyFileContainers(Statement $originalToCopy, Statement $newOriginalStatement): void
    {
        $fileContainers = $this->getFileContainerRepository()
            ->getStatementFileContainers($originalToCopy->getId());

        $fileStrings = [];
        foreach ($fileContainers as $fileContainer) {
            $statementFileContainer = $this->copyFileContainer($fileContainer, $newOriginalStatement);
            $fileStrings[] = $statementFileContainer->getFileString();
        }

        $newOriginalStatement->setFiles($fileStrings);
    }

    public function copyFileContainer(FileContainer $sourceFileContainer, Statement $targetStatement): FileContainer
    {
        $statementFileContainer = new FileContainer();
        $statementFileContainer->setEntityClass(Statement::class);
        $statementFileContainer->setEntityId($targetStatement->getId());
        $statementFileContainer->setEntityField('file');
        $statementFileContainer->setFileString($sourceFileContainer->getFileString());

        // use new file for new container
        $fileCopy = $this->copyFile($sourceFileContainer->getFile(), $targetStatement);
        $statementFileContainer->setFile($fileCopy);

        $this->getEntityManager()->persist($statementFileContainer);

        return $statementFileContainer;
    }

    public function copyFile(File $sourceFile, Statement $targetStatement): File
    {
        $fileCopy = $this->getFileRepository()->copyFile($sourceFile);
        $fileCopy->setProcedure($targetStatement->getProcedure());
        $this->getEntityManager()->persist($fileCopy);

        return $fileCopy;
    }

    private function getFileContainerRepository(): FileContainerRepository
    {
        return $this->getEntityManager()->getRepository(FileContainer::class);
    }

    private function getFileRepository(): FileRepository
    {
        return $this->getEntityManager()->getRepository(File::class);
    }
}
