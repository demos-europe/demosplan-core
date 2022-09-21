<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf;

use DateTime;
use demosplan\DemosPlanCoreBundle\Constraint\NonEmptyAnnotatedStatementPdfConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Faker\Provider\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Status tracking for imported statements processed by demospip.es.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf\AnnotatedStatementPdfRepository")
 *
 * @NonEmptyAnnotatedStatementPdfConstraint()
 */
class AnnotatedStatementPdf extends CoreEntity implements UuidEntityInterface
{
    public const PENDING = 'pending';
    public const READY_TO_REVIEW = 'ready_to_review';
    public const REVIEWED = 'reviewed';
    public const BOX_REVIEW = 'boxes_review';
    public const READY_TO_CONVERT = 'ready_to_convert';
    public const TEXT_REVIEW = 'text_review';
    public const CONVERTED = 'converted';

    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, nullable=false, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var Collection<int,AnnotatedStatementPdfPage>
     *
     * @ORM\OneToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdfPage",
     *     mappedBy="annotatedStatementPdf",
     *     cascade={"persist", "remove"}
     * )
     * @ORM\OrderBy({"pageOrder" = "ASC"})
     *
     * @Assert\NotNull()
     */
    private $annotatedStatementPdfPages;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", cascade={"persist"}, inversedBy="annotatedStatementPdfs")
     * @ORM\JoinColumn(name="_procedure", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     *
     * @Assert\NotNull()
     */
    private $procedure;

    /**
     * @var Statement|null
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     * @ORM\JoinColumn(name="_statement", referencedColumnName="_st_id", nullable=true)
     */
    private $statement;

    /**
     * @var File
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File")
     * @ORM\JoinColumn(name="file", referencedColumnName="_f_ident")
     *
     * @Assert\NotNull()
     */
    private $file;

    /**
     * @var Collection<int, File>
     */
    private $statementAttachments;

    /**
     * @var string
     *
     * @ORM\Column(name="statement_text", type="text", nullable=true, length=15000000)
     */
    private $statementText;

    /**
     * @var string|null
     *
     * @ORM\Column(name="pi_resource_url", type="string", length=255, nullable=true)
     */
    private $piResourceUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="text", nullable=false, options={"default":"pending"})
     */
    private $status;

    /**
     * @var string|null
     *
     * @ORM\Column(name="submitter_json", type="string", length=1024, nullable=true)
     */
    private $submitterJson;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default": "0"})
     */
    private $boxRecognitionPiRetries;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default": "0"})
     */
    private $textRecognitionPiRetries;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="reviewed_date", type="datetime", nullable=true)
     */
    protected $reviewedDate;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(referencedColumnName="_u_id", onDelete="RESTRICT")
     * })
     */
    protected $reviewer;

    public function __construct()
    {
        $this->boxRecognitionPiRetries = 0;
        $this->textRecognitionPiRetries = 0;
        $this->id = Uuid::uuid();
        $this->annotatedStatementPdfPages = new ArrayCollection();
        $this->statementAttachments = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Collection<int,AnnotatedStatementPdfPage>
     */
    public function getAnnotatedStatementPdfPages(): Collection
    {
        return $this->annotatedStatementPdfPages;
    }

    /**
     * @param Collection<int,AnnotatedStatementPdfPage> $annotatedStatementPdfPages
     */
    public function setAnnotatedStatementPdfPages(Collection $annotatedStatementPdfPages): void
    {
        $this->annotatedStatementPdfPages = $annotatedStatementPdfPages;
    }

    public function addAnnotatedStatementPdfPages(
        AnnotatedStatementPdfPage $annotatedStatementPdfPage): void
    {
        if (null === $this->annotatedStatementPdfPages) {
            $this->annotatedStatementPdfPages = new ArrayCollection();
        }
        $this->annotatedStatementPdfPages->add($annotatedStatementPdfPage);
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getProcedureId(): string
    {
        return $this->procedure->getId();
    }

    public function setProcedure(Procedure $procedure): void
    {
        $this->procedure = $procedure;
    }

    public function getStatement(): ?Statement
    {
        return $this->statement;
    }

    public function setStatement(?Statement $statement): void
    {
        $this->statement = $statement;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
    }

    /**
     * @return string
     */
    public function getStatementText(): ?string
    {
        return $this->statementText;
    }

    public function setStatementText(string $statementText): void
    {
        $this->statementText = $statementText;
    }

    /**
     * Returns true if all pages in the document are reviewed
     * This can happen at different stages in the workflow so, if tue, it can not be taken
     * for granted that the status will be reviewed.
     */
    public function allPagesReviewed(): bool
    {
        foreach ($this->getAnnotatedStatementPdfPages() as $annotatedStatementPdfPage) {
            /** @var AnnotatedStatementPdfPage $annotatedStatementPdfPage */
            if (!$annotatedStatementPdfPage->isConfirmed()) {
                return false;
            }
        }

        return true;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getPiResourceUrl(): ?string
    {
        return $this->piResourceUrl;
    }

    public function setPiResourceUrl(?string $piResourceUrl): void
    {
        $this->piResourceUrl = $piResourceUrl;
    }

    public function getSubmitterJson(): ?string
    {
        return '' === $this->submitterJson ? '{}' : $this->submitterJson;
    }

    public function setSubmitterJson(?string $submitterJson): void
    {
        $this->submitterJson = $submitterJson;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }

    public function getReviewedDate(): ?DateTime
    {
        return $this->reviewedDate;
    }

    public function setReviewedDate(DateTime $reviewedDate): void
    {
        $this->reviewedDate = $reviewedDate;
    }

    public function getBoxRecognitionPiRetries(): int
    {
        return $this->boxRecognitionPiRetries;
    }

    public function setBoxRecognitionPiRetries(int $boxRecognitionPiRetries): void
    {
        $this->boxRecognitionPiRetries = $boxRecognitionPiRetries;
    }

    public function incrementBoxRecognitionPiRetries(): void
    {
        ++$this->boxRecognitionPiRetries;
    }

    public function getTextRecognitionPiRetries(): int
    {
        return $this->textRecognitionPiRetries;
    }

    public function setTextRecognitionPiRetries(int $textRecognitionPiRetries): void
    {
        $this->textRecognitionPiRetries = $textRecognitionPiRetries;
    }

    public function incrementTextRecognitionPiRetries(): void
    {
        ++$this->textRecognitionPiRetries;
    }

    public function getDocumentName(): string
    {
        return $this->getFile()->getName();
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(?User $reviewer): void
    {
        $this->reviewer = $reviewer;
    }
}
