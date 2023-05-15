<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

class NewsRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get a news entry from DB by id.
     *
     * @param string $id
     *
     * @return News|null
     *
     * @throws NonUniqueResultException
     */
    public function get($id)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('singleNews')
            ->from(News::class, 'singleNews')
            ->where('singleNews.ident = :ident')
            ->setParameter('ident', $id)
            ->setMaxResults(1)
            ->getQuery();
        try {
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get singleNews failed, Id: '.$id.' Message:', [$e]);

            return null;
        }
    }

    /**
     * Copy the non-generated values of all news of a specific procedure.
     * Set the generated values to null, for regeneration.
     *
     * @param string $sourceProcedureId
     * @param string $newProcedureId
     *
     * @throws Exception
     */
    public function copy($sourceProcedureId, $newProcedureId)
    {
        try {
            /** @var News[] $news */
            $news = $this->findBy(['pId' => $sourceProcedureId]);

            if (0 < sizeof($news)) {
                foreach ($news as $singleNews) {
                    $newNews = clone $singleNews;
                    $newNews->setIdent(null);
                    $newNews->setPId($newProcedureId);
                    $newNews->setCreateDate(null);
                    $newNews->setDeleteDate(null);
                    $newNews->setModifyDate(null);

                    $this->getEntityManager()->persist($newNews);
                }
                $this->getEntityManager()->flush();
            }
        } catch (Exception $e) {
            $this->logger->warning('Copy news failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Insert a new Newsentry into the DB.
     *
     * @param array $data contains the values for the object, which will mapped to the DB
     *
     * @return News the created object, with the content of the given array
     *
     * @throws Exception
     */
    public function add(array $data): News
    {
        try {
            $em = $this->getEntityManager();
            $news = $this->generateObjectValues(new News(), $data);

            $em->persist($news);
            // is ist a manual sorted list?
            $type = 'news';
            $manualSortScope = 'procedure:'.$news->getPId();
            /** @var ManualListSortRepository $manualListSortRepos */
            $manualListSortRepos = $em->getRepository(ManualListSort::class);
            $manualListSort = $manualListSortRepos->getManualListSort($news->getPId(), $manualSortScope, $type);
            // if it is, add new item to list
            if (null !== $manualListSort) {
                $identList = $manualListSort->getIdents();
                $identList = $news->getIdent().','.$identList;
                $manualListSortRepos->addList($news->getPId(), $manualSortScope, $type, $identList);
            }
            $em->flush();

            return $news;
        } catch (Exception $e) {
            $this->logger->warning('News could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Update a single Newsentry in DB.
     *
     * @param string $entityId - The ID of the entry, whichone will be updated
     * @param array  $data     - contains the values for the object, which will mapped to the DB
     *
     * @return news - Will return the updated news-object, if the update was successful
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();
            $singleNews = $this->get($entityId);
            $singleNews = $this->generateObjectValues($singleNews, $data);

            if (!is_null($singleNews->getPId())) {
                $em->persist($singleNews);
                $em->flush();
            }

            return $singleNews;
        } catch (Exception $e) {
            $this->logger->warning('Update SingleNews failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a single News from the DB
     * not in use, cause news aren't properly deleted.
     *
     * @param string $newsId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete($newsId)
    {
        try {
            $em = $this->getEntityManager();
            $em->remove($em->getReference(News::class, $newsId));
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete NewsEntry failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete any News of a procedure from the DB.
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
                ->delete(News::class, 'n')
                ->andWhere('n.pId = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete News by Procedure failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all files of news of a procedure.
     *
     * @param string $procedureId
     *
     * @return array|null
     */
    public function getFilesByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('singleNews.pdf')
            ->addSelect('singleNews.picture')
            ->from(News::class, 'singleNews')
            ->where('singleNews.pId = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get pdf of news failed', [$e]);

            return null;
        }
    }

    /**
     * Set objectvalues by arraydata.
     *
     * @param News $news
     *
     * @return news
     */
    public function generateObjectValues($news, array $data)
    {
        // @improve T16727: general method to add if array key exists:
        if (array_key_exists('ident', $data)) {
            $news->setIdent($data['ident']);
        }

        if (array_key_exists('autoSwitchState', $data)) {
            $news->setDeterminedToSwitch($data['autoSwitchState']);
        }

        if (array_key_exists('designatedSwitchDate', $data)) {
            $news->setDesignatedSwitchDate($data['designatedSwitchDate']);
        }

        if (array_key_exists('title', $data)) {
            $news->setTitle($data['title']);
        }

        if (array_key_exists('description', $data)) {
            $news->setDescription($data['description']);
        }

        if (array_key_exists('text', $data)) {
            $news->setText($data['text']);
        }

        if (array_key_exists('pictitle', $data)) {
            $news->setPictitle($data['pictitle']);
        }

        if (array_key_exists('pId', $data)) {
            $news->setPId($data['pId']);
        }

        if (array_key_exists('designatedSwitchDate', $data)) {
            $news->setDesignatedSwitchDate($data['designatedSwitchDate']);
        }

        if (array_key_exists('designatedState', $data)) {
            $news->setDesignatedState($data['designatedState']);
        }

        if (array_key_exists('determinedToSwitch', $data)) {
            $news->setDeterminedToSwitch($data['determinedToSwitch']);
        }

        if (array_key_exists('picture', $data)) {
            if (is_array($data['picture'])) {
                $pictruesString = implode(', ', $data['picture']);
                $pictruesString = '['.$pictruesString.']';
                $news->setPicture($pictruesString);
            } else {
                $news->setPicture($data['picture']);
            }
        }

        if (array_key_exists('pdf', $data)) {
            if (is_array($data['pdf'])) {
                $pdfString = implode(', ', $data['pdf']);
                $pdfString = '['.$pdfString.']';
                $news->setPdf($pdfString);
            } else {
                $news->setPdf($data['pdf']);
            }
        }

        if (array_key_exists('pdftitle', $data)) {
            $news->setPdftitle($data['pdftitle']);
        }

        if (array_key_exists('enabled', $data)) {
            $news->setEnabled($data['enabled']);
        }

        if (array_key_exists('deleted', $data)) {
            $news->setDeleted($data['deleted']);
        }

        if (array_key_exists('group_code', $data)) {
            if (is_array($data['group_code'])) {
                $allRolesForSelectedGroups = [];
                foreach ($data['group_code'] as $code) {
                    $roleRepository = $this->getEntityManager()->getRepository(Role::class);

                    $roles = $roleRepository->findBy(['groupCode' => $code]);
                    foreach ($roles as $role) {
                        $allRolesForSelectedGroups[] = $role;
                    }

                    if (Role::GGUEST === $code) {
                        $allRolesForSelectedGroups[] = $roleRepository->findOneBy(
                            ['code' => Role::CITIZEN]
                        );
                    }
                }
                $news->setRoles($allRolesForSelectedGroups);
            }
        } else {
            $news->setRoles([]);
        }

        return $news;
    }

    /**
     * @return News[]
     *
     * @throws Exception
     */
    public function getNewsToAutoSetState(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('news')
            ->from(News::class, 'news')
            ->where('news.designatedSwitchDate IS NOT NULL')
            ->andWhere('news.determinedToSwitch = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws Exception
     */
    public function updateObject(News $news): News
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($news);
            $em->flush();

            return $news;
        } catch (Exception $e) {
            $this->logger->warning('Update News Object failed Reason: ', [$e]);
            throw $e;
        }
    }
}
