<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Instances of this class represent addons, that have not only been installed (their code is
 * present in the `vendor` folder) but that have also been activated (their code is loaded and
 * affects the application).
 *
 * This entity should not be used to duplicate addon information into the database, that can
 * be determined from its `composer.json` and similar files (e.g. the version in which the composer
 * package is present). Instead, it can be used to store additional information and assign a UUID
 * to it.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DplanAddonRepository")
 */
class DplanAddon implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * The unique composer package name of this addon.
     *
     * @var non-empty-string
     *
     * @ORM\Column(type="string", length=128, nullable=true, unique=true)
     *
     * @Assert\NotBlank(normalizer="trim", allowNull=false)
     * @Assert\Length(max=128)
     * @Assert\Regex(pattern="/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$/")
     *
     * @see https://getcomposer.org/doc/04-schema.md#name
     */
    private $packageName;

    /**
     * @param non-empty-string $packageName
     */
    public function __construct(string $packageName)
    {
        $this->packageName;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
