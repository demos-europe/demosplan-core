<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementFileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity()
 */
class DraftStatementFile implements UuidEntityInterface, DraftStatementFileInterface
{
    /**
     * @var string
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     */
    private $id;

    /**
     * Temporary null value required for orphan removal.
     *
     * @var DraftStatementInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement", inversedBy="files")
     *
     * @ORM\JoinColumn(referencedColumnName="_ds_id", nullable=false)
     */
    private $draftStatement;

    /**
     * @var DateTimeInterface
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $createDate;

    /**
     * @var FileInterface
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="_f_ident", nullable=false)
     */
    private $file;

    public function getId(): string
    {
        return $this->id;
    }

    public function getDraftStatement(): ?DraftStatementInterface
    {
        return $this->draftStatement;
    }

    /**
     * Set to null to activate orphan removal.
     */
    public function setDraftStatement(?DraftStatementInterface $draftStatement): self
    {
        $this->draftStatement = $draftStatement;

        return $this;
    }

    public function getCreateDate(): DateTimeInterface
    {
        return $this->createDate;
    }

    public function getFile(): FileInterface
    {
        return $this->file;
    }

    public function setFile(FileInterface $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getFileString(): ?string
    {
        if ($this->file instanceof FileInterface) {
            return $this->file->getFileString();
        }

        return null;
    }
}
