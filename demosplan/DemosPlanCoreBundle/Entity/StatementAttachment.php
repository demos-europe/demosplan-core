<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\IsValidStatementAttachmentType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Some kind of file connected to a {@link StatementInterface} or original {@link StatementInterface}.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementAttachmentRepository")
 */
class StatementAttachment implements UuidEntityInterface, StatementAttachmentInterface
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
     * @var FileInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File")
     *
     * @ORM\JoinColumn(referencedColumnName="_f_ident", nullable=false)
     */
    protected $file;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     *
     * @IsValidStatementAttachmentType()
     */
    protected $type;

    /**
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="attachments")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=false)
     */
    protected $statement;

    public function getFile(): FileInterface
    {
        return $this->file;
    }

    public function setFile(FileInterface $file): void
    {
        $this->file = $file;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function setStatement(StatementInterface $statement): void
    {
        $this->statement = $statement;
    }
}
