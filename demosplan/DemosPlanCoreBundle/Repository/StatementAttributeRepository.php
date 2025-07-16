<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends CoreRepository<StatementAttribute>
 */
class StatementAttributeRepository extends CoreRepository implements ArrayInterface
{
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['id' => $entityId]);
        } catch (NoResultException) {
            return null;
        }
    }

    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!(array_key_exists('stId', $data) ^ array_key_exists('dsId', $data) ^ array_key_exists('statement', $data) ^ array_key_exists('draftStatement', $data))) {
                throw new InvalidArgumentException('Trying to add a StatementAttribute without Statement- or DraftStatementId');
            }
            if (!array_key_exists('type', $data)) {
                throw new InvalidArgumentException('Trying to add a StatementAttribute without type');
            }

            $statementAttribute = new StatementAttribute();

            $statementAttribute = $this->generateObjectValues($statementAttribute, $data);

            $em->persist($statementAttribute);
            $em->flush();

            return $statementAttribute;
        } catch (Exception $e) {
            $this->logger->warning('Create StatementAttribute failed Message: ', [$e]);
            throw $e;
        }
    }

    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $statementAttribute = $this->get($entityId);
            $statementAttribute = $this->generateObjectValues($statementAttribute, $data);

            $em->persist($statementAttribute);
            $em->flush();

            return $statementAttribute;
        } catch (Exception $e) {
            $this->logger->warning(
                'Update StatementAttribute failed. Message: ', [$e]);
            throw $e;
        }
    }

    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();

            $entity = $this->get($entityId);

            $em->remove($entity);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete StatementAttribute failed ', [$e]);

            return false;
        }
    }

    /**
     * @param StatementAttribute $entity
     *
     * @return StatementAttribute
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        if (is_null($entity)) {
            return $entity;
        }

        $em = $this->getEntityManager();

        if (array_key_exists('stId', $data)) {
            $entity->setStatement($em->getReference(Statement::class, $data['stId']));
        } elseif (array_key_exists('dsId', $data)) {
            $entity->setDraftStatement($em->getReference(DraftStatement::class, $data['dsId']));
        }

        if (array_key_exists('statement', $data)) {
            $entity->setStatement($data['statement']);
        }
        if (array_key_exists('draftStatement', $data)) {
            $entity->setDraftStatement($data['draftStatement']);
        }

        if (array_key_exists('type', $data)) {
            $entity->setType($data['type']);
        }

        if (array_key_exists('value', $data)) {
            $entity->setValue($data['value']);
        }

        return $entity;
    }

    /**
     * @todo: One method should not handle two different entity types
     *
     * @param Statement|DraftStatement $entity
     * @param string                   $type
     * @param string                   $value
     *
     * @throws Exception
     */
    protected function addStatementAttribute($entity, $type, $value)
    {
        $this->assertIsSupportedType($entity);
        try {
            $em = $this->getEntityManager();
            $sa = new StatementAttribute();
            $sa->setType($type);
            $sa->setValue($value);

            if ($entity instanceof Statement) {
                // statementAttributes are not automatically added to Statements
                $sa->setStatement($entity);
                /*
                 * Looks like everything is broken. The thing is, that
                 * StatamentAttributes is an ArrayCollection of StatementAttributes in Statement.
                 * So there is actually a add() method.
                 */
                $entity->getStatementAttributes()->add($sa);
            } else {
                $sa->setDraftStatement($entity);
            }

            $em->persist($sa);
            $em->persist($entity);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->warning('Failed to add a StatementAttribute: ', [$e]);
            throw $e;
        }
    }

    /**
     * Removes attribute of the given statement, identified by the given type and value.
     * If no value is given, all attributes of the given type will be removed.
     *
     * @param Statement|DraftStatement $entity
     * @param string                   $type
     * @param string                   $value
     *
     * If value is not set, the method will delete each entry with the
     * given type
     *
     * @throws Exception
     */
    protected function removeStatementAttribute($entity, $type, $value = null)
    {
        $this->assertIsSupportedType($entity);
        try {
            $em = $this->getEntityManager();
            foreach ($entity->getStatementAttributes() as $statementAttribute) {
                if ($statementAttribute->getType() == $type && ($statementAttribute->getValue() == $value || is_null($value))) {
                    $entity->getStatementAttributes()->removeElement($statementAttribute);
                    $em->remove($statementAttribute);
                }
                $em->persist($entity);
            }
            $em->flush();
        } catch (Exception $e) {
            $this->logger->warning('Failed to add a StatementAttribute: ', [$e]);
            throw $e;
        }
    }

    /**
     * Removes statement attributes with the key 'priorityAreaKey', 'priorityAreaType' and 'noLocation'.
     *
     * @param DraftStatement $draftStatement
     *
     * @throws Exception
     */
    public function removePriorityAreaAttributes($draftStatement)
    {
        try {
            $this->removeStatementAttribute($draftStatement, 'priorityAreaKey');
            $this->removeStatementAttribute($draftStatement, 'priorityAreaType');
            $this->unsetNoLocation($draftStatement);
        } catch (Exception $e) {
            $this->logger->warning('Failed to remove priorityAreaAttributes: ', [$e]);
            throw $e;
        }
    }

    /**
     * Clones all Statement Attributes of the draftStatement and
     * adds them to the Statement.
     *
     * @param DraftStatement $draftStatement
     * @param Statement      $statement
     *
     * @throws Exception
     */
    public function copyStatementAttributes($draftStatement, $statement)
    {
        $this->assertIsSupportedType($statement);
        $this->assertIsSupportedType($draftStatement);
        try {
            foreach ($draftStatement->getStatementAttributes() as $statementAttribute) {
                $this->addStatementAttribute($statement, $statementAttribute->getType(), $statementAttribute->getValue());
            }

            $em = $this->getEntityManager();
            $em->persist($statement);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->warning('Failed to copy StatementAttributes: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Statement|DraftStatement $entity
     * @param string                   $countyname
     *
     * @throws Exception
     */
    public function addCounty($entity, $countyname)
    {
        try {
            $this->removeCounty($entity);
            $this->addStatementAttribute($entity, 'county', $countyname);
            $this->unsetNoLocation($entity);
        } catch (Exception $e) {
            $this->logger->warning('Failed to set County : ', [$e]);
            throw $e;
        }
    }

    /**
     * Removes the currenct county from statementattributes.
     *
     * @param Statement|DraftStatement $entity
     *
     * @throws Exception
     */
    public function removeCounty($entity)
    {
        try {
            $this->removeStatementAttribute($entity, 'county');
        } catch (Exception $e) {
            $this->logger->warning('Failed to remove county: ', [$e]);
            throw $e;
        }
    }

    /**
     * State that this statement has no location.
     *
     * @param Statement|DraftStatement $entity
     *
     * @throws Exception
     */
    public function setNoLocation($entity)
    {
        try {
            $this->removeStatementAttribute($entity, 'county');
            $this->removeStatementAttribute($entity, 'community');
            $this->addStatementAttribute($entity, 'noLocation', '1');
        } catch (Exception $e) {
            $this->logger->warning('Failed to mark statement nonlocated: ', [$e]);
            throw $e;
        }
    }

    /**
     * Removes the statement attribute with the key 'noLocation'.
     *
     * @param Statement|DraftStatement $entity
     *
     * @throws Exception
     */
    public function unsetNoLocation($entity)
    {
        try {
            $this->removeStatementAttribute($entity, 'noLocation', '1');
        } catch (Exception $e) {
            $this->logger->warning('Failed to unmark statement nonlocated: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Statement $entity
     * @param string    $statementId
     *
     * @throws Exception
     */
    public function addFetchGeodataPending($entity, $statementId)
    {
        try {
            $this->addStatementAttribute($entity, 'fetchGeodataPending', $statementId);
        } catch (Exception $e) {
            $this->logger->warning('Failed to set fetchGeodataPending : ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Statement $entity
     * @param string    $statementId
     *
     * @throws Exception
     */
    public function removeFetchGeodataPending($entity, $statementId)
    {
        try {
            $this->removeStatementAttribute($entity, 'fetchGeodataPending', $statementId);
        } catch (Exception $e) {
            $this->logger->warning('Failed to remove fetchGeodataPending : ', [$e]);
            throw $e;
        }
    }

    /**
     * Returns true if the given entity is a Statement or a DraftStatement.
     *
     * @param Statement|DraftStatement $entity
     *
     * @throws Exception
     */
    private function assertIsSupportedType($entity)
    {
        if (!(is_a($entity, Statement::class)
            ^ is_a($entity, DraftStatement::class))) {
            throw new Exception('Argument $entity must be Statement or DraftStatement');
        }
    }
}
