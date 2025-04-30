<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementFile;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends CoreRepository<DraftStatement>
 */
class DraftStatementRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Gib eine Liste der Versionen der Stellungnahme zurück.
     */
    public function getVersionList(string $draftStatementId, string $organisationId): ?array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('draftStatementVersion')
            ->from(DraftStatementVersion::class, 'draftStatementVersion')
            ->where('draftStatementVersion.draftStatement = :draftStatementId')
            ->andWhere('draftStatementVersion.organisation = :organisationId')
            ->setParameter('draftStatementId', $draftStatementId)
            ->setParameter('organisationId', $organisationId)
            ->orderBy('draftStatementVersion.versionDate', 'DESC')
            ->orderBy('draftStatementVersion.lastModifiedDate', 'DESC')
            ->getQuery();

        try {
            return $query->getResult();
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
            ->select('ds.file')
            ->addSelect('ds.mapFile')
            ->from(DraftStatement::class, 'ds')
            ->where('ds.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of DraftStatements failed ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return DraftStatement
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('pId', $data)) {
                throw new InvalidArgumentException('Trying to add a Draft statement without ProcedureKey pId');
            }

            $draftStatement = $this->generateObjectValues(new DraftStatement(), $data);

            /** @var StatementRepository $statementRepository */
            $statementRepository = $this->getEntityManager()
                ->getRepository(Statement::class);

            $nextExternId = $statementRepository->getNextValidExternalIdForProcedure($data['pId']);

            // Anfangswert für Nummern soll 1000 sein
            $number = ($nextExternId < 1000) ? 1000 : $nextExternId;
            $draftStatement->setNumber($number);

            $em->persist($draftStatement);
            $em->flush();

            return $draftStatement;
        } catch (Exception $e) {
            $this->logger->warning('Create DraftStatement failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param DraftStatement $entity
     *
     * @return DraftStatement
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        // TODO: use setEntityFieldsOnFieldCollection for common fields

        if (is_null($entity)) {
            return $entity;
        }

        $em = $this->getEntityManager();

        // Bei einem Bürger nimm die Standarddaten.
        if (array_key_exists('anonym', $data) && true === $data['anonym']) {
            $data['uId'] = User::ANONYMOUS_USER_ID;
            $data['dId'] = User::ANONYMOUS_USER_DEPARTMENT_ID;
            $data['submitUId'] = User::ANONYMOUS_USER_ID;
            $data['oId'] = User::ANONYMOUS_USER_ORGA_ID;
            $data['oName'] = User::ANONYMOUS_USER_ORGA_NAME;
            $data['dName'] = User::ANONYMOUS_USER_DEPARTMENT_NAME;
            // wenn der Bürger mit Namen die Stellungnahme abgeben will, überschreibe ihn nicht
            if (!isset($data['useName']) || false == $data['useName']) {
                $data['uName'] = User::ANONYMOUS_USER_NAME;
            }
        }

        // Bürgerstellungnahmen werden als PublicDraftStatement gekennzeichnet
        if (array_key_exists('oId', $data) && User::ANONYMOUS_USER_ORGA_ID == $data['oId']) {
            $data['publicDraftStatement'] = DraftStatement::EXTERNAL;
        }

        if (array_key_exists('categories', $data)) {
            $entity->setCategories($data['categories']);
        }

        $flagFields = collect(
            ['deleted', 'rejected', 'negativ', 'released', 'submitted', 'publicAllowed', 'publicUseName', 'showToAll']
        );
        $this->setEntityFlagFieldsOnFlagFieldCollection($flagFields, $entity, $data);

        if (array_key_exists('dId', $data) && 0 < strlen((string) $data['dId'])) {
            $entity->setDepartment($em->getReference(Department::class, $data['dId']));
        }
        if (array_key_exists('dName', $data)) {
            $entity->setDName($data['dName']);
        }
        if (array_key_exists('document', $data)) {
            $entity->setDocument($data['document']);
        }
        if (!array_key_exists('document', $data) && array_key_exists('documentId', $data) && 0 < strlen(
            (string) $data['documentId']
        )
        ) {
            $entity->setDocument(
                $em->getReference(SingleDocumentVersion::class, $data['documentId'])
            );
        }
        if (!array_key_exists('document', $data) && array_key_exists('documentId', $data)
            && '' === $data['documentId']
        ) {
            $entity->setDocument(null);
        }
        if (array_key_exists('element', $data)) {
            $entity->setElement($data['element']);
        }
        if (array_key_exists('elementId', $data) && 0 < strlen((string) $data['elementId'])) {
            $entity->setElement(
                $em->getReference(Elements::class, $data['elementId'])
            );
        }
        if (array_key_exists('elementId', $data) && '' === $data['elementId']) {
            $entity->setElement(null);
        }
        if (array_key_exists('feedback', $data)) {
            $entity->setFeedback($data['feedback']);
        }

        if (array_key_exists('files', $data)) {
            $data['files'] = is_array($data['files']) ? $data['files'] : [$data['files']];

            foreach ($data['files'] as $fileString) {
                $draftStatementFile = new DraftStatementFile();
                $parts = explode(':', (string) $fileString);
                $draftStatementFile->setFile($em->getReference(File::class, $parts[1] ?? ''));
                $entity->addFile($draftStatementFile);
            }
        }

        if (array_key_exists('files_to_remove', $data)) {
            $data['files_to_remove'] = is_array($data['files_to_remove']) ? $data['files_to_remove'] : [$data['files_to_remove']];

            foreach ($data['files_to_remove'] as $fileId) {
                $entity->removeFileByFileId($fileId);
            }
        }

        if (array_key_exists('mapFile', $data)) {
            $entity->setMapFile($data['mapFile']);
        }
        if (array_key_exists('miscData', $data)) {
            $entity->setMiscData($data['miscData']);
        }

        if (array_key_exists('number', $data)) {
            $entity->setNumber($data['number']);
        }
        if (array_key_exists('oId', $data) && 0 < strlen((string) $data['oId'])) {
            $entity->setOrganisation($em->getReference(Orga::class, $data['oId']));
        }
        if (array_key_exists('oName', $data)) {
            $entity->setOName($data['oName']);
        }
        if (array_key_exists('paragraph', $data)) {
            $entity->setParagraph($data['paragraph']);
        }
        // nutze die paragraphId nur, wenn nicht schon das Objekt direkt gesetzt wurde
        if (!array_key_exists('paragraph', $data) && array_key_exists('paragraphId', $data) && 0 < strlen(
            (string) $data['paragraphId']
        )
        ) {
            $entity->setParagraph(
                $em->getReference(ParagraphVersion::class, $data['paragraphId'])
            );
        }
        if (!array_key_exists('paragraph', $data) && array_key_exists('paragraphId', $data)
            && '' === $data['paragraphId']
        ) {
            $entity->setParagraph(null);
        }
        if (array_key_exists('pId', $data) && 0 < strlen((string) $data['pId'])) {
            $entity->setProcedure(
                $em->getReference(Procedure::class, $data['pId'])
            );
        }
        if (array_key_exists('polygon', $data)) {
            $entity->setPolygon($data['polygon']);
        }

        if (array_key_exists('publicDraftStatement', $data)) {
            $entity->setPublicDraftStatement($data['publicDraftStatement']);
        }

        if (array_key_exists('rejectedReason', $data)) {
            $entity->setRejectedReason($data['rejectedReason']);
        }
        if (array_key_exists('rejectedDate', $data)) {
            $entity->setRejectedDate($data['rejectedDate']);
        }

        if (array_key_exists('releasedDate', $data)) {
            $entity->setReleasedDate($data['releasedDate']);
        }

        if (array_key_exists('submittedDate', $data)) {
            $entity->setSubmittedDate($data['submittedDate']);
        }
        if (array_key_exists('text', $data)) {
            $entity->setText($this->sanitize($data['text'], [$this->obscureTag]));
        }
        if (array_key_exists('title', $data)) {
            $entity->setTitle($data['title']);
        }
        if (array_key_exists('uId', $data) && 0 < strlen((string) $data['uId'])) {
            $entity->setUser($em->getReference(User::class, $data['uId']));
        }
        if (array_key_exists('uCity', $data)) {
            $entity->setUCity($data['uCity']);
        }
        if (array_key_exists('uEmail', $data)) {
            $entity->setUEmail($data['uEmail']);
        }
        if (array_key_exists('userEmail', $data)) {
            $entity->setUEmail($data['userEmail']);
        }
        if (array_key_exists('uName', $data)) {
            $entity->setUName($data['uName']);
        }
        if (array_key_exists('uPostalCode', $data)) {
            $entity->setUPostalCode($data['uPostalCode']);
        }
        if (array_key_exists('uStreet', $data)) {
            $entity->setUStreet($data['uStreet']);
        }
        if (array_key_exists('houseNumber', $data)) {
            $entity->setHouseNumber($data['houseNumber']);
        }
        if (array_key_exists('uFeedback', $data)) {
            $entity->setUFeedback($data['uFeedback']);
        }

        // Setze die Phase des Verfahrens ein
        if (array_key_exists('phase', $data)) {
            $entity->setPhase($data['phase']);
        } else {
            $procedure = $entity->getProcedure();
            if (!is_null($procedure)) {
                // public or internal statement?
                if (DraftStatement::INTERNAL === $entity->getPublicDraftStatement()) {
                    $entity->setPhase($procedure->getPhase());
                } else {
                    $entity->setPhase($procedure->getPublicParticipationPhase());
                }
            }
        }
        if (array_key_exists('represents', $data)) {
            $entity->setRepresents($data['represents']);
        }

        if (array_key_exists('anonymous', $data)) {
            $entity->setAnonymous($data['anonymous']);
        }

        if (array_key_exists('authorOnly', $data)) {
            $entity->setAuthorOnly($data['authorOnly']);
        }

        return $entity;
    }

    /**
     * Gets the maximum given DraftStatement number per procedure.
     *
     * @param string $procedureId
     *
     * @return int
     *
     * @throws NonUniqueResultException
     */
    public function getMaxDraftStatementNumber($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('MAX(ds.number) as max_num')
            ->from(DraftStatement::class, 'ds')
            ->where('ds.procedure = :pId')
            ->setParameter('pId', $procedureId)
            ->getQuery();

        try {
            // cast int from null == 1
            return (int) $query->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return DraftStatement
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $draftStatement = $this->get($entityId);
            $draftStatement = $this->generateObjectValues($draftStatement, $data);

            $em->persist($draftStatement);
            $em->flush();

            return $draftStatement;
        } catch (Exception $e) {
            $this->logger->warning(
                'Update DraftStatement failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get DraftStatement by Id.
     *
     * @param string $draftStatementId
     *
     * @return DraftStatement|null
     *
     * @throws Exception
     */
    public function get($draftStatementId)
    {
        return $this->find($draftStatementId);
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete($entityId)
    {
        return $this->deleteObject($this->get($entityId));
    }

    /**
     * @param CoreEntity $draftStatement
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteObject($draftStatement)
    {
        try {
            $this->getEntityManager()->remove($draftStatement);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete DraftStatementEntry failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all DraftStatements of a procedure.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteByProcedureId(string $procedureId): int
    {
        $deletedEntities = 0;
        $draftStatementsToDelete = $this->findBy(['procedure' => $procedureId]);
        /** @var DraftStatement $draftStatement */
        foreach ($draftStatementsToDelete as $draftStatement) {
            $this->getEntityManager()->remove($draftStatement);
            ++$deletedEntities;
        }
        $this->getEntityManager()->flush();

        return $deletedEntities;
    }

    /**
     * @param string $organisationId - identifies the organisation, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getAllDraftStatementsOfOrga($organisationId)
    {
        return $this->findBy(['organisation' => $organisationId]);
    }

    /**
     * @param string $departmentId - identifies the department, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getAllDraftStatementsOfDepartment($departmentId)
    {
        return $this->findBy(['department' => $departmentId]);
    }

    /**
     * Deletable draftStatements are draftStatements which are unreleased and not submitted.
     *
     * @param string $userId - identifies the user, whose draftStatements will be returned
     *
     * @return DraftStatement[]
     */
    public function getDeletableDraftStatementOfUser($userId)
    {
        return $this->findBy(['user' => $userId, 'submitted' => false, 'released' => false]);
    }

    /**
     * @param string[] $procedureIds
     *
     * @return DraftStatement[]
     */
    public function getUnsubmittedDraftStatementsProcedures(array $procedureIds, bool $internal): array
    {
        $publicDraftStatement = $internal ? DraftStatement::INTERNAL : DraftStatement::EXTERNAL;

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('draftStatement')
            ->from(DraftStatement::class, 'draftStatement')
            ->where('draftStatement.procedure IN (:procedureIds)')
            ->andWhere('draftStatement.rejected = false')
            ->andWhere('draftStatement.deleted = false')
            ->andWhere('draftStatement.submitted = false')
            ->andWhere('draftStatement.released = false')
            ->andWhere('draftStatement.publicDraftStatement = :publicDraftStatement')
            ->setParameter('procedureIds', $procedureIds)
            ->setParameter('publicDraftStatement', $publicDraftStatement)
            ->getQuery();

        return $query->getResult();
    }
}
