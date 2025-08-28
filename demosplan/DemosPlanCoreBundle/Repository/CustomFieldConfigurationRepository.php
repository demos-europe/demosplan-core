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

    public function findCustomFieldConfigurationByCriteria(
        string $sourceEntity,
        string $sourceEntityId,
        string $targetEntity,
        ?string $customFieldId = null,
    ): ?array {
        try {
            $criteria = ['sourceEntityId' => $sourceEntityId];
            $criteria['sourceEntityClass'] = $sourceEntity;
            $criteria['targetEntityClass'] = $targetEntity;

            if ($customFieldId) {
                $criteria['id'] = $customFieldId;
            }

            return $this->findBy($criteria);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration: '.$e->getMessage());

            return null;
        }
    }

    public function createCustomFieldConfiguration(
        string $sourceEntity,
        string $sourceEntityId,
        string $targetEntity,
        CustomFieldInterface $customField,
    ): CustomFieldConfiguration {
        $customFieldConfiguration = new CustomFieldConfiguration();

        $customFieldConfiguration->setSourceEntityClass($sourceEntity);
        $customFieldConfiguration->setSourceEntityId($sourceEntityId);

        $customFieldConfiguration->setTargetEntityClass($targetEntity);
        $customFieldConfiguration->setConfiguration($customField);
        $this->add($customFieldConfiguration);

        return $customFieldConfiguration;
    }

    public function updateObject(CustomFieldConfiguration $entity): CustomFieldConfiguration
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
        $customFieldsConfigurations = $this->findCustomFieldConfigurationByCriteria(
            'PROCEDURE_TEMPLATE',
            $sourceProcedureId,
            'SEGMENT'
        );

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

    /**
     * @throws Exception
     */
    public function deleteObject(CustomFieldConfiguration $customFieldConfiguration): bool
    {
        try {
            $this->getEntityManager()->remove($customFieldConfiguration);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete CustomFieldConfiguration failed: ', [$e]);
            throw $e;
        }
    }
}
