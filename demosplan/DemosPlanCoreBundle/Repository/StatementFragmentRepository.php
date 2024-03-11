<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Validator\Validation;

/**
 * @template-extends CoreRepository<StatementFragment>
 */
class StatementFragmentRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return StatementFragment|null
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get StatementFragment failed: ', [$e]);

            return null;
        }
    }

    /**
     * Get Entity by Id.
     *
     * @param string $statementId
     *
     * @return array|null
     */
    public function findByStatement($statementId) :?array
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->select('sf')
                ->from(StatementFragment::class, 'sf')
                ->where('sf.statement = :id')
                ->setParameter('id', $statementId)
                ->getQuery();
            return $query->getResult(Query::HYDRATE_ARRAY);
        } catch (Exception $e) {
            $this->logger->warning('Get StatementFragment failed: ', [$e]);

            return null;
        }
    }

    /**
     * Load the StatementFragment of the given Ids.
     *
     * @param array $statementIds
     *
     * @return StatementFragment[]
     */
    public function getStatementFragments($statementIds): array
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('sf')
            ->from(StatementFragment::class, 'sf')
            ->andWhere('sf.id IN (:ids)')
            ->setParameter('ids', $statementIds, Connection::PARAM_STR_ARRAY)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Add Entity to database.
     *
     * @return StatementFragment
     *
     * @throws Exception
     *
     * @deprecated use {@link StatementFragmentRepository::addObject()} instead
     */
    public function add(array $data)
    {
        trigger_error('Unexpected '.self::class.'::add invokation', E_USER_DEPRECATED);
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('text', $data)) {
                throw new InvalidArgumentException('Trying to add a StatementFragment without text');
            }

            $maxDisplayId = $this->getMaxDisplayId($data);
            $data['displayId'] = $maxDisplayId + 1;

            $statementFragment = $this->generateObjectValues(
                new StatementFragment(),
                $data
            );
            $em->persist($statementFragment);
            $em->flush();

            return $statementFragment;
        } catch (Exception $e) {
            $this->logger->warning(
                'Create StatementFragment failed Message: ', [$e]
            );
            throw $e;
        }
    }

    /**
     ** Returns all fragments related to the given tag-ID.
     *
     * @param string $tagId - the ID of the related tag
     *
     * @return array
     *
     * @throws Exception
     */
    public function getListByTag($tagId)
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->select('fragment')
                ->from(StatementFragment::class, 'fragment')
                ->join('fragment.tags', 'tags')
                ->where('tags = :tagId')
                ->setParameter('tagId', $tagId);

            return $query->getQuery()->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get List of StatementFragment by Tag failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param StatementFragment $statementFragment
     *
     * @throws Exception
     */
    public function addObject($statementFragment): StatementFragment
    {
        try {
            if (null === $statementFragment->getText()) {
                throw new InvalidArgumentException('Trying to add a StatementFragment without text');
            }

            if (null === $statementFragment->getDisplayIdRaw()) {
                $maxDisplayId = $this->getMaxDisplayId(['procedureId' => $statementFragment->getProcedureId()]);
                $statementFragment->setDisplayId($maxDisplayId + 1);
            }

            if (-1 === $statementFragment->getSortIndex()) {
                $procedure = $statementFragment->getProcedure();
                $statement = $statementFragment->getStatement();
                $maxSortIndex = null === $procedure || null === $statement
                    ? -1
                    : $this->getMaxSortIndex($procedure->getId(), $statement->getId());
                $statementFragment->setSortIndex($maxSortIndex + 1);
            }

            $statementFragment->setText($this->sanitize($statementFragment->getText(), [$this->obscureTag]));

            $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
            $validator->validate($statementFragment, null, [StatementFragment::VALIDATION_GROUP_MANDATORY]);

            $em = $this->getEntityManager();
            $em->persist($statementFragment);
            $em->flush();
        } catch (Exception $e) {
            $this->getLogger()->error('Add StatementFragment failed: ', [$e]);
            throw new RuntimeException('Could not add StatementFragment', 0, $e);
        }

        return $statementFragment;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return StatementFragment|false
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            /** @var StatementFragment $fragment */
            $fragment = $this->find($entityId);
            if (array_key_exists('elementId', $data)) {
                $data['element'] = $data['elementId'];
            }
            if (array_key_exists('paragraphId', $data)) {
                $data['paragraph'] = $data['paragraphId'];
            }
            if (array_key_exists('documentId', $data)) {
                $data['document'] = $data['documentId'];
            }
            $fragment = $this->generateObjectValues($fragment, $data);

            return $this->updateObject($fragment);
        } catch (Exception $e) {
            $this->logger->error('Update StatementFragment failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param StatementFragment $fragment
     *
     * @return StatementFragment|false
     */
    public function updateObject($fragment)
    {
        try {
            $manager = $this->getEntityManager();
            $fragment->setText($this->sanitize($fragment->getText(), [$this->obscureTag]));
            $manager->persist($fragment);
            $manager->flush();
        } catch (Exception $e) {
            $this->getLogger()->error('Update StatementFragment failed: ', [$e]);

            return false;
        }

        return $fragment;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteById($entityId)
    {
        /** @var StatementFragment|null $toDelete */
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Deletes all StatementFragment of a procedure.
     *
     * @param string $procedureId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteByProcedureId($procedureId)
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->delete(StatementFragment::class, 'sf')
                ->andWhere('sf.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete StatementFragment of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param StatementFragment $toDelete
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete): bool
    {
        if (null === $toDelete) {
            $this->logger->warning(
                'Delete StatementFragment failed: Given ID not found.'
            );
            throw new EntityNotFoundException('Delete StatementFragment failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete StatementFragment failed: ', [$e]);
        }

        return false;
    }

    /**
     * @param StatementFragment $entity
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteObject($entity)
    {
        return $this->delete($entity);
    }

    /**
     * Get all Entities.
     *
     * @return StatementFragment[]
     */
    public function getAll()
    {
        try {
            return $this->findAll();
        } catch (NoResultException) {
            return null;
        }
    }

    public function getFragmentsById(array $fragmentIds): array
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('fragment')
            ->from(StatementFragment::class, 'fragment')
            ->andWhere('fragment.id IN (:ids)')
            ->setParameter('ids', $fragmentIds, Connection::PARAM_STR_ARRAY)
            ->orderBy('fragment.sortIndex', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param StatementFragment $entity
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data): StatementFragment
    {
        $em = $this->getEntityManager();

        if (array_key_exists('assignee', $data)) {
            $entity->setAssignee($data['assignee']);
        }

        if (array_key_exists('archivedDepartmentName', $data)) {
            $entity->setArchivedDepartmentName($data['archivedDepartmentName']);
        }

        if (array_key_exists('archivedOrgaName', $data)) {
            $entity->setArchivedOrgaName($data['archivedOrgaName']);
        }

        if (array_key_exists('archivedVoteUserName', $data)) {
            $entity->setArchivedVoteUserName($data['archivedVoteUserName']);
        }

        if (array_key_exists('considerationAdvice', $data)) {
            $entity->setConsiderationAdvice($data['considerationAdvice']);
        }

        if (array_key_exists('consideration', $data)) {
            $entity->setConsideration($data['consideration']);
        }

        if (array_key_exists('text', $data)) {
            $entity->setText($this->sanitize($data['text'], [$this->obscureTag]));
        }

        if (array_key_exists('tags', $data)) {
            $tags = [];
            foreach ($data['tags'] as $tagId) {
                if ($tagId instanceof Tag) {
                    $tags[] = $tagId;
                } else {
                    $tags[] = $em->getReference(Tag::class, $tagId);
                }
            }
            $entity->setTags($tags);
        } else {
            $entity->setTags([]);
        }

        if (array_key_exists('displayId', $data)) {
            $entity->setDisplayId($data['displayId']);
        }

        if (array_key_exists('statementId', $data) && 36 === strlen(trim((string) $data['statementId']))) {
            $entity->setStatement($em->getReference(Statement::class, $data['statementId']));
        }
        if (array_key_exists('procedureId', $data) && 36 === strlen(trim((string) $data['procedureId']))) {
            $entity->setProcedure($em->getReference(Procedure::class, $data['procedureId']));
        }

        if (array_key_exists('departmentId', $data)) {
            $entity->setDepartment(null);
            if (36 === strlen(trim((string) $data['departmentId']))) {
                $entity->setDepartment($em->getReference(Department::class, $data['departmentId']));
                $entity->setAssignedToFbDate(new DateTime());
            }
        }

        if (array_key_exists('modifiedByDepartmentId', $data) && 36 === strlen((string) $data['modifiedByDepartmentId'])) {
            $entity->setModifiedByDepartment($em->getReference(Department::class, $data['modifiedByDepartmentId']));
        }
        if (array_key_exists('modifiedByUserId', $data) && 36 === strlen((string) $data['modifiedByUserId'])) {
            $entity->setModifiedByUser($em->getReference(User::class, $data['modifiedByUserId']));
        }

        if (array_key_exists('municipalities', $data)) {
            $municipalities = [];
            foreach ($data['municipalities'] as $municipalityId) {
                if ($municipalityId instanceof Municipality) {
                    $municipalities[] = $municipalityId;
                } else {
                    $municipalities[] = $em->getReference(Municipality::class, $municipalityId);
                }
            }
            $entity->setMunicipalities($municipalities);
        } else {
            $entity->setMunicipalities([]);
        }

        if (array_key_exists('counties', $data)) {
            $counties = [];
            foreach ($data['counties'] as $countyId) {
                if ($countyId instanceof County) {
                    $counties[] = $countyId;
                } else {
                    $counties[] = $em->getReference(County::class, $countyId);
                }
            }
            $entity->setCounties($counties);
        } else {
            $entity->setCounties([]);
        }

        if (array_key_exists('priorityAreas', $data)) {
            $priorityAreas = [];
            foreach ($data['priorityAreas'] as $priorityAreaId) {
                if ($priorityAreaId instanceof PriorityArea) {
                    $priorityAreas[] = $priorityAreaId;
                } else {
                    $priorityAreas[] = $em->getReference(PriorityArea::class, $priorityAreaId);
                }
            }
            $entity->setPriorityAreas($priorityAreas);
        } else {
            $entity->setPriorityAreas([]);
        }

        if (array_key_exists('element', $data) && '' != $data['element']) {
            $element = $data['element'];
            $element = $element instanceof Elements ? $element : $em->getReference(Elements::class, $element);
            $entity->setElement($element);
        } else {
            $entity->setElement(null);
        }

        if (array_key_exists('paragraph', $data) && '' != $data['paragraph']) {
            $paragraph = $data['paragraph'];

            if ($paragraph instanceof ParagraphVersion) {
                $entity->setParagraph($paragraph);
            }

            /** @var ParagraphVersionRepository $paragraphVersionRepository */
            $paragraphVersionRepository = $em->getRepository(ParagraphVersion::class);
            if ($paragraph instanceof Paragraph) {
                $entity->setParagraph($paragraphVersionRepository->createVersion($paragraph));
            }

            if (is_string($paragraph)) {
                /** @var ParagraphRepository $paragraphRepository */
                $paragraphRepository = $em->getRepository(Paragraph::class);
                // check whether we got paragraph or paragraphVersion Id string
                $paragraphEntity = $paragraphRepository->findOneBy(['id' => $paragraph]);
                if (null !== $paragraphEntity) {
                    $entity->setParagraph($paragraphVersionRepository->createVersion($paragraphEntity));
                }
                $paragraphVersionEntity = $paragraphVersionRepository->findOneBy(['id' => $paragraph]);
                if (null !== $paragraphVersionEntity) {
                    $entity->setParagraph($paragraphVersionEntity);
                }
            }
        } else {
            $entity->setParagraph(null);
        }

        if (array_key_exists('voteAdvice', $data)) {
            if ('' === $data['voteAdvice']) {
                $data['voteAdvice'] = null;
            }
            $entity->setVoteAdvice($data['voteAdvice']);
        }

        if (array_key_exists('vote', $data)) {
            if ('' === $data['vote']) {
                $data['vote'] = null;
            }
            $entity->setVote($data['vote']);
        }

        if (array_key_exists('status', $data)) {
            $entity->setStatus($data['status']);
        }
        if (array_key_exists('archivedDepartment', $data)) {
            $entity->setArchivedDepartment($data['archivedDepartment']);
        }

        if (array_key_exists('document', $data) && '' !== $data['document']) {
            $document = $data['document'];

            if ($document instanceof SingleDocumentVersion) {
                $entity->setDocument($document);
            }

            /** @var SingleDocumentVersionRepository $singleDocumentVersionRepository */
            $singleDocumentVersionRepository = $em->getRepository(SingleDocumentVersion::class);
            if ($document instanceof SingleDocument) {
                $entity->setDocument($singleDocumentVersionRepository->createVersion($document));
            }

            if (is_string($document)) {
                $singleDocumentRepository = $em->getRepository(SingleDocument::class);
                // check whether we got paragraph or paragraphVersion Id string
                $documentEntity = $singleDocumentRepository->findOneBy(['id' => $document]);
                if ($documentEntity instanceof SingleDocument) {
                    $entity->setDocument($singleDocumentVersionRepository->createVersion($documentEntity));
                }
                $singleDocumentVersionEntity = $singleDocumentVersionRepository->findOneBy(['id' => $document]);
                if ($singleDocumentVersionEntity instanceof SingleDocumentVersion) {
                    $entity->setDocument($singleDocumentVersionEntity);
                }
            }
        } else {
            $entity->setDocument(null);
        }

        if (array_key_exists('sortIndex', $data)) {
            $entity->setSortIndex($data['sortIndex']);
        }

        return $entity;
    }

    /**
     * Get max given number.
     *
     * @param array $data
     *
     * @return int
     */
    protected function getMaxDisplayId($data)
    {
        if (!isset($data['procedureId'])) {
            return 0;
        }
        $val = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('MAX(sf.displayId)')
            ->from(StatementFragment::class, 'sf')
            ->andWhere('sf.procedure = :procedureId')
            ->setParameter('procedureId', $data['procedureId'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $val;
    }

    protected function getMaxSortIndex(string $procedureId, string $statementId): int
    {
        // If no result is found then [0 => ['max' => null]] will be returned
        $maxSortIndex = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('MAX(sf.sortIndex) as max')
            ->from(StatementFragment::class, 'sf')
            ->where('sf.statement = :statementId')
            ->setParameter('statementId', $statementId)
            ->andWhere('sf.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getResult(Query::HYDRATE_SCALAR);

        return (int) ($maxSortIndex[0]['max'] ?? -1);
    }
}
