<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\IsValidStatementAttachmentType;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\ORM\Mapping as ORM;

/**
 * Some kind of file connected to a {@link Statement} or original {@link Statement}.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementAttachmentRepository")
 */
class StatementAttachment implements UuidEntityInterface, StatementAttachmentInterface
{
    /**
     * A file that originally resulted in the original statement being created. E.g. a PDF file
     * send via email.
     *
     * @var string
     */
    public const SOURCE_STATEMENT = 'source_statement';
    /**
     * Attachments with this type can have any kind of content. We can only speculate that it
     * may be legal documents from lawyers or reviewers, images or other files supporting the
     * statement.
     *
     * @var string
     */
    public const GENERIC = 'generic';

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
     * @var File
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
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="attachments")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=false)
     */
    protected $statement;

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): void
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

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function setStatement(Statement $statement): void
    {
        $this->statement = $statement;
    }
}
