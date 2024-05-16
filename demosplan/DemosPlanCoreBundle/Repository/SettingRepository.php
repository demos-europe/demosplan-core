<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Exception;

/**
 * @template-extends FluentRepository<Setting>
 */
class SettingRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $key
     *
     * @return Setting[]
     */
    public function get($key)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('setting')
            ->from(Setting::class, 'setting')
            ->where('setting.key = :key')
            ->setParameter('key', $key)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Get all Settings with given procedure ID.
     *
     * @param string $procedureId
     *
     * @return Setting[]
     */
    public function getSettingsByProcedureId($procedureId)
    {
        return $this->findBy(['procedure' => $procedureId]);
    }

    /**
     * Select all settings entries from DB.
     *
     * @return array
     */
    public function getAllSettings()
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('setting')
            ->from(Setting::class, 'setting')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(array $data): never
    {
        throw new Exception('Inserts are implemented as upsert via update()');
    }

    /**
     * Update Entity.
     *
     * @param string $key
     *
     * @throws OptimisticLockException
     */
    public function update($key, array $data): Setting
    {
        // nimm den Content aus dem Suchfilter heraus
        $filter = $data;
        unset($filter['content']);

        $settingList = $this->getSettingsByKeyAndSetting($key, $filter);

        $setting = new Setting();
        $this->setProperties($setting, $data);
        if (!isset($settingList)) {
            $setting->setKey($key);
            $this->getEntityManager()->persist($setting);
            $this->getEntityManager()->flush();
        } else {
            $this->updateSettingList($settingList, $setting);
        }

        return $setting;
    }

    public function updateObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Set the properties of a Setting.
     *
     * @param setting|string $setting    ID|Object to be filled
     * @param array          $properties - Holds the values, which should be set as properties of the object
     */
    public function setProperties($setting, $properties)
    {
        if (!$setting instanceof Setting) {
            $setting = $this->_em->getReference(Setting::class, $setting);
        }

        if (array_key_exists('content', $properties)) {
            $setting->setContent($properties['content']);
        }

        if (array_key_exists('user', $properties)) {
            $setting->setUser($properties['user']);
        }

        if (array_key_exists('userId', $properties)) {
            $setting->setUser($this->getEntityManager()->getReference(User::class, $properties['userId']));
        }

        if (array_key_exists('orga', $properties)) {
            $setting->setOrga($properties['orga']);
        }

        if (array_key_exists('orgaId', $properties)) {
            $setting->setOrga($this->getEntityManager()->getReference(Orga::class, $properties['orgaId']));
        }

        if (array_key_exists('procedure', $properties)) {
            $setting->setProcedure($properties['procedure']);
        }

        if (array_key_exists('procedureId', $properties)) {
            $setting->setProcedure($this->getEntityManager()->getReference(Procedure::class, $properties['procedureId']));
        }
    }

    /**
     * @param string $key
     * @param array  $setting
     *
     * @return array|null
     *
     * @deprecated do not spread usage of this; see T21768
     */
    public function getSettingsByKeyAndSetting($key, $setting)
    {
        // finde eintrag mit den werten von setting
        $setting['key'] = $key;
        $settingKeys = ['procedureId', 'orgaId', 'userId'];
        foreach ($settingKeys as $settingKey) {
            if (isset($setting[$settingKey])) {
                $setting[substr($settingKey, 0, -2)] = $setting[$settingKey];
                unset($setting[$settingKey]);
            }
        }
        $settingList = $this->findBy($setting);

        if (!isset($settingList[0])) {
            return null;
        }

        return $settingList;
    }

    private function updateSettingList($settingList, $setting)
    {
        foreach ($settingList as $singleSetting) {
            $this->updateSetting($singleSetting, $setting);
        }
    }

    /**
     * @param Setting $toUpdate
     * @param Setting $settingRequest
     *
     * @return bool
     */
    public function updateSetting($toUpdate, $settingRequest)
    {
        $updated = false;

        if (null != $settingRequest->getKey()) {
            $toUpdate->setKey($settingRequest->getKey());
            $updated = true;
        }

        if (null !== $settingRequest->getContent()) {
            $toUpdate->setContent($settingRequest->getContent());
            $updated = true;
        }

        if (null != $settingRequest->getOrga()) {
            $toUpdate->setOrga($settingRequest->getOrga());
            $updated = true;
        }

        if (null != $settingRequest->getProcedure()) {
            $toUpdate->setProcedure($settingRequest->getProcedure());
            $updated = true;
        }

        if (null != $settingRequest->getUser()) {
            $toUpdate->setUser($settingRequest->getUser());
            $updated = true;
        }

        $this->getEntityManager()->persist($toUpdate);
        $this->getEntityManager()->flush();

        return $updated;
    }

    /**
     * Delete a settings entry with a given id.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete($entityId)
    {
        $toDelete = $this->find($entityId);
        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * @param Setting $entity
     *
     * @return bool
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function deleteObject($entity)
    {
        return $this->delete($entity->getId());
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return void
     */
    public function generateObjectValues($entity, array $data)
    {
    }

    public function addObject($entity)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        return $entity;
    }

    /**
     * Returns the Setting of the given user.
     *
     * @param string $userid identifies the user, whose settings will be returns
     *
     * @return array - List of settings
     */
    public function getSettingByUserId($userid)
    {
        return $this->findBy(['user' => $userid]);
    }

    /**
     * Returns the Setting of the given user.
     *
     * @param string $organisationid - Identifies the organisation, whose settings will be returns
     *
     * @return array - List of settings
     */
    public function getSettingByOrganisationId($organisationid)
    {
        return $this->findBy(['orga' => $organisationid]);
    }

    /**
     * @param string $sourceProcedureId
     */
    public function copy($sourceProcedureId, Procedure $newProcedure)
    {
        $sourceSettnigs = $this->getSettingsByProcedureId($sourceProcedureId);

        foreach ($sourceSettnigs as $setting) {
            $newSetting = clone $setting;
            $newSetting->setIdent(null);
            $newSetting->setProcedure($newProcedure);
            $newSetting->setCreated(null);
            $newSetting->setModified(null);

            $this->addObject($newSetting);
        }
    }
}
