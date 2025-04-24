<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\CustomFields;

use DateTime;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository")
 */
class CustomFieldConfiguration extends CoreEntity
{
    /**
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected string $id;

    /**
     *
     * @ORM\Column(name="source_entity_id", type="string", length=36, nullable=false)
     */
    protected string $sourceEntityId;

    /**
     *
     * @ORM\Column(name="source_entity_class", type="string", nullable=false)
     */
    protected string $sourceEntityClass;

    /**
     *
     * @ORM\Column(name="target_entity_class", type="string", nullable=false)
     */
    protected string $targetEntityClass;

    /**
     *
     * @ORM\Column(type="dplan.custom_field_configuration", nullable=true)
     */
    protected CustomFieldInterface $configuration;

    /**
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $createDate;

    /**
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $modifyDate;

    public function getConfiguration(): CustomFieldInterface
    {
        return $this->configuration;
    }

    public function setConfiguration($configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getSourceEntityId(): string
    {
        return $this->sourceEntityId;
    }

    public function setSourceEntityId(string $templateEntityId): void
    {
        $this->sourceEntityId = $templateEntityId;
    }

    public function getSourceEntityClass(): string
    {
        return $this->sourceEntityClass;
    }

    public function setSourceEntityClass(string $sourceEntityClass): void
    {
        $this->sourceEntityClass = $sourceEntityClass;
    }

    public function getTargetEntityClass(): string
    {
        return $this->targetEntityClass;
    }

    public function setTargetEntityClass(string $targetEntityClass): void
    {
        $this->targetEntityClass = $targetEntityClass;
    }
    
    public function setId($id): void
    {
        $this->id = $id;
    }

    public function setCreateDate($createDate): void
    {
        $this->createDate = $createDate;
    }


    public function setModifyDate($modifyDate): void
    {
        $this->modifyDate = $modifyDate;
    }

    public function getId(): string
    {
        return $this->id;
    }


}
