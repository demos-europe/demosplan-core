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
use Cocur\Slugify\Slugify;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Form\Procedure\AbstractProcedureFormTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhase;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\FluentProcedureQuery;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\TransactionRequiredException;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Querying\FluentQueries\FluentQuery;
use Exception;
use Symfony\Component\Validator\Validation;

use function array_key_exists;
use function array_merge;
use function array_unique;

/**
 * @template-extends SluggedRepository<Procedure>
 */
class ProcedureRepository extends SluggedRepository implements ArrayInterface, ObjectInterface
{
    /**
     * @return FluentProcedureQuery
     */
    public function createFluentQuery(): FluentQuery
    {
        return new FluentProcedureQuery($this->conditionFactory, $this->sortMethodFactory, $this->objectProvider);
    }

    /**
     * Fetch all info about certain Procedure.
     *
     * @param string $procedureId
     *
     * @return Procedure|null
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function get($procedureId)
    {
        // using getEntityManager to limit 'find' to Procedure entities, thus limiting
        // accidental misuse of the function (eg. getting entities of other types)
        return $this->getEntityManager()->find(Procedure::class, $procedureId);
    }

    /**
     * Fetch the 'name' and 'externalName' of the Procedure with the given ID.
     *
     * @param string $procedureId
     *
     * @return string[]|null
     *
     * @throws NonUniqueResultException
     */
    public function getNames($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('procedure.name')
            ->addSelect('procedure.externalName')
            ->from(Procedure::class, 'procedure')
            ->where('procedure.id = :ident')
            ->setParameter('ident', $procedureId)
            ->setMaxResults(1)
            ->getQuery();
        try {
            return $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get a list of all Procedures, which are not deleted.
     *
     * @param bool|null $master  Optional include Blaupausen; If set to null the query will be executed
     *                           <strong>without</strong> limiting the resulting Procedures using the
     *                           master property. If set to true only Procedures with the
     *                           master property set to true will be returned. If set to
     *                           false only Procedures with the master property set to
     *                           false will be returned.
     * @param bool      $idsOnly determines if the whole object will be returned or only the UUID of the object
     *
     * @return array<int, string>|array<int, Procedure>
     *
     * @throws Exception
     */
    public function getFullList(?bool $master = null, bool $idsOnly = false, ?Customer $customer = null): array
    {
        try {
            $em = $this->getEntityManager();
            $queryBuilder = $em->createQueryBuilder();
            $selector = $idsOnly ? 'p.id' : 'p';

            $queryBuilder
                ->select($selector)
                ->from(Procedure::class, 'p')
                ->join('p.orga', 'o')
                ->orderBy('o.name', 'asc')
                ->andWhere('p.deleted = :deleted')
                ->setParameter('deleted', false);

            if (null !== $customer) {
                $queryBuilder->andWhere('p.customer = :customer')->setParameter('customer', $customer);
            }

            if (!is_null($master)) {
                $queryBuilder->andWhere('p.master = :master')
                    ->setParameter('master', $master);
            }

            $result = $queryBuilder->getQuery()->getResult();

            return $idsOnly ? array_map('current', $result) : $result;
        } catch (Exception $e) {
            $this->logger->warning('Get List Procedure failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a list of all not deleted and open Procedures by dataInputOrga.
     *
     * @param array<int, string> $allowedPhases
     *
     * @return array<int, Procedure>
     *
     * @throws Exception
     */
    public function getProceduresForDataInputOrga(string $orgaId, array $allowedPhases): array
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('p')
                ->from(Procedure::class, 'p')
                ->join('p.dataInputOrganisations', 'o')
                ->andWhere('o.id = :orgaId')
                ->andWhere('p.deleted = 0')
                ->andWhere('p.closed = 0')
                ->setParameter('orgaId', $orgaId)
                ->orderBy('p.name', 'ASC');

            $prefilteredProcedures = $query->getQuery()->getResult();

            return collect($prefilteredProcedures)->filter(
                static fn (Procedure $procedure): bool => collect($allowedPhases)->contains($procedure->getPhase())
            )->toArray();
        } catch (Exception $e) {
            $this->getLogger()->warning('getProceduresForDataInputOrga failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add new Procedure.
     *
     * @return Procedure
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();

            if (!isset($data['copymaster'])) {
                throw new \InvalidArgumentException('No BlaupauseId given');
            }

            // Get Blaupause for the settings to extend
            $procedureMaster = $this->get($data['copymaster']);

            if (is_null($procedureMaster)) {
                throw new Exception('Blaupause '.$data['copymaster'].' not found');
            }

            // Clone Blaupause as we want a new Procedure
            $procedure = clone $procedureMaster;
            $procedure->setFiles(null);
            $procedure->setMasterTemplate(false);
            $em->persist($procedure); // persist procedure to get new UUID()
            // Copy Blaupausen Proceduresettings
            $procedureSettings = clone $procedureMaster->getSettings();
            $em->persist($procedureSettings); // persist procedureSettings to set relation to new procedure
            $procedureSettings->setProcedure($procedure);

            // refs T17912: If creating Procedure is not a master-procedure and
            // incoming planText is empty: Ensure current date as default value on create new procedure:
            if (
                (array_key_exists('master', $data) && false === $data['master'])
                && ('' === $procedureSettings->getPlanText() || null === $procedureSettings->getPlanText())
            ) {
                $planTextDate = new DateTime();
                $procedureSettings->setPlanText($planTextDate->format('d.m.Y'));
            }

            $procedure->setSettings($procedureSettings);

            $currentDate = new DateTime();
            $procedure->setDeletedDate($currentDate);
            $procedure->setClosedDate($currentDate);
            $procedure->setAuthorizedUsers([]);
            $procedure->setCustomer($data['customer']);
            // When a procedure is created we may get an empty string as its description
            // ('interne Notiz') from the FE. In this case we want to keep the current description
            // we already copied from the procedure template at this point.
            // the same counts for publicParticipationContact - T27400
            if ('' === ($data['desc'] ?? '')) {
                $data['desc'] = $procedure->getDesc();
            }
            if ('' === ($data['publicParticipationContact'] ?? '')) {
                $data['publicParticipationContact'] = $procedure->getPublicParticipationContact();
            }
            // Set Values that override Blaupausensettings
            $procedure = $this->generateObjectValues($procedure, $data);
            // default values different from blueprint
            $procedure->setCreatedDate($currentDate);
            $procedure->setPublicParticipationPhase($data['publicParticipationPhase']);
            $procedure->setInitialSlug();
            $procedure->setXtaPlanId($data['xtaPlanId'] ?? '');
            $procedure->setElements(new ArrayCollection());

            $procedure->setPhaseObject(new ProcedurePhase());
            $procedure->getPhaseObject()->copyValuesFromPhase($procedureMaster->getPhaseObject());
            $procedure->setPublicParticipationPhaseObject(new ProcedurePhase());
            $procedure->getPublicParticipationPhaseObject()->copyValuesFromPhase(
                $procedureMaster->getPublicParticipationPhaseObject()
            );

            // improve T20997:
            // this kind of denylisting should be avoided by do not using "clone"
            // instead copy each attribute which has to be copied (allowlisting)
            $procedure->clearExportFieldsConfiguration();
            $procedure->clearProcedureTypeDefinitions();
            // this will be filled later

            $this->validateProcedureLike($procedure);

            $em->persist($procedure);
            $em->flush();

            return $procedure;
        } catch (Exception $e) {
            $this->logger->warning('Procedure could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Procedures.
     *
     * @param array|string $procedureIds
     *
     * @return true
     *
     * @throws Exception
     */
    public function deleteProcedures($procedureIds)
    {
        try {
            $em = $this->getEntityManager();
            if (!is_array($procedureIds)) {
                $procedureIds = [$procedureIds];
            }
            if (0 === count($procedureIds)) {
                throw new \InvalidArgumentException('No ProcedureIds given to delete');
            }

            foreach ($procedureIds as $procedureId) {
                if (!is_string($procedureId) || 36 !== strlen($procedureId)) {
                    throw new \InvalidArgumentException('ProcedureId '.print_r($procedureId, true).' not valid');
                }

                $em->remove($em->getReference(Procedure::class, $procedureId));
                $em->getRepository(ReportEntry::class)
                    ->deleteByProcedure($procedureId);
            }
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Procedure failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $procedureId
     *
     * @return array
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteRelatedEntitiesOfProcedure($procedureId)
    {
        $entityManager = $this->getEntityManager();
        $filesToDelete = [];

        // Delete News
        /** @var NewsRepository $newsRepos */
        $newsRepos = $entityManager->getRepository(News::class);
        foreach ($newsRepos->getFilesByProcedureId($procedureId) as $newsPdfToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($newsPdfToDelete)];
        }
        $newsRepos->deleteByProcedureId($procedureId);

        // Delete GIS-Layer
        /** @var MapRepository $gisRepos */
        $gisRepos = $entityManager->getRepository(GisLayer::class);
        foreach ($gisRepos->getLegendsByProcedureId($procedureId) as $legendsToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($legendsToDelete)];
        }
        $gisRepos->deleteByProcedureId($procedureId);

        // Delete GIS-LayerCategory
        /** @var GisLayerCategoryRepository $gisCategoryRepos */
        $gisCategoryRepos = $entityManager->getRepository(GisLayerCategory::class);
        $gisCategoryRepos->deleteByProcedureId($procedureId);

        // Delete DraftStatements
        $draftStatementRepos = $entityManager->getRepository(DraftStatement::class);
        foreach ($draftStatementRepos->getFilesByProcedureId($procedureId) as $fileToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($fileToDelete)];
        }
        $draftStatementsToDelete = $draftStatementRepos->findBy(['procedure' => $procedureId]);
        foreach ($draftStatementsToDelete as $draftStatement) {
            $draftStatement->clearCategories();
            $draftStatement->clearStatementAttributes();
            $entityManager->remove($draftStatement);
        }

        // Delete DraftStatementVersions
        $draftStatementVersionRepos = $entityManager->getRepository(DraftStatementVersion::class);
        foreach ($draftStatementVersionRepos->getFilesByProcedureId($procedureId) as $fileToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($fileToDelete)];
        }

        $draftStatementVersionsToDelete = $draftStatementVersionRepos->findBy(['procedure' => $procedureId]);
        foreach ($draftStatementVersionsToDelete as $draftStatementVersion) {
            $draftStatementVersion->clearCategories();
            $entityManager->remove($draftStatementVersion);
        }

        // flush to persist state that is needed later to avoid stale references
        $entityManager->flush();

        $statementFragmentsToDelete = $entityManager->getRepository(StatementFragment::class)
            ->findBy(['procedure' => $procedureId]);
        foreach ($statementFragmentsToDelete as $statementFragment) {
            $statementFragment->getPriorityAreas()->clear();
            $statementFragment->getMunicipalities()->clear();
            $statementFragment->getCounties()->clear();
            $statementFragment->getTags()->clear();
            $entityManager->remove($statementFragment);
        }

        $statementsToDelete = $this->getStatementRepository()->findBy([
            'procedure' => $procedureId,
        ]);
        foreach ($statementsToDelete as $statementToDelete) {
            $statementToDelete->getPriorityAreas()->clear();
            $statementToDelete->getMunicipalities()->clear();
            $statementToDelete->getCounties()->clear();
            $statementToDelete->getVotes()->clear();
            $statementToDelete->getTags()->clear();
            foreach ($statementToDelete->getCluster() as $clusteredStatement) {
                $clusteredStatement->setHeadStatement(null);
            }
            $statementToDelete->getCluster()->clear();
            $entityManager->remove($statementToDelete);
        }

        // flush to persist state that is needed later to avoid stale references
        $entityManager->flush();

        // Delete ParagraphVersions
        $paragraphVersionsToDelete = $entityManager->getRepository(ParagraphVersion::class)
            ->findBy(['procedure' => $procedureId]);
        foreach ($paragraphVersionsToDelete as $paragraphVersionToDelete) {
            $entityManager->remove($paragraphVersionToDelete);
        }

        // Delete Paragraphs
        $paragraphsToDelete = $entityManager->getRepository(Paragraph::class)
            ->findBy(['procedure' => $procedureId]);
        foreach ($paragraphsToDelete as $paragraphToDelete) {
            $entityManager->remove($paragraphToDelete);
        }

        // Delete SingleDocumentVersions
        /** @var SingleDocumentVersionRepository $singleDocumentVersionRepos */
        $singleDocumentVersionRepos = $entityManager->getRepository(SingleDocumentVersion::class);
        foreach ($singleDocumentVersionRepos->getFilesByProcedureId($procedureId) as $fileToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($fileToDelete)];
        }
        $singleDocumentVersionRepos->deleteByProcedureId($procedureId);

        // Delete SingleDocuments
        /** @var SingleDocumentRepository $singleDocumentRepos */
        $singleDocumentRepos = $entityManager->getRepository(SingleDocument::class);
        foreach ($singleDocumentRepos->getFilesByProcedureId($procedureId) as $fileToDelete) {
            $filesToDelete = [...$filesToDelete, ...array_values($fileToDelete)];
        }
        $singleDocumentRepos->deleteByProcedureId($procedureId);

        // Delete Elements
        /** @var ElementsRepository $elementRepository */
        $elementRepository = $entityManager->getRepository(Elements::class);
        $elementRepository->deleteByProcedureId($procedureId);

        /** @var BoilerplateRepository $boilerplateRepository */
        $boilerplateRepository = $entityManager->getRepository(Boilerplate::class);
        $boilerplateRepository->unsetAllCategories($procedureId);

        /** @var BoilerplateCategoryRepository $boilerplateCategoryRepository */
        $boilerplateCategoryRepository = $entityManager->getRepository(BoilerplateCategory::class);
        $boilerplateCategoryRepository->deleteByProcedureId($procedureId);

        $boilerplateRepository->deleteByProcedureId($procedureId);

        // Delete ManualListSorts
        /** @var ManualListSortRepository $manualListSortRepository */
        $manualListSortRepository = $entityManager->getRepository(ManualListSort::class);
        $manualListSortRepository->deleteByProcedureId($procedureId);

        // Delete Topics
        /** @var TagTopicRepository $tagTopicRepository */
        $tagTopicRepository = $entityManager->getRepository(TagTopic::class);
        $topicsToDelete = $tagTopicRepository->findBy(['procedure' => $procedureId]);
        /** @var TagTopic $topicToDelete */
        foreach ($topicsToDelete as $topicToDelete) {
            $entityManager->remove($topicToDelete);
        }

        // flush to persist state that is needed later to avoid stale references
        $entityManager->flush();

        /** @var ProcedureSettings[] $procedureSettingsToDelete */
        $procedureSettingsToDelete = $entityManager->getRepository(ProcedureSettings::class)
            ->findBy(['procedure' => $procedureId]);
        foreach ($procedureSettingsToDelete as $procedureSetting) {
            if (0 < strlen($procedureSetting->getPlanPDF())) {
                array_push($filesToDelete, $procedureSetting->getPlanPDF());
            }
            if (0 < strlen($procedureSetting->getPlanDrawPDF())) {
                array_push($filesToDelete, $procedureSetting->getPlanDrawPDF());
            }
        }

        $entityManager->flush();

        $entityManager = null;

        // return unique and not empty values
        return collect($filesToDelete)
            ->unique()
            ->filter(fn ($value) => !is_null($value) && 0 < mb_strlen((string) $value))
            ->toArray();
    }

    /**
     * Delete single Procedure.
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function delete($procedureId)
    {
        return $this->deleteProcedures([$procedureId]);
    }

    /**
     * Update Procedure with arraydata.
     *
     * @param string $entityId
     *
     * @return Procedure
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();
            $procedure = $this->get($entityId);
            $procedure = $this->generateObjectValues($procedure, $data);

            $this->validateProcedureLike($procedure);

            $em->persist($procedure);
            $em->flush();

            return $procedure;
        } catch (Exception $e) {
            $this->logger->warning('Update Procedure failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Sets objectvalues by arraydata of procedures.
     *
     * @param Procedure $procedure
     *
     * @return Procedure
     *
     * @throws ORMException
     */
    public function generateObjectValues($procedure, array $data)
    {
        if (array_key_exists('closed', $data)) {
            $procedure->setClosed($data['closed']);
        }
        if (array_key_exists('deleted', $data)) {
            $procedure->setDeleted($data['deleted']);
            $procedure->setCustomer(null);
            $procedure->setProcedureCategories([]);
            $procedure->setDeletedDate(Carbon::now());
        }

        if (array_key_exists('plisId', $data)) {
            $procedure->setPlisId($data['plisId']);
        }

        if (array_key_exists('desc', $data)) {
            $procedure->setDesc($data['desc']);
        }
        if (array_key_exists('endDate', $data)) {
            if (is_string($data['endDate']) && 0 < strlen($data['endDate'])) {
                $procedure->setEndDate($this->convertUserInputDate($data['endDate'], '23:59:59'));
            } elseif ($data['endDate'] instanceof DateTime) {
                $procedure->setEndDate(
                    Carbon::instance($data['endDate'])->endOfDay()->toDate()
                );
            }
        }
        if (array_key_exists('dataInputOrga', $data)) {
            $dataInputOrgas = [];
            foreach ($data['dataInputOrga'] as $dataInputOrga) {
                if ($dataInputOrga instanceof Orga) {
                    $dataInputOrgas[] = $dataInputOrga;
                } else {
                    $dataInputOrgas[] = $this->getEntityManager()->getReference(Orga::class, $dataInputOrga);
                }
            }
            $procedure->setDataInputOrganisations($dataInputOrgas);
        }
        if (array_key_exists('externalDesc', $data)) {
            $procedure->setExternalDesc($data['externalDesc']);
        }
        if (array_key_exists('externalName', $data)) {
            $procedure->setExternalName($data['externalName']);
        }
        if (array_key_exists('locationName', $data)) {
            $procedure->setLocationName($data['locationName']);
        }
        if (array_key_exists('locationPostCode', $data)) {
            $procedure->setLocationPostCode(substr((string) $data['locationPostCode'], 0, 5));
        }
        if (array_key_exists('ars', $data)) {
            $procedure->setArs(substr((string) $data['ars'], 0, 12));
        }

        if (array_key_exists('logo', $data)) {
            $procedure->setLogo($data['logo']);
        }
        if (array_key_exists('master', $data)) {
            $procedure->setMaster($data['master']);
        }
        if (array_key_exists('customer', $data)) {
            if (is_string($data['customer'])) {
                $data['customer'] = $this->getEntityManager()->getReference(Customer::class, $data['customer']);
            }
            $procedure->setCustomer($data['customer']);
        }
        if (array_key_exists('municipalCode', $data)) {
            $procedure->setMunicipalCode(substr((string) $data['municipalCode'], 0, 10));
        }
        if (array_key_exists('name', $data)) {
            $procedure->setName($data['name']);
        }
        if (array_key_exists('orgaName', $data)) {
            $procedure->setOrgaName($data['orgaName']);
        }
        if (array_key_exists('orgaId', $data)) {
            $procedure->setOrga(
                $this->getEntityManager()->getReference(
                    Orga::class,
                    $data['orgaId']
                )
            );
        }
        if (array_key_exists('phase', $data)) {
            $procedure->setPhase($data['phase']);
        }
        if (array_key_exists('phase_iteration', $data)) {
            $procedure->getPhaseObject()->setIteration($data['phase_iteration']);
        }
        if (array_key_exists('public_participation_phase_iteration', $data)) {
            $procedure->getPublicParticipationPhaseObject()
                ->setIteration($data['public_participation_phase_iteration']);
        }
        if (array_key_exists('shortUrl', $data)) {
            $procedure->setShortUrl($data['shortUrl']);
        }
        if (array_key_exists('publicParticipation', $data)) {
            $procedure->setPublicParticipation($data['publicParticipation']);
        }

        // avoid overwriting, if input is default "k.A."
        if (array_key_exists('publicParticipationContact', $data)
            && 'k.A.' !== $data['publicParticipationContact']
        ) {
            $procedure->setPublicParticipationContact($data['publicParticipationContact']);
        }
        if (array_key_exists('publicParticipationStartDate', $data)) {
            if (is_string($data['publicParticipationStartDate']) && 0 < strlen($data['publicParticipationStartDate'])) {
                $procedure->setPublicParticipationStartDate($this->convertUserInputDate($data['publicParticipationStartDate']));
            } elseif ($data['publicParticipationStartDate'] instanceof DateTime) {
                $procedure->setPublicParticipationStartDate($data['publicParticipationStartDate']);
            }
        }
        if (array_key_exists('publicParticipationEndDate', $data)) {
            if (is_string($data['publicParticipationEndDate']) && 0 < strlen($data['publicParticipationEndDate'])) {
                $procedure->setPublicParticipationEndDate($this->convertUserInputDate($data['publicParticipationEndDate'], '23:59:59'));
            } elseif ($data['publicParticipationEndDate'] instanceof DateTime) {
                $procedure->setPublicParticipationEndDate(
                    Carbon::instance($data['publicParticipationEndDate'])->endOfDay()->toDate()
                );
            }
        }
        if (array_key_exists('publicParticipationPhase', $data)) {
            $procedure->setPublicParticipationPhase($data['publicParticipationPhase']);
        }
        if (array_key_exists('publicParticipationPublicationEnabled', $data)) {
            $procedure->setPublicParticipationPublicationEnabled($data['publicParticipationPublicationEnabled']);
        }
        if (array_key_exists('startDate', $data)) {
            if (is_string($data['startDate']) && 0 < strlen($data['startDate'])) {
                $procedure->setStartDate($this->convertUserInputDate($data['startDate']));
            } elseif ($data['startDate'] instanceof DateTime) {
                $procedure->setStartDate($data['startDate']);
            }
        }
        if (array_key_exists('notificationReceivers', $data)) {
            $procedure->setNotificationReceivers($data['notificationReceivers']);
        }

        if (array_key_exists(AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS, $data)) {
            $procedure->setAgencyMainEmailAddress($data[AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS]);
        }

        if (array_key_exists('procedure_categories', $data)) {
            $procedure->setProcedureCategories($data['procedure_categories']);
        }

        if (array_key_exists(AbstractProcedureFormTypeInterface::AGENCY_EXTRA_EMAIL_ADDRESSES, $data)) {
            $inputEmailAddressStrings = $data[AbstractProcedureFormTypeInterface::AGENCY_EXTRA_EMAIL_ADDRESSES];
            /** @var EmailAddressRepository $emailAddressRepository */
            $emailAddressRepository = $this->getEntityManager()->getRepository(EmailAddress::class);
            $newEmailAddressEntities = $emailAddressRepository->getOrCreateEmailAddresses($inputEmailAddressStrings);
            $procedure->setAgencyExtraEmailAddresses(new ArrayCollection($newEmailAddressEntities));
        }

        if (array_key_exists('allowedSegmentAccessProcedureIds', $data)) {
            $allowedProcedures = $this->getProcedures($data['allowedSegmentAccessProcedureIds']);
            $procedure->getSettings()->setAllowedSegmentAccessProcedures(new ArrayCollection($allowedProcedures));
        }

        if (array_key_exists('organisations', $data)) {
            $organisationsToSet = [];
            foreach ($data['organisations'] as $orgaId) {
                $organisationsToSet[] = $this->getEntityManager()->getReference(Orga::class, $orgaId);
            }
            $procedure->setOrganisation($organisationsToSet);
        }

        if (array_key_exists('planningOffices', $data)) {
            $planningOfficesToSet = [];
            foreach ($data['planningOffices'] as $orgaId) {
                $planningOfficesToSet[] = $this->getEntityManager()->getReference(Orga::class, $orgaId);
            }
            $procedure->setPlanningOffices($planningOfficesToSet);
        }

        if (array_key_exists('authorizedUsers', $data)) {
            $usersToSet = $this->getUserRepository()->findBy([
                'id' => $data['authorizedUsers'],
            ]);
            $procedure->setAuthorizedUsers($usersToSet);
        }

        // Settings
        if (array_key_exists('settings', $data)) {
            // get ProcedureSettings to modify data
            $procedureSettings = $procedure->getSettings();
            if (array_key_exists('coordinate', $data['settings'])) {
                $procedureSettings->setCoordinate($data['settings']['coordinate']);
            }
            if (array_key_exists('boundingBox', $data['settings'])) {
                $procedureSettings->setBoundingBox($data['settings']['boundingBox']);
            }
            if (array_key_exists('defaultLayer', $data['settings'])) {
                $procedureSettings->setDefaultLayer($data['settings']['defaultLayer']);
            }
            if (array_key_exists('emailCc', $data['settings'])) {
                $procedureSettings->setEmailCc($data['settings']['emailCc']);
            }
            if (array_key_exists('emailTitle', $data['settings'])) {
                $procedureSettings->setEmailTitle($data['settings']['emailTitle']);
            }
            if (array_key_exists('emailText', $data['settings'])) {
                $procedureSettings->setEmailText($data['settings']['emailText']);
            }
            if (array_key_exists('informationUrl', $data['settings'])) {
                $procedureSettings->setInformationUrl($data['settings']['informationUrl']);
            }
            if (array_key_exists('mapExtent', $data['settings'])) {
                $procedureSettings->setMapExtent($data['settings']['mapExtent']);
            }
            if (array_key_exists('planDrawText', $data['settings'])) {
                $procedureSettings->setPlanDrawText($data['settings']['planDrawText']);
            }
            if (array_key_exists('planDrawPDF', $data['settings'])) {
                $procedureSettings->setPlanDrawPDF($data['settings']['planDrawPDF']);
            }
            if (array_key_exists('planText', $data['settings'])) {
                $procedureSettings->setPlanText($data['settings']['planText']);
            }
            if (array_key_exists('planPDF', $data['settings'])) {
                $procedureSettings->setPlanPDF($data['settings']['planPDF']);
            }
            if (array_key_exists('planPara1PDF', $data['settings'])) {
                $procedureSettings->setPlanPara1PDF($data['settings']['planPara1PDF']);
            }
            if (array_key_exists('planPara2PDF', $data['settings'])) {
                $procedureSettings->setPlanPara2PDF($data['settings']['planPara2PDF']);
            }
            if (array_key_exists('startScale', $data['settings'])) {
                $procedureSettings->setStartScale($data['settings']['startScale']);
            }
            if (array_key_exists('territory', $data['settings'])) {
                $procedureSettings->setTerritory($data['settings']['territory']);
            }
            if (array_key_exists('links', $data['settings'])) {
                $procedureSettings->setLinks($data['settings']['links']);
            }
            if (array_key_exists('pictogram', $data['settings'])) {
                $procedureSettings->setPictogram($data['settings']['pictogram']);
            }
            if (array_key_exists('pictogramCopyright', $data['settings'])) {
                $procedureSettings->setPictogramCopyright($data['settings']['pictogramCopyright']);
            }
            if (array_key_exists('pictogramAltText', $data['settings'])) {
                $procedureSettings->setPictogramAltText($data['settings']['pictogramAltText']);
            }
            if (array_key_exists('planningArea', $data['settings'])) {
                $procedureSettings->setPlanningArea($data['settings']['planningArea']);
            }

            $this->transferDesignatedExternalSwitch($procedureSettings, $data);
            $this->transferDesignatedInternalSwitch($procedureSettings, $data);

            if (array_key_exists('sendMailsToCounties', $data['settings'])) {
                $procedureSettings->setSendMailsToCounties($data['settings']['sendMailsToCounties']);
            }

            if (array_key_exists('scales', $data['settings'])) {
                $procedureSettings->setScales($data['settings']['scales']);
            }

            if (array_key_exists('legalNotice', $data['settings'])) {
                $procedureSettings->setLegalNotice($data['settings']['legalNotice']);
            }

            if (array_key_exists('copyright', $data['settings'])) {
                $procedureSettings->setCopyright($data['settings']['copyright']);
            }

            if (array_key_exists('mapHint', $data['settings'])) {
                $procedureSettings->setMapHint($data['settings']['mapHint']);
            }

            // Re-set ProcedureSettings to save Changes
            $procedure->setSettings($procedureSettings);

            if (array_key_exists('shortUrl', $data)) {
                $this->handleSlugUpdate($procedure, $data['shortUrl'], $data['oldSlug'] ?? '');
            }
        }

        return $procedure;
    }

    /**
     * @param int  $exactlyDaysToGo number of days, in which the procedures ends
     * @param bool $internal        check for institution phases. false checks public phases
     *
     * @return Procedure[] containing the procedures, which are ending in the given number of days
     *
     * @throws Exception
     */
    public function getListOfSoonEnding(int $exactlyDaysToGo, bool $internal = true): array
    {
        $resultProcedureList = [];

        try {
            $phase = $internal ? 'phase' : 'publicParticipationPhase';

            $query = $this->createFluentQuery();
            $query->getConditionDefinition()
                ->propertyHasValue(false, ['deleted'])
                ->propertyHasValue(false, ['master'])
                ->propertyHasValue(false, ['masterTemplate'])
                ->propertyHasValueAfterNow([$phase, 'endDate']);

            $query->getSortDefinition()->propertyDescending([$phase, 'endDate']);

            $notEndedProcedures = $query->getEntities();

            $currentTime = Carbon::today();
            $destinationDate = $currentTime->addDays($exactlyDaysToGo);

            /** @var Procedure $procedure */
            foreach ($notEndedProcedures as $procedure) {
                $endDate = $procedure->getEndDate();
                if (!$internal) {
                    $endDate = $procedure->getPublicParticipationEndDate();
                }
                if ($destinationDate->isSameDay($endDate)) {
                    $resultProcedureList[] = $procedure;
                }
            }

            return $resultProcedureList;
        } catch (Exception $e) {
            $this->getLogger()->warning(
                'getListIfSoonEnding with the given parameter '.$exactlyDaysToGo.' failed Reason: ',
                [$e]
            );
            throw $e;
        }
    }

    /**
     * Returns all Topics of a specific procedure.
     *
     * @param string $procedureId Identifies the procedure
     *
     * @return TagTopic[] List of Topics
     *
     * @throws Exception
     */
    public function getTopics($procedureId)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('tagTopic')
                ->from(TagTopic::class, 'tagTopic')
                ->where('tagTopic.procedure= :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->orderBy('tagTopic.title')
                ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('GetTopics of the procedure with ID: '.$procedureId.' failed: ', [$e]);
            throw $e;
        }
    }

    public function addObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Update the data in the database.
     *
     * @param Procedure $procedure
     *
     * @return Procedure
     *
     * @throws Exception
     */
    public function updateObject($procedure)
    {
        try {
            $this->validateProcedureLike($procedure);

            $em = $this->getEntityManager();
            $em->persist($procedure);
            $em->flush();

            return $procedure;
        } catch (Exception $e) {
            $this->logger->warning('Update Procedure Object failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a query builder prepared to get the e-mail-addresses of statements submitted by citizens and planers
     * to a specific procedure.
     * <p>
     * For statements created by citizens and planers we can get the citizen e-mail address
     * from the statement.meta.orgaEmail field. If citizens don't want to get notificated
     * meta.authorFeedback field will be false. Please note that statements submitted by institutions
     * needs to be handled separately.
     *
     * @param string $procedureId
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilderForCitizenPlanerStatementMailAddressesForProcedure($procedureId)
    {
        $citizenInstitutionQb = $this->getEntityManager()->createQueryBuilder();

        // get all (filled) orgaEmail entries from all statements in the procedure
        return $citizenInstitutionQb
            ->select('TRIM(meta.orgaEmail) as emailAddress')
            ->from(Statement::class, 'statement')
            // ->andWhere($citizenInstitutionQb->expr()->isNotNull('statement.parent'))
            ->andWhere('statement.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->andWhere('statement.feedback = :feedbackType')
            ->setParameter('feedbackType', 'email')
            ->leftJoin('statement.meta', 'meta')
            ->andWhere($citizenInstitutionQb->expr()->neq('TRIM(meta.orgaEmail)', ':empty'))
            ->setParameter('empty', '')
            ->andWhere($citizenInstitutionQb->expr()->isNotNull('meta.orgaEmail'))
            ->andWhere('meta.authorFeedback = :authorFeedback')
            ->setParameter('authorFeedback', 1)
            ->distinct(true);
    }

    /**
     * Get a query builder prepared to get the e-mail-addresses of statements submitted by institutions to a specific
     * procedure.
     * <p>
     * For statements created by institutions statement.meta.orgaEmail will be always empty. We detect
     * such statements by testing its 'manual' field to be false (manual statements are
     * created by planers) and its {@link Statement::publicStatement} field to be set to {@link Statement::INTERNAL}
     * (statements directly created by citizens are always {@link Statement::EXTERNAL})
     * From the found statements we'll use the {@link Orga::$email2} property of the institution
     * because this is the one currently used when sending the final notice to institutions
     * from the detail view of a statement.
     *
     * @param string $procedureId
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilderForInstitutionStatementMailAddressesForProcedure($procedureId)
    {
        $institutionQb = $this->getEntityManager()->createQueryBuilder();

        return $institutionQb
            // TODO: maybe statement.organization can be used instead of statement.user.organization, would avoid a join
            ->select('organisations.email2 as emailAddress')
            ->from(Statement::class, 'statement')
            // ->andWhere($institutionQb->expr()->isNotNull('statement.parent'))
            ->andWhere('statement.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->andWhere($institutionQb->expr()->eq('statement.manual', ':notmanual'))
            ->setParameter('notmanual', false)
            ->andWhere($institutionQb->expr()->eq('statement.publicStatement', ':internal'))
            ->setParameter('internal', Statement::INTERNAL) // registered citizens are external
            ->leftJoin('statement.user', 'user')
            ->leftJoin('user.orga', 'organisations')
            ->andWhere($institutionQb->expr()->neq('organisations.email2', ':empty'))
            ->setParameter('empty', '')
            ->andWhere($institutionQb->expr()->isNotNull('organisations.email2'))
            // TODO: add trim
            ->distinct(true);
    }

    /**
     * Get the e-mail-addresses of all statements (citizens, institutions, planer) submitted to a specific procedure.
     *
     * @param string $procedureId
     *
     * @return array
     */
    public function getStatementMailAddressesForProcedure($procedureId)
    {
        // TODO: merge queries for institutions, citizens and institutions and get a single array from the db
        $citizenInstitutionQuery = $this->getQueryBuilderForCitizenPlanerStatementMailAddressesForProcedure(
            $procedureId
        )
            ->getQuery();
        $institutionQuery = $this->getQueryBuilderForInstitutionStatementMailAddressesForProcedure($procedureId)
            ->getQuery();
        $citizenInstitutionResult = $citizenInstitutionQuery->getScalarResult();
        $institutionResult = $institutionQuery->getScalarResult();
        $merged = array_merge($citizenInstitutionResult, $institutionResult);
        $count = count($merged);
        for ($i = 0; $i < $count; ++$i) {
            $merged[$i] = $merged[$i]['emailAddress'];
        }

        return array_unique($merged);
    }

    /**
     * Get the count of the e-mail-addresses of all statements (citizens, institutions, planer) submitted to a specific
     * procedure.
     *
     * @param string $procedureId
     *
     * @return int
     */
    public function getStatementMailAddressesCountForProcedure($procedureId)
    {
        // TODO: merge queries for institutions, citizens and institutions and get a single count from the db
        return count($this->getStatementMailAddressesForProcedure($procedureId));
    }

    /**
     * Will update the data of the given procedure.
     *
     * @param string    $sourceProcedureId must exist in the database
     * @param Procedure $newProcedure      must not be null
     *
     * @return Procedure the given procedure
     *
     * @throws ORMException
     * @throws InvalidArgumentException
     */
    public function copyAgencyExtraEmailAddresses($sourceProcedureId, $newProcedure): Procedure
    {
        if (null === $newProcedure) {
            throw new InvalidArgumentException();
        }
        $sourceProcedure = $this->get($sourceProcedureId);
        if (null === $sourceProcedure) {
            throw new InvalidArgumentException('no procedure found for id: '.$sourceProcedureId);
        }
        $newProcedure->addAgencyExtraEmailAddresses($sourceProcedure->getAgencyExtraEmailAddresses());

        $this->validateProcedureLike($newProcedure);

        $this->getEntityManager()->persist($newProcedure);
        $this->getEntityManager()->flush();

        return $newProcedure;
    }

    /**
     * Including Statements, DraftStatements and DraftStatementVersions.
     */
    public function deleteStatements(string $procedureId): int
    {
        try {
            $amountOfDeletedStatements = $this->getStatementRepository()
                    ->deleteByProcedure($procedureId);
        } catch (Exception $e) {
            $this->getLogger()->error('Delete Statement of a procedure failed ', [$e]);
        }

        return $amountOfDeletedStatements ?? 0;
    }

    public function deleteDraftStatements(string $procedureId): int
    {
        try {
            /** @var DraftStatementRepository $draftStatementRepository */
            $draftStatementRepository = $this->getEntityManager()->getRepository(DraftStatement::class);
            $amountOfDeletedDraftStatements = $draftStatementRepository->deleteByProcedureId($procedureId);
        } catch (Exception $e) {
            $this->getLogger()->warning('Delete DraftStatement of a procedure failed ', [$e]);
        }

        return $amountOfDeletedDraftStatements ?? 0;
    }

    public function deleteDraftStatementVersions(string $procedureId): int
    {
        try {
            /** @var DraftStatementVersionRepository $draftStatementVersionRepository */
            $draftStatementVersionRepository = $this->getEntityManager()->getRepository(DraftStatementVersion::class);
            $amountOfDeletedDraftStatementVersions = $draftStatementVersionRepository->deleteByProcedureId($procedureId);
        } catch (Exception $e) {
            $this->getLogger()->warning('Delete DraftStatementVersions of a procedure failed ', [$e]);
        }

        return $amountOfDeletedDraftStatementVersions ?? 0;
    }

    /**
     * Deletes all ReportEntries of a specific procedure.
     *
     * @param string $procedureId identifies the Procedure
     *
     * @return int number of deleted Reports
     *
     * @throws Exception
     */
    public function deleteRelatedReports(string $procedureId): int
    {
        return $this->getEntityManager()->getRepository(ReportEntry::class)->deleteByProcedure($procedureId);
    }

    /**
     * Finds a Procedure which has, or has had, the received slug.
     * If none exists, returns null.
     *
     * @param string $slug
     *
     * @throws NonUniqueResultException
     */
    public function getProcedureBySlug($slug): ?Procedure
    {
        $slugify = new Slugify();
        $slug = $slugify->slugify($slug);
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('p')
            ->from(Procedure::class, 'p')
            ->join('p.slugs', 'slug')
            ->where('slug.name = :slug')
            ->setParameter('slug', $slug)
            ->getQuery();
        try {
            $result = $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        return $result;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    public function getProcedures(array $procedureIds): array
    {
        return $this->findBy(['id' => $procedureIds]);
    }

    /**
     * @return array<int, Procedure>
     *
     * @throws PaginationException
     * @throws PathException
     * @throws SortException
     */
    public function getUndeletedProcedures(): array
    {
        $query = $this->createFluentQuery();
        $query->getConditionDefinition()
            ->propertyHasValue(false, ['deleted'])
            ->propertyHasValue(false, ['master'])
            ->propertyHasValue(false, ['masterTemplate']);

        return $query->getEntities();
    }

    /**
     * @return array<int, Procedure>
     *
     * @throws Exception
     */
    public function getProceduresWithEndedParticipation(array $phaseKeys, bool $internal = true): array
    {
        try {
            $currentDate = new DateTime();
            $procedures = $this->getUndeletedProcedures();
            $phaseKeys = collect($phaseKeys);

            if ($internal) {
                $hits = collect($procedures)->filter(
                    static fn (Procedure $procedure): bool => $procedure->getEndDate() < $currentDate
                        && $phaseKeys->contains($procedure->getPhase())
                );
            } else {
                $hits = collect($procedures)->filter(
                    static fn (Procedure $procedure): bool => $procedure->getPublicParticipationEndDate() < $currentDate
                        && $phaseKeys->contains($procedure->getPublicParticipationPhase())
                );
            }

            return $hits->toArray();
        } catch (Exception $e) {
            $this->getLogger()->warning('getListOfEndedYesterday failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getInvitedOrgaIds(string $procedureId): array
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('organisation.id')
            ->from(Procedure::class, 'procedure')
            ->join('procedure.organisation', 'organisation')
            ->where('procedure.id = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getArrayResult();

        return array_column($result, 'id');
    }

    /**
     * @return array<int, string>
     */
    public function getPlanningOfficeIds(string $procedureId): array
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('planningOffices.id')
            ->from(Procedure::class, 'procedure')
            ->join('procedure.planningOffices', 'planningOffices')
            ->where('procedure.id = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getArrayResult();

        return array_column($result, 'id');
    }

    /**
     * Validates that the given instance is a valid procedure or procedure template.
     *
     * Doesn't belong into the repository layer, but is needed here because of
     * {@link ProcedureRepository::add()}. Do not expose (keep `private`) to avoid spreading
     * its usage throughout the application.
     */
    private function validateProcedureLike(Procedure $procedure): void
    {
        if ($procedure->getMaster()) {
            $this->validateProcedureTemplate($procedure);
        } else {
            $this->validateProcedure($procedure);
        }
    }

    /**
     * Validates that the given instance is a valid procedure. Procedure templates will not
     * pass the validation, use {@link ProcedureRepository::validateProcedureTemplate()} instead.
     *
     * Doesn't belong into the repository layer, but is needed here because of
     * {@link ProcedureRepository::add()}. Do not expose (keep `private`) to avoid spreading
     * its usage throughout the application.
     */
    private function validateProcedure(Procedure $procedure, string ...$additionalValidationGroups): void
    {
        $additionalValidationGroups[] = Procedure::VALIDATION_GROUP_DEFAULT;
        $additionalValidationGroups[] = Procedure::VALIDATION_GROUP_MANDATORY_PROCEDURE;
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violationList = $validator->validate($procedure, null, $additionalValidationGroups);
        $violationList->addAll($validator->validate($procedure->getPhaseObject()));
        $violationList->addAll($validator->validate($procedure->getPublicParticipationPhaseObject()));

        if (0 !== $violationList->count()) {
            throw ViolationsException::fromConstraintViolationList($violationList);
        }
    }

    /**
     * Validates that the given instance is a valid procedure template. Non-templates will not pass
     * the validation.
     *
     * Doesn't belong into the repository layer, but is needed here because of
     * {@link ProcedureRepository::add()}. Do not expose (keep `private`) to avoid spreading
     * its usage throughout the application.
     */
    private function validateProcedureTemplate(Procedure $procedure, string ...$additionalValidationGroups): void
    {
        $additionalValidationGroups[] = Procedure::VALIDATION_GROUP_DEFAULT;
        $additionalValidationGroups[] = Procedure::VALIDATION_GROUP_MANDATORY_PROCEDURE_TEMPLATE;
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violationList = $validator->validate($procedure, null, $additionalValidationGroups);
        $violationList->addAll($validator->validate($procedure->getPhaseObject()));
        $violationList->addAll($validator->validate($procedure->getPublicParticipationPhaseObject()));
        if (0 !== $violationList->count()) {
            throw ViolationsException::fromConstraintViolationList($violationList);
        }
    }

    /**
     * Procedures to switch are defined by {@link ProcedurePhase::$designatedSwitchDate} and
     * {@link ProcedurePhase::$designatedPublicSwitchDate}.
     * The needed accuracy is limited to 15 minutes, therefore a check of current timestamp will be
     * sufficient.
     *
     * The result will not contain deleted procedures.
     *
     * @return array<int, Procedure>
     *
     * @throws PaginationException
     * @throws PathException
     * @throws SortException
     */
    public function getProceduresReadyToSwitchPhases(): array
    {
        $query = $this->createFluentQuery();
        $conditionDefinition = $query->getConditionDefinition()
            ->propertyHasValue(false, ['deleted'])
            ->propertyHasValue(false, ['master'])
            ->propertyHasValue(false, ['masterTemplate']);

        $orCondition = $conditionDefinition->anyConditionApplies();
        $orCondition->allConditionsApply()
            ->propertyIsNotNull(['phase', 'designatedSwitchDate'])
            ->propertyIsNotNull(['phase', 'designatedPhase'])
            ->propertyIsNotNull(['phase', 'designatedEndDate'])
            ->propertyHasValueBeforeNow(['phase', 'designatedSwitchDate']);
        $orCondition->allConditionsApply()
            ->propertyIsNotNull(['publicParticipationPhase', 'designatedSwitchDate'])
            ->propertyIsNotNull(['publicParticipationPhase', 'designatedPhase'])
            ->propertyIsNotNull(['publicParticipationPhase', 'designatedEndDate'])
            ->propertyHasValueBeforeNow(['publicParticipationPhase', 'designatedSwitchDate']);

        return $query->getEntities();
    }

    private function transferDesignatedExternalSwitch(ProcedureSettings $procedureSettings, array $data): void
    {
        if (array_key_exists('designatedPublicSwitchDate', $data['settings'])) {
            $designatedPublicSwitchDate = $data['settings']['designatedPublicSwitchDate'];
            if (null !== $designatedPublicSwitchDate) {
                $designatedPublicSwitchDate = Carbon::createFromFormat(Carbon::ATOM, date(DATE_ATOM, strtotime((string) $designatedPublicSwitchDate)));
            }
            $procedureSettings->setDesignatedPublicSwitchDate($designatedPublicSwitchDate);
        }

        if (array_key_exists('designatedPublicPhase', $data['settings'])) {
            $procedureSettings->setDesignatedPublicPhase($data['settings']['designatedPublicPhase']);
        }

        if (array_key_exists('designatedPublicEndDate', $data['settings'])) {
            $convertedDate = $this->convertUserInputDate($data['settings']['designatedPublicEndDate']);
            $procedureSettings->setDesignatedPublicEndDate($convertedDate);
        }

        if (($data['settings']['designatedPublicSwitchDate'] ?? null) !== null
        || ($data['settings']['designatedPublicPhase'] ?? null) !== null
        || ($data['settings']['designatedPublicEndDate'] ?? null) !== null) {
            $procedureSettings->setDesignatedPublicPhaseChangeUser($data['currentUser']);
        }
    }

    private function transferDesignatedInternalSwitch(ProcedureSettings $procedureSettings, array $data): void
    {
        if (array_key_exists('designatedSwitchDate', $data['settings'])) {
            $designatedSwitchDate = $data['settings']['designatedSwitchDate'];
            if (null !== $designatedSwitchDate) {
                $designatedSwitchDate = Carbon::createFromFormat(Carbon::ATOM, date(DATE_ATOM, strtotime((string) $designatedSwitchDate)));
            }
            $procedureSettings->setDesignatedSwitchDate($designatedSwitchDate);
        }

        if (array_key_exists('designatedPhase', $data['settings'])) {
            $procedureSettings->setDesignatedPhase($data['settings']['designatedPhase']);
        }

        if (array_key_exists('designatedEndDate', $data['settings'])) {
            $convertedDate = $this->convertUserInputDate($data['settings']['designatedEndDate']);
            $procedureSettings->setDesignatedEndDate($convertedDate);
        }

        if (($data['settings']['designatedSwitchDate'] ?? null) !== null
            || ($data['settings']['designatedPhase'] ?? null) !== null
            || ($data['settings']['designatedEndDate'] ?? null) !== null) {
            $procedureSettings->setDesignatedPhaseChangeUser($data['currentUser']);
        }
    }

    private function getStatementRepository(): StatementRepository
    {
        return $this->getEntityManager()->getRepository(Statement::class);
    }

    private function getUserRepository(): UserRepository
    {
        return $this->getEntityManager()->getRepository(User::class);
    }

    /**
     * Extra method to get the shortUrl of a procedure by its id to avoid
     * hydrating the whole procedure.
     */
    public function findShortUrlById(string $procedureId): string
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.shortUrl')
            ->where('p.id = :id')
            ->setParameter('id', $procedureId)
            ->getQuery();

        return $qb->getSingleScalarResult();
    }
}
