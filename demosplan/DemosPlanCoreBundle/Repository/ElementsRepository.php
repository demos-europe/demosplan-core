<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;
use Exception;

use function array_key_exists;
use function collect;

/**
 * @template-extends CoreRepository<Elements>
 */
class ElementsRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    public function __construct(
        DqlConditionFactory $dqlConditionFactory,
        ManagerRegistry $registry,
        PermissionsInterface $permissions,
        Reindexer $reindexer,
        SortMethodFactory $sortMethodFactory,
        string $entityClass = Elements::class,
    ) {
        parent::__construct($dqlConditionFactory, $registry, $reindexer, $sortMethodFactory, $entityClass);

        $this->permissions = $permissions;
    }

    /**
     * Get a element.
     *
     * @param string $id
     *
     * @return Elements|null
     *
     * @throws Exception
     */
    public function get($id)
    {
        return $this->getOneBy(['id' => $id]);
    }

    /**
     * Get the element which mach the given criteria.
     *
     * @param string[] $criteria
     *
     * @return Elements[]
     *
     * @throws Exception
     */
    public function getBy(array $criteria)
    {
        try {
            $elements = $this->findBy($criteria);

            return $this->filterElementsByPermissions($elements);
        } catch (Exception $e) {
            $this->logger->warning('Get elements by criteria failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Elements returned by this function are
     * <ul>
     *  <li>in the given procedure
     *  <li>top level elements, meaning no returned element has a parent set
     * </ul>.
     *
     * @param string $procedureId the procedure ID all returned elements must have
     * @param array  $notWheres   An array of arrays. The keys in the top array must be strings, each naming a member of the Element entity.
     *                            Each nested array lists string values that must not be values assigned to the respective member of the returned Elements.
     * @param array  $wheres
     *
     * @return Elements[] the results as returned by Doctrine
     */
    public function getTopElements($procedureId, $notWheres = [], $wheres = []): array
    {
        // rename method to getElements()?! - no, because we always need the top elements
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder = $queryBuilder
            ->select('element')->from(Elements::class, 'element')
            ->andWhere('element.pId = :pId')->setParameter('pId', $procedureId) // has procedure id
            ->orderBy('element.order', 'ASC');

        // because of the name of this method is get-TOP-ElementsByProcedureId, parentsOnly will be true
        $parentsOnly = true;
        if ($parentsOnly) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('element.elementParentId')); // has no parent
        }

        // apply additional filters
        foreach ($notWheres as $whereKey => $whereArray) {
            if (!empty($whereArray)) {
                $queryBuilder = $queryBuilder->andWhere($queryBuilder->expr()->notIn('element.'.$whereKey, $whereArray));
            }
        }
        foreach ($wheres as $whereKey => $whereArray) {
            if (empty($whereArray)) {
                // no element will match the condition 'key is one of those in this empty array'
                // hence just return no elements
                return [];
            } else {
                $queryBuilder = $queryBuilder->andWhere($queryBuilder->expr()->in('element.'.$whereKey, $whereArray));
            }
        }
        $elements = $queryBuilder->getQuery()->getResult();

        return $this->filterElementsByPermissions($elements);
    }

    /**
     * Get the element which mach the given criteria.
     *
     * @param array $criteria - Criteria, to filter by
     *
     * @return Elements|null
     *
     * @throws Exception|StatementElementNotFoundException
     */
    public function getOneBy(array $criteria)
    {
        try {
            /** @var Elements $element */
            $element = $this->findOneBy($criteria);
            if (null === $element) {
                throw StatementElementNotFoundException::create();
            }
            $elements = $this->filterElementsByPermissions([$element]);

            return 1 === count($elements) ? $elements[0] : null;
        } catch (Exception $e) {
            $this->logger->warning('Get elements by criteria failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add a element.
     *
     * @return Elements
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $element = $this->generateObjectValues(new Elements(), $data);

            $nextOrderNumber = $this->getNextFreeOrderIndex($data['pId']);
            $element->setOrder($nextOrderNumber);

            $em->persist($element);
            $em->flush();

            return $element;
        } catch (Exception $e) {
            $this->logger->warning('Create elements failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update a element.
     *
     * @param string $id
     *
     * @throws Exception
     */
    public function update($id, array $data): Elements
    {
        try {
            $em = $this->getEntityManager();

            $element = $this->get($id);
            $element = $this->generateObjectValues($element, $data);

            $em->persist($element);
            $em->flush();

            return $element;
        } catch (Exception $e) {
            $this->logger->warning('Update elements failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a element.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws Exception
     */
    public function delete($id)
    {
        try {
            $em = $this->getEntityManager();

            $element = $this->get($id);

            if (!is_null($element)) {
                $em->remove($element);
                $em->flush();
            }
        } catch (Exception $e) {
            $this->logger->warning('Delete elements failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all Elements of a procedure.
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
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->delete(Elements::class, 'e')
                ->andWhere('e.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Elements of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Elements $entity
     *
     * @return Elements
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        $commonFields = collect(['title', 'text', 'icon', 'category', 'order', 'file', 'designatedSwitchDate']);
        $this->setEntityFieldsOnFieldCollection($commonFields, $entity, $data);

        $this->setEntityFlagFieldsOnFlagFieldCollection(collect(['enabled']), $entity, $data);

        if (array_key_exists('pId', $data)) {
            $procedure = $this->getEntityManager()->getReference(Procedure::class, $data['pId']);
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($data['pId']);
            }
            $entity->setProcedure($procedure);
        }

        if (array_key_exists('parent', $data) && '' !== $data['parent']) {
            $entity->setParent($this->get($data['parent']));
        }

        if (array_key_exists('permission', $data)
            && null !== $data['permission']) {
            $entity->setPermission($data['permission']);
        }

        return $entity;
    }

    /**
     * Returns all paragraph-IDs, related to the element with the given ID.
     *
     * @param string $id
     *
     * @return array
     *
     * @throws Exception
     */
    public function getParagraphIds($id)
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->select('paragraph.id')
                ->from(Paragraph::class, 'paragraph')
                ->where('paragraph.element = :elementId')
                ->setParameter('elementId', $id)
                ->getQuery();

            $result = $query->getResult();

            $finalResult = [];

            foreach ($result as $id) {
                $finalResult[] = $id['id'];
            }

            return $finalResult;
        } catch (Exception $e) {
            $this->logger->warning('Get Paragraphs failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entityobject to database.
     *
     * @param Elements $entity
     */
    public function addObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param Elements $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @return int number of affected elements
     */
    public function autoSwitchElementsState(): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->update(Elements::class, 'element')
            // booleans are stored as int, hence we can switch the bool state using subtraction (1-1=0 and 1-0=1)
            ->set('element.enabled', '1-element.enabled')
            ->set('element.designatedSwitchDate', ':null')
            ->setParameter('null', null)
            ->where('element.deleted = false')
            ->andWhere('element.designatedSwitchDate < CURRENT_TIMESTAMP()');

        return $qb->getQuery()->execute();
    }

    /**
     * @throws Exception
     */
    public function updateObject($entity)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Update Element Object failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all Elements entities where no Paragraph entities are assigned to.
     *
     * @return string[]
     */
    public function getElementIdsWithoutParagraphsAndDocuments(string $procedureId): array
    {
        /** @var ParagraphRepository $paragraphRepository */
        $paragraphRepository = $this->getEntityManager()->getRepository(Paragraph::class);
        /** @var SingleDocumentRepository $paragraphRepository */
        $documentRepository = $this->getEntityManager()->getRepository(SingleDocument::class);
        $paragraphs = $paragraphRepository->findBy(['procedure' => $procedureId]);
        $documents = $documentRepository->findBy(['procedure' => $procedureId]);
        $elementIdsWithParagraphsOrDocuments = array_map(
            static fn ($paragraphOrDocument) =>
                /* @var Paragraph|SingleDocument $paragraphOrDocument */
                $paragraphOrDocument->getElement()->getId(),
            [...$paragraphs, ...$documents]
        );

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $queryBuilder = $queryBuilder->select('e')
            ->from(Elements::class, 'e')
            ->andWhere('e.pId = :pId')
            ->setParameter('pId', $procedureId);

        if (0 !== count($elementIdsWithParagraphsOrDocuments)) {
            $queryBuilder = $queryBuilder->andWhere($queryBuilder->expr()->notIn('e.id', $elementIdsWithParagraphsOrDocuments));
        }

        $elements = $queryBuilder->getQuery()->getResult();

        return array_map(
            static fn (Elements $element) => $element->getId(),
            $this->filterElementsByPermissions($elements)
        );
    }

    /**
     * New elements are sorted at the end of the existing elements,
     * hence find the largest order number of the existing elements
     * and use its increment as order number for the new elements (order numbers start at 1).
     */
    public function getNextFreeOrderIndex(string $procedureId): int
    {
        /** @var Elements|null $lastElement */
        $lastElement = $this->findOneBy(['pId' => $procedureId], ['order' => 'DESC']);

        return null === $lastElement ? 1 : $lastElement->getOrder() + 1;
    }

    /**
     * @deprecated please autowire this repository instead
     */
    public function setPermissions(Permissions $permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * T11292: filter elements if permission is enabled.
     *
     * Elements may only be visible for public or institution user.
     * This method filters Elements by return only Elements without limiting access or Elements, which the current
     * user is allowed to access, by its permissions.
     *
     * @param Elements[] $elements
     * @param string[]   $permissions
     *
     * @return Elements[]
     */
    public function filterElementsByPermissions(array $elements, array $permissions = ['feature_admin_element_public_access', 'feature_admin_element_invitable_institution_access']): array
    {
        if (!$this->permissions->hasPermission(
            'feature_admin_element_invitable_institution_or_public_authorisations'
        )) {
            return $elements;
        }

        if (!$this->permissions->hasPermissions($permissions, 'OR')) {
            // neither public nor institution -> no access rights at all -> false
            return [];
        }

        $permissionFilteredElements = collect($elements)->filter(function (Elements $element) use ($permissions) {
            if ($element->hasPermission(null)) {
                // current element has no needed permission, therefore no further checks are necessary
                return true;
            }

            foreach ($permissions as $permissionString) {
                // if one of needed permissions is required AND set for current user, element can be add to the result-set
                if ($this->permissions->hasPermission($permissionString) && $element->hasPermission(
                    $permissionString
                )) {
                    return true;
                }
            }

            return false;
        });

        return $permissionFilteredElements->toArray();
    }

    /**
     * Returns the Elements in the procedure with the given enabled status.
     *
     * @return Elements[]
     */
    public function getElementsByEnabledStatus(string $procedureId, bool $enabled): array
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder = $queryBuilder
            ->select('element')
            ->from(Elements::class, 'element')
            ->andWhere('element.pId = :pId')->setParameter('pId', $procedureId) // has procedure id
            ->andWhere('element.enabled = :enabled')->setParameter('enabled', $enabled);

        return $queryBuilder->getQuery()->getResult();
    }
}
