<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use demosplan\DemosPlanCoreBundle\Repository\BrandingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BrandingRepository::class)
 */
class Branding extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * For future reference: null is for all intents and purposes equivalent to ''.
     * Both mean that there is no set of cssvars.
     *
     * @var ?string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $cssvars;

    /**
     * @var File|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File")
     * @ORM\JoinColumn(name="logo", referencedColumnName="_f_ident", nullable=true, onDelete="CASCADE")
     */
    protected $logo;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCssvars(): ?string
    {
        return $this->cssvars;
    }

    public function setCssvars(?string $cssVars): self
    {
        $this->cssvars = $cssVars;

        return $this;
    }

    public function getLogo(): ?File
    {
        return $this->logo;
    }

    public function setLogo(?File $logo): void
    {
        $this->logo = $logo;
    }
}
