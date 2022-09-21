<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement\StatementImportEmail;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Planners may receive statements as emails and want to redirect them into the system. When they
 * do a corresponding instance of this class is created.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\StatementImportEmailRepository")
 *
 * // TODO: validate that this instance and all AnnotatedStatementPdf instances reference the same procedure.
 * // TODO: some of the properties can potentially separated into a generic Email or SentEmail entity (the existing MailSend doesn't seem to be a good fit)
 */
class StatementImportEmail implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * The date and time the instance of this entity class was created in the database.
     *
     * @var DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * The procedure this email was redirected into.
     *
     * @var Procedure
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     * @ORM\JoinColumn(referencedColumnName="_p_id")
     *
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Type(type=Procedure::class)
     */
    private $procedure;

    /**
     * The **original** statements created from this e-mail.
     *
     * @var Collection<int, Statement>
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     * @ORM\JoinTable(name="statement_import_email_original_statements",
     *      joinColumns={@ORM\JoinColumn(name="statement_import_email_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="original_statement_id", referencedColumnName="_st_id", unique=true)}
     * )
     */
    private $createdStatements;

    // #region <redirect-email>

    /**
     * The user with the email address that was used to redirect this email into the system.
     *
     * @var User|null
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=true)
     *
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Type(type=User::class)
     */
    private $forwardingUser;

    /**
     * The original `From` header field of the email.
     * Can be used in case there is no forwarding user.
     *
     * @var string
     * @ORM\Column(name="from_address", type="string", length=512, nullable=false)
     *
     * @Assert\NotBlank()
     */
    private $from;

    /**
     * The subject loaded from the headers of the redirect-email.
     *
     * There is no formal limit to the length of the subject.
     *
     * @var string
     * @ORM\Column(type="text", nullable=false)
     *
     * @Assert\NotNull()
     */
    private $subject;

    /**
     * The plain text content of the email that was sent into the system. I.e. from the email
     * resulting from forwarding the original email.
     *
     * @var string
     * @ORM\Column(name="body", type="text", nullable=false)
     *
     * @Assert\NotNull()
     */
    private $plainTextContent;

    /**
     * The HTML text content of the email that was sent into the system. I.e. from the email
     * resulting from forwarding the original email.
     *
     * @var string
     * @ORM\Column(name="html_text_content", type="text", nullable=false)
     *
     * @Assert\NotNull()
     */
    private $htmlTextContent;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=false)
     *
     * @Assert\NotBlank(allowNull=false)
     */
    private $rawEmailText;

    // #endregion <redirect-email>

    // #region <attachments>

    /**
     * The attachments received with this email. Usually contains at least one or multiple PDF file
     * attachments, containing one or multiple statements to be imported. However, this is not
     * mandatory, as the statement text may be present in the
     * {@link StatementImportEmail::$plainTextContent} as well. Additional attachments files may be
     * the following.
     *
     * * files that should be added as {@link StatementAttachment} to the statement
     * * files unrelated to the statement, e.g. logo images used in the email
     *
     * @var Collection<int, File>
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File")
     * @ORM\JoinTable(name="statement_import_email_attachments",
     *      joinColumns={@ORM\JoinColumn(name="statement_import_email_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="file_id", referencedColumnName="_f_ident", unique=true)}
     * )
     */
    private $attachments;

    /**
     * The attachments of this email that are currently being processed by the PDF importer or have
     * been finished processing.
     *
     * @var Collection<int, AnnotatedStatementPdf>
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf")
     * @ORM\JoinTable(name="statement_import_email_processed_attachments",
     *      joinColumns={@ORM\JoinColumn(name="statement_import_email_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="annotated_statement_pdf_id", referencedColumnName="id", unique=true)}
     * )
     */
    private $processedAttachments;

    // #endregion <attachments>

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->processedAttachments = new ArrayCollection();
        $this->createdStatements = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setProcedure(Procedure $procedure): StatementImportEmail
    {
        $this->procedure = $procedure;

        return $this;
    }

    /**
     * @param Collection<int, Statement> $createdStatements
     */
    public function setCreatedStatements(Collection $createdStatements): StatementImportEmail
    {
        $this->createdStatements = $createdStatements;

        return $this;
    }

    /**
     * @return Collection<int, Statement>
     */
    public function getCreatedStatements(): Collection
    {
        return $this->createdStatements;
    }

    public function setForwardingUser(?User $forwardingUser): StatementImportEmail
    {
        $this->forwardingUser = $forwardingUser;

        return $this;
    }

    public function setSubject(string $subject): StatementImportEmail
    {
        $this->subject = $subject;

        return $this;
    }

    public function setPlainTextContent(string $plainTextContent): StatementImportEmail
    {
        $this->plainTextContent = $plainTextContent;

        return $this;
    }

    public function setRawEmailText(string $rawEmailText): StatementImportEmail
    {
        $this->rawEmailText = $rawEmailText;

        return $this;
    }

    public function setHtmlTextContent(string $htmlTextContent): StatementImportEmail
    {
        $this->htmlTextContent = $htmlTextContent;

        return $this;
    }

    public function setAttachments($attachments): StatementImportEmail
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function setProcessedAttachments($processedAttachments): StatementImportEmail
    {
        $this->processedAttachments = $processedAttachments;

        return $this;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getPlainTextContent(): string
    {
        return $this->plainTextContent;
    }

    public function getHtmlTextContent(): string
    {
        return $this->htmlTextContent;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * @param string $from
     */
    public function setFrom(string $from): void
    {
        $this->from = $from;
    }
}
