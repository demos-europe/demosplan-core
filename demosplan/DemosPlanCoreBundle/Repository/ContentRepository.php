<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\DeprecatedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;

/**
 * @template-extends CoreRepository<GlobalContent>
 */
class ContentRepository extends CoreRepository implements ArrayInterface
{
    public function getNewsListByRoles(array $roles, Customer $customer): array
    {
        $em = $this->getEntityManager();
        $query = $em->createQueryBuilder()
            ->select('globalContent')
            ->from(GlobalContent::class, 'globalContent')
            ->join('globalContent.roles', 'cr')
            ->where('cr.code IN (:roles)')
            ->setParameter('roles', $roles, Connection::PARAM_STR_ARRAY)
            ->orderBy('globalContent.createDate', 'DESC')
            ->andWhere('globalContent.deleted = :deleted')
            ->andWhere('globalContent.enabled = :enabled')
            ->andWhere('globalContent.type = :type')
            ->andWhere('globalContent.customer = :customer')
            ->setParameter('deleted', false)
            ->setParameter('enabled', true)
            ->setParameter('type', 'news')
            ->setParameter('customer', $customer)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Get SingleGlobalContent by Id.
     *
     * @param string $entityId
     *
     * @return CoreEntity|mixed|null
     */
    public function get($entityId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('globalContent')
            ->from(GlobalContent::class, 'globalContent')
            ->where('globalContent.ident = :ident')
            ->setParameter('ident', $entityId)
            ->setMaxResults(1)
            ->getQuery();
        try {
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            $this->logger->error('GetGisLayer failed, Id: '.$entityId, [$e]);

            return null;
        }
    }

    /**
     * Add Single Global Content to database.
     *
     * @param array $data - contains the values for the object, which will mapped to the DB
     *
     * @return GlobalContent - entity object with updated values
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $content = $this->generateObjectValues(new GlobalContent(), $data);
            $em->persist($content);

            $type = null;
            $manualSortScope = null;

            if (array_key_exists('manualSortScope', $data)) {
                $manualSortScope = $data['manualSortScope'];
            }

            if (array_key_exists('type', $data)) {
                $type = $data['type'];
            }

            if (array_key_exists('customer', $data)) {
                $customer = $data['customer'];
            }

            if (!is_null($manualSortScope) && !is_null($type) && !is_null($customer)) {
                /** @var ManualListSortRepository $manualListSortRepos */
                $manualListSortRepos = $em->getRepository(ManualListSort::class);
                $manualListSort = $manualListSortRepos->getManualListSort('global', $manualSortScope, 'content:'.$type, $customer);
                if (!is_null($manualListSort)) {
                    $identList = $manualListSort->getIdents();
                    $identList = $content->getIdent().','.$identList;

                    $manualListSortRepos->addList('global', $manualSortScope, 'content:'.$type, $identList, $customer);
                }
            }

            $em->flush();

            return $content;
        } catch (Exception $e) {
            $this->logger->warning('GlobalContent could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Single Global Content.
     *
     * @param string $entityId - The ID of the entry, which one will be updated
     * @param array  $data     - contains the updated values for the object, which will mapped to the DB
     *
     * @return GlobalContent will return the updated news-object, if the update was successful
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();
            $singleGlobalContent = $this->get($entityId);
            if (null === $singleGlobalContent) {
                $this->logger->warning('Update SingleGlobalContent failed Reason: Content is null');
                throw new EntityNotFoundException('GlobalContent with EntityId: '.$entityId.' not found!');
            }
            $singleGlobalContent = $this->generateObjectValues($singleGlobalContent, $data);

            if (null !== $singleGlobalContent) {
                $em->persist($singleGlobalContent);
                $em->flush();
            }

            return $singleGlobalContent;
        } catch (Exception $e) {
            $this->logger->warning(
                'Update SingleGlobalContent failed Reason: ', [$e]
            );
            throw $e;
        }
    }

    /**
     * @deprecated use {@link ObjectInterface::deleteObject()} instead
     *
     * @throws DeprecatedException
     */
    public function delete($entityId): never
    {
        throw new DeprecatedException('Use ObjectInterface::deleteObject instead');
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param GlobalContent $globalContent
     *
     * @return GlobalContent
     */
    public function generateObjectValues($globalContent, array $data)
    {
        if (null === $data) {
            return null;
        }

        $commonEntityFields = collect(
            [
                'type',
                'title',
                'text',
                'description',
                'picture',
                'pictitle',
                'pdf',
                'pdftitle',
                'customer',
            ]
        );

        $this->setEntityFieldsOnFieldCollection($commonEntityFields, $globalContent, $data);

        $flagFields = collect(['enabled', 'deleted']);

        $this->setEntityFlagFieldsOnFlagFieldCollection($flagFields, $globalContent, $data);

        $this->generateObjectValuesForRoles($globalContent, $data);
        $this->generateObjectValuesForCategories($globalContent, $data);

        return $globalContent;
    }

    /**
     * @param GlobalContent $globalContent
     */
    protected function generateObjectValuesForRoles($globalContent, array $data)
    {
        $globalContent->setRoles([]);

        if (array_key_exists('group_code', $data)) {
            if (is_array($data['group_code'])) {
                $allRolesForSelectedGroups = [];
                foreach ($data['group_code'] as $code) {
                    $roles = $this->getEntityManager()
                        ->getRepository(Role::class)
                        ->findBy(['groupCode' => $code]);

                    foreach ($roles as $role) {
                        $allRolesForSelectedGroups[] = $role;
                    }
                }
                $globalContent->setRoles($allRolesForSelectedGroups);
            }
        }
    }

    /**
     * @param GlobalContent $globalContent
     */
    protected function generateObjectValuesForCategories($globalContent, array $data)
    {
        /*
         * @todo: This allows only one category.
         * Should be array or something
         */
        $globalContent->setCategories([]);

        if (array_key_exists('category_id', $data)) {
            $categoryObject = $this->getEntityManager()
                ->getRepository(Category::class)
                ->find($data['category_id']);

            $globalContent->setCategories([$categoryObject]);
        }
    }

    /**
     * Get all enabled and not deleted categories.
     *
     * @param array $categoryNames - parameter to get categories of specific type
     *
     * @return Category[]
     */
    public function getCategories(array $categoryNames = [])
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('category')
                ->from(Category::class, 'category')
                ->andWhere('category.deleted = false')
                ->andWhere('category.enabled = true')
                ->orderBy('category.title');

            if (false === empty($categoryNames)) {
                $query->andWhere('category.name IN (:categoryNames)')
                    ->setParameter('categoryNames', $categoryNames, Connection::PARAM_STR_ARRAY);
            }

            return $query->getQuery()->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('GetCategories failed.', [$e]);

            return [];
        }
    }

    /**
     * Get all user created categories.
     *
     * @return array
     */
    public function getCustomCategories()
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('category')
                ->from(Category::class, 'category')
                ->andWhere('category.custom = true');

            return $query->getQuery()->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get custom Categories failed.', [$e]);

            return [];
        }
    }

    /**
     * Get the Entity related to the given ID.
     *
     * @param string $categoryId
     *
     * @return Category|object|null
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function getCategory($categoryId)
    {
        return $this->getEntityManager()->find(Category::class, $categoryId);
    }

    /**
     * @param string $name
     *
     * @return object|array|null
     */
    public function getCategoryByName($name)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('category')
                ->from(Category::class, 'category')
                ->where('category.name = :name')
                ->setParameter('name', $name)
                ->getQuery();

            return $query->getSingleResult();
        } catch (NoResultException $e) {
            $this->logger->error('GetCategories failed.', [$e]);

            return [];
        }
    }

    /**
     * Delete a specific category of the given Id if there are no related content.
     *
     * @param string|Category $category - Identify the category to delete
     *
     * @return bool - false if unsuccessfully deleted, otherwise true
     *
     * @throws Exception
     */
    public function deleteCategory($category)
    {
        try {
            $categoryToDelete = $category instanceof Category ? $category : $this->getCategory($category);

            if (false === $categoryToDelete->getGlobalContents()->isEmpty()) {
                return false;
            }

            $this->getEntityManager()->remove($categoryToDelete);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Delete category failed', [$e]);

            return false;
        }

        return true;
    }
}
