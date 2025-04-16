<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;

class CustomFieldConfigurationRepository extends CoreRepository
{
    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    private function add(CustomFieldConfiguration $customFieldConfiguration): CustomFieldConfiguration
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($customFieldConfiguration);
            $em->flush();

            return $customFieldConfiguration;
        } catch (Exception $e) {
            $this->logger->warning('CustomFieldConfiguration could not be added. ', [$e]);
            throw $e;
        }
    }

    public function findCustomFieldConfigurationByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity)
    {
        try {
            $criteria = ['sourceEntityId' => $sourceEntityId];
            $criteria['sourceEntityClass'] = $sourceEntity;
            $criteria['targetEntityClass'] = $targetEntity;

            return $this->findBy($criteria);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration: '.$e->getMessage());

            return null;
        }
    }

    public function createCustomFieldConfiguration(string $sourceEntity, string $sourceEntityId, string $targetEntity, $customField): CustomFieldConfiguration
    {
        $customFieldConfiguration = new CustomFieldConfiguration();

        $customFieldConfiguration->setSourceEntityClass($sourceEntity);
        $customFieldConfiguration->setSourceEntityId($sourceEntityId);

        $customFieldConfiguration->setTargetEntityClass($targetEntity);
        $customFieldConfiguration->setConfiguration($customField);
        $this->add($customFieldConfiguration);

        return $customFieldConfiguration;
    }

    public function updateObject($entity): CustomFieldConfiguration
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->error('Update CustomFieldConfiguration failed Reason: ', [$e]);
            throw $e;
        }
    }

    public function copy(string $sourceProcedureId, Procedure $newProcedure): void
    {
        $customFieldsConfigurations = $this->findCustomFieldConfigurationByCriteria( 'PROCEDURE_TEMPLATE', $sourceProcedureId, 'SEGMENT');

        if (empty($customFieldsConfigurations)) {
            return;
        }

        foreach ($customFieldsConfigurations as $customFieldConfiguration) {
            $newCustomFieldConfiguration = new CustomFieldConfiguration();
            $newCustomFieldConfiguration->setSourceEntityClass('PROCEDURE');
            $newCustomFieldConfiguration->setSourceEntityId($newProcedure->getId());
            $newCustomFieldConfiguration->setTargetEntityClass($customFieldConfiguration->getTargetEntityClass());
            $newCustomFieldConfiguration->setConfiguration($customFieldConfiguration->getConfiguration());
            $this->add($newCustomFieldConfiguration);
        }

    }

    public function getCustomFields(string $sourceEntity, string $sourceEntityId, string $targetEntity): ArrayCollection
    {
        $customFieldConfigurations = $this->findCustomFieldConfigurationByCriteria($sourceEntity, $sourceEntityId, $targetEntity);

        if (empty($customFieldConfigurations)) {
            return new ArrayCollection();
        }

        return new ArrayCollection(
            array_map(
                static function (CustomFieldConfiguration $customFieldConfiguration): CustomFieldInterface {
                    $customField = $customFieldConfiguration->getConfiguration();
                    $customField->setId($customFieldConfiguration->getId());
                    return $customField;
                },
                $customFieldConfigurations
            )
        );
    }
}
