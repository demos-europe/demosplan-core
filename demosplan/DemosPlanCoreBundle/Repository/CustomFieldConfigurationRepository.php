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

    public function findCustomFieldConfigurationByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity): ?CustomFieldConfiguration
    {
        try {
            $criteria = ['templateEntityId' => $sourceEntityId];
            $criteria['templateEntityClass'] = $sourceEntity;
            $criteria['valueEntityClass'] = $targetEntity;

            return $this->findOneBy($criteria);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration: '.$e->getMessage());

            return null;
        }
    }

    public function findOrCreateCustomFieldConfigurationByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity): CustomFieldConfiguration
    {
        $customFieldConfiguration = $this->findCustomFieldConfigurationByCriteria($sourceEntity, $sourceEntityId, $targetEntity);

        if (null !== $customFieldConfiguration) {
            return $customFieldConfiguration;
        }

        return $this->createCustomFieldConfiguration($sourceEntity, $sourceEntityId, $targetEntity);
    }

    public function findCustomFieldConfigurationById(string $id): ?CustomFieldInterface
    {
        try {
            return $this->find($id);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration by ID: '.$e->getMessage());

            return null;
        }
    }

    private function createCustomFieldConfiguration(string $sourceEntity, string $sourceEntityId, string $targetEntity): CustomFieldConfiguration
    {
        $customFieldConfiguration = new CustomFieldConfiguration();

        $customFieldConfiguration->setTemplateEntityClass($sourceEntity);
        $customFieldConfiguration->setTemplateEntityId($sourceEntityId);

        $customFieldConfiguration->setValueEntityClass($targetEntity);
        $customFieldsList = new CustomFieldList();
        $customFieldsList->setName('DefaultName');
        $customFieldsList->setCustomFields([]);
        $customFieldConfiguration->setConfiguration($customFieldsList);
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
        $sourceCustomFields = $this->findCustomFieldConfigurationByCriteria( 'PROCEDURE_TEMPLATE', $sourceProcedureId, 'SEGMENT');

        if (null !== $sourceCustomFields) {
            $newCustomFieldsConfiguration = clone $sourceCustomFields;
            $newCustomFieldsConfiguration->setId(null);
            $newCustomFieldsConfiguration->setTemplateEntityId($newProcedure->getId());
            $newCustomFieldsConfiguration->setTemplateEntityClass('PROCEDURE');
            $newCustomFieldsConfiguration->setCreateDate(null);
            $newCustomFieldsConfiguration->setModifyDate(null);
            $newCustomFieldsConfiguration->setConfiguration($sourceCustomFields->getConfiguration()->toJson());

            $this->add($newCustomFieldsConfiguration);
        }
    }
}
