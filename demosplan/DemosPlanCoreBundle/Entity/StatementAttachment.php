<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use demosplan\DemosPlanCoreBundle\Repository\StatementAttachmentRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\IsValidStatementAttachmentType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Some kind of file connected to a {@link StatementInterface} or original {@link StatementInterface}.
 */
#[ORM\Entity(repositoryClass: StatementAttachmentRepository::class)]
class StatementAttachment implements UuidEntityInterface, StatementAttachmentInterface
{
    /**
     * @var string|null
     *
     *
     *
     *
     */
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected $id;

    /**
     * @var FileInterface
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: '_f_ident', nullable: false)]
    #[ORM\ManyToOne(targetEntity: File::class)]
    protected $file;

    /**
     * @var string
     *
     *
     * @IsValidStatementAttachmentType()
     */
    #[ORM\Column(type: 'string', nullable: false)]
    protected $type;

    /**
     * @var StatementInterface
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: '_st_id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Statement::class, inversedBy: 'attachments')]
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
