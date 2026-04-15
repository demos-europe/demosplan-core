<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use demosplan\DemosPlanCoreBundle\Repository\OriginalStatementAnonymizationRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\OriginalStatementAnonymizationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\IsOriginalStatementConstraint;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: OriginalStatementAnonymizationRepository::class)]
class OriginalStatementAnonymization implements OriginalStatementAnonymizationInterface, UuidEntityInterface
{
    /**
     * @var string
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
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    protected $created;

    /**
     * @var StatementInterface
     *
     *
     *
     * @IsOriginalStatementConstraint()
     */
    #[ORM\JoinColumn(referencedColumnName: '_st_id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Statement::class, inversedBy: 'anonymizations')]
    protected $statement;

    /**
     * @var UserInterface
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: '_u_id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $createdBy;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    protected $attachmentsDeleted;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    protected $textVersionHistoryDeleted;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    protected $textPassagesAnonymized;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
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
