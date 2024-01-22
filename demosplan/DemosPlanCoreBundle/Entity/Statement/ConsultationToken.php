<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ConsultationTokenInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\ConsistentOriginalStatementConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\IsNotOriginalStatementConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\IsOriginalStatementConstraint;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Repository\ConsultationTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Allows users knowing a valid token to submit statements during a special
 * {@link * Procedure::$phase}, in which submitting statements is otherwise disabled.
 *
 * Tokens will be created when a statement is submitted during a {@link Procedure::$phase} in which
 * users are allowed to submit statements without full login, ie. without the need to provide
 * a {@link ConsultationToken::token token} during the authorization. Beside the two usual ways
 * (via the manual statement form and the public detail page) statements are now also
 * automatically created when a new entry is added in the {@link ConsultationToken} list page.
 * When adding an entry there the user can enter a note for the token but also some basic statement
 * header information (name of the submitter and her/his address/email address). From those
 * information a manual statement is created automatically, for which the token will be
 * created.
 *
 * @ORM\Entity(repositoryClass=ConsultationTokenRepository::class)
 *
 * @ORM\Table(uniqueConstraints={
 *
 *        @ORM\UniqueConstraint(name="unique_consultation_token", columns={"token"})
 * })
 *
 * @ConsistentOriginalStatementConstraint
 */
class ConsultationToken implements UuidEntityInterface, ConsultationTokenInterface
{
    /**
     * The value of this property should be considered final.
     *
     * @var string|null `null` if this instance was not persisted yet
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    private $id;

    /**
     * For each token a note can be stored. It is manually entered by the user accessing
     * the token list.
     *
     * It has informative character only. An example for possible content may be an address.
     * The content is not restricted in any way.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=1024, nullable=false)
     */
    #[Assert\NotNull]
    private $note = '';

    /**
     * The connection to the original statement the token was created for.
     *
     * The value of this property should be considered final.
     *
     * By storing the reference to the original statement we can still determine the source
     * of the token even if the {@link ConsultationToken::$statement} in the assessment table
     * is deleted. This property is currently not used, but may become vital in the future to
     * handle {@link ConsultationToken::$statement} being `null`.
     *
     * @var Statement the original statement of the {@link ConsultationToken::$statement}, as
     *                original statements can not be deleted this property will never become `null`
     *
     * @IsOriginalStatementConstraint
     *
     * @ORM\OneToOne(targetEntity=Statement::class)
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=false)
     */
    #[Assert\NotBlank]
    private $originalStatement;

    /**
     * The email that was used to sent this token. `null` if it was not sent yet via email (to our
     * knowledge).
     *
     * @var MailSend|null
     *
     * @ORM\OneToOne(targetEntity=MailSend::class)
     *
     * @ORM\JoinColumn(referencedColumnName="_ms_id", nullable=true)
     */
    private $sentEmail;

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
     * The connection to the statement the token was created for.
     *
     * The value of this property should be considered final.
     *
     * We need the reference to the {@link Statement} in the assessment table to provide
     * the user with a link to its detail view.
     *
     * @var Statement|null the source statement or `null` if the statement was deleted
     *
     * @IsNotOriginalStatementConstraint
     *
     * @ORM\OneToOne(targetEntity=Statement::class)
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id", nullable=true)
     */
    private ?Statement $statement;

    public function __construct(
        /**
         * The human readable token given to users to submit statements during the special
         * {@link Procedure::$phase}.
         *
         * The value of this property should be considered final.
         *
         * @ORM\Column(type="string", length=8, nullable=false)
         */
        #[Assert\NotBlank]
        #[Assert\Regex('/^\w{8}$/')]
        private string $token,
        Statement $statement,
        /**
         * Determines if this token entry was created manually by the user in the UI, in which
         * case it is `true`. The alternative would be that this instance was created automatically
         * via an {@link DPlanEvent} when a statement was submitted.
         *
         * The value of this property should be considered final.
         *
         * @ORM\Column(type="boolean", nullable=false, options={"default":false})
         */
        private bool $manuallyCreated
    ) {
        $this->statement = $statement;
        $this->originalStatement = $statement->getOriginal();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): ConsultationToken
    {
        $this->note = $note;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getStatement(): ?Statement
    {
        return $this->statement;
    }

    public function getOriginalStatement(): Statement
    {
        return $this->originalStatement;
    }

    public function isManuallyCreated(): bool
    {
        return $this->manuallyCreated;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function getSentEmail(): ?MailSend
    {
        return $this->sentEmail;
    }

    public function setSentEmail(MailSend $sentEmail): ConsultationToken
    {
        $this->sentEmail = $sentEmail;

        return $this;
    }
}
