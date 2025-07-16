<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\OriginalStatementAnonymizationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\IsOriginalStatementConstraint;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OriginalStatementAnonymizationRepository")
 */
class OriginalStatementAnonymization implements OriginalStatementAnonymizationInterface, UuidEntityInterface
{
    /**
     * @var string
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
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="anonymizations")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=false)
     *
     * @IsOriginalStatementConstraint()
     */
    protected $statement;

    /**
     * @var UserInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=false)
     */
    protected $createdBy;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $attachmentsDeleted;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $textVersionHistoryDeleted;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $textPassagesAnonymized;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $submitterAndAuthorMetaDataAnonymized;

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function isAttachmentsDeleted(): bool
    {
        return $this->attachmentsDeleted;
    }

    public function setAttachmentsDeleted(bool $attachmentsDeleted): void
    {
        $this->attachmentsDeleted = $attachmentsDeleted;
    }

    public function isTextVersionHistoryDeleted(): bool
    {
        return $this->textVersionHistoryDeleted;
    }

    public function setTextVersionHistoryDeleted(bool $textVersionHistoryDeleted): void
    {
        $this->textVersionHistoryDeleted = $textVersionHistoryDeleted;
    }

    public function isTextPassagesAnonymized(): bool
    {
        return $this->textPassagesAnonymized;
    }

    public function setTextPassagesAnonymized(bool $textPassagesAnonymized): void
    {
        $this->textPassagesAnonymized = $textPassagesAnonymized;
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function setStatement(StatementInterface $statement): void
    {
        $this->statement = $statement;
    }

    public function getCreatedBy(): UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(UserInterface $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function isSubmitterAndAuthorMetaDataAnonymized(): bool
    {
        return $this->submitterAndAuthorMetaDataAnonymized;
    }

    public function setSubmitterAndAuthorMetaDataAnonymized(bool $submitterAndAuthorMetaDataAnonymized): void
    {
        $this->submitterAndAuthorMetaDataAnonymized = $submitterAndAuthorMetaDataAnonymized;
    }
}
