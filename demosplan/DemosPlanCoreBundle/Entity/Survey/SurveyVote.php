<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Survey;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use UnexpectedValueException;

/**
 * @see https://yaits.demos-deutschland.de/w/demosplan/functions/survey/ Wiki: Survey
 *
 * @ORM\Table(name="survey_vote")
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanSurveyBundle\Repository\SurveyVoteRepository")
 */
class SurveyVote extends CoreEntity implements UuidEntityInterface
{
    public const PUBLICATION_PENDING = 'publication_pending';
    public const PUBLICATION_REJECTED = 'publication_rejected';
    public const PUBLICATION_APPROVED = 'publication_approved';

    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * True is a positive vote for the Survey question.
     *
     * False is a negative vote for the Survey question.
     *
     * @var bool
     *
     * @ORM\Column(name="is_agreed", type="boolean", nullable=false)
     */
    protected $isAgreed = false;

    /**
     * Explanation on the positive/negative vote.
     *
     * @var string
     *
     * @ORM\Column(name="text", type="text", length=400, nullable=true)
     */
    protected $text = '';

    /**
     * Action by the planner, no artificial logic involved.
     *
     * @var string
     *
     * @ORM\Column(name="text_review", type="string", nullable=false)
     */
    protected $textReview = self::PUBLICATION_PENDING;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_date", type="date", nullable=false)
     */
    protected $createdDate;

    /**
     * @var Survey
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Survey\Survey",
     *     cascade={"persist"}, inversedBy="votes")
     * @ORM\JoinColumn(name="survey_id", referencedColumnName="id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $survey;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User",
     *     cascade={"persist"}, inversedBy="surveyVotes")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $user;

    /**
     * @throws Exception
     */
    public function __construct(bool $isAgreed, string $text, Survey $survey, User $user)
    {
        $this->createdDate = new DateTime();
        $this->isAgreed = $isAgreed;
        $this->survey = $survey;
        $this->text = $text;
        $this->user = $user;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function isAgreed(): bool
    {
        return $this->isAgreed;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTextReview(): string
    {
        return $this->textReview;
    }

    public function setTextReview(string $textReview): void
    {
        if (!in_array($textReview, self::getTextReviewAllowedValues(), true)) {
            throw new UnexpectedValueException(sprintf('Tried to set field $textReview with value: "%s"', $textReview));
        }

        $this->textReview = $textReview;
    }

    /**
     * Has text and the text is approved.
     */
    public function hasApprovedText(): bool
    {
        return $this->hasText() && self::PUBLICATION_APPROVED === $this->getTextReview();
    }

    /**
     * A review is required if a) it has not yet happened and b) there is a text to review.
     */
    public function isReviewRequired(): bool
    {
        return $this->hasText() && self::PUBLICATION_PENDING === $this->getTextReview();
    }

    public function hasText(): bool
    {
        return '' !== $this->getText();
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    public function getSurvey(): Survey
    {
        return $this->survey;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public static function getTextReviewAllowedValues(): array
    {
        return [
            self::PUBLICATION_PENDING,
            self::PUBLICATION_APPROVED,
            self::PUBLICATION_REJECTED,
        ];
    }
}
