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
use Exception;
use Ramsey\Uuid\Uuid;
use UAParser\Exception\InvalidArgumentException;

class CustomFieldConfigurationRepository extends CoreRepository
{
    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(CustomFieldConfiguration $customFieldConfiguration): CustomFieldConfiguration
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

    public function getCustomFieldConfigurationByProcedureId(string $sourceEntity, string $procedureId, string $targetEntity): ?CustomFieldConfiguration
    {
        try {
            $criteria = ['templateEntityId' => $procedureId];
            $criteria['templateEntityClass'] = $sourceEntity;
            $criteria['valueEntityClass'] = $targetEntity;

            return $this->findOneBy($criteria);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration: '.$e->getMessage());

            return null;
        }
    }

    public function detectCustomFieldConfigurationByProcedureId(string $sourceEntity, string $procedureId, string $targetEntity): CustomFieldConfiguration
    {
        $customFieldConfiguration = $this->getCustomFieldConfigurationByProcedureId($sourceEntity, $procedureId, $targetEntity);

        if (null === $customFieldConfiguration) {
            // if it does not exist, create new entry

            $customFieldConfiguration = new CustomFieldConfiguration();

            $customFieldConfiguration->setTemplateEntityClass($sourceEntity);
            $customFieldConfiguration->setTemplateEntityId($procedureId);

            $customFieldConfiguration->setValueEntityClass($targetEntity);
            $customFieldsList = new CustomFieldList();
            $customFieldsList->setName('DefaultName');
            $customFieldsList->setCustomFields([]);
            $customFieldConfiguration->setConfiguration($customFieldsList);
            $this->add($customFieldConfiguration);

            return $customFieldConfiguration;
        }

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

    public function createCustomField($attributes): CustomFieldInterface
    {
        /** @var CustomFieldConfiguration $customFieldConfiguration */
        $customFieldConfiguration = $this->detectCustomFieldConfigurationByProcedureId($attributes['sourceEntity'], $attributes['sourceEntityId'], $attributes['targetEntity']);

        /** @var CustomFieldInterface $particularCustomField */
        $particularCustomField = $this->createParticularCustomField($attributes);

        $customFieldConfiguration->addCustomFieldToCustomFieldList($particularCustomField);

        $this->updateObject($customFieldConfiguration);

        return $particularCustomField;
    }

    private function createParticularCustomField($attributes): CustomFieldInterface
    {
        $type = $attributes['fieldType'];
        if (!isset(CustomFieldList::TYPE_CLASSES[$type])) {
            throw new InvalidArgumentException('Unknown custom field type: '.$type);
        }
        $customFieldClass = CustomFieldList::TYPE_CLASSES[$type];
        $customField = new $customFieldClass();

        $customField->setId(Uuid::uuid4()->toString());
        $customField->setFieldType($type);
        $customField->setName($attributes['name']);
        $customField->setDescription($attributes['description']);

        if (isset($attributes['options']) && method_exists($customField, 'setOptions')) {
            $customField->setOptions($attributes['options']);
        }

        return $customField;
    }
}
