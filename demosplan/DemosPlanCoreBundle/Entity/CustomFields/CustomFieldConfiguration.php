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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\NotifyPropertyChanged;
use Doctrine\Persistence\PropertyChangedListener;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository")
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 */
class CustomFieldConfiguration extends CoreEntity
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="template_entity_id", type="string", length=36, nullable=false)
     */
    protected $templateEntityId;

    /**
     * @var string
     *
     * @ORM\Column(name="template_entity_class", type="string", nullable=false)
     */
    protected $templateEntityClass;

    /**
     * @var string
     *
     * @ORM\Column(name="value_entity_class", type="string", nullable=false)
     */
    protected $valueEntityClass;

    /**
     * @var CustomFieldList
     *
     * @ORM\Column(type="dplan.custom_fields_template", nullable=true)
     */
    protected $configuration;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $modifyDate;


    public function getConfiguration(): ?CustomFieldList
    {
        return $this->configuration;
    }

    public function setConfiguration(CustomFieldList $configuration): void
    {
        $this->configuration = $configuration;
    }


}
