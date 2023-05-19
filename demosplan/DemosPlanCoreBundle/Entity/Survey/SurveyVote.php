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
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use UnexpectedValueException;

/**
 * @see https://yaits.demos-deutschland.de/w/demosplan/functions/survey/ Wiki: Survey
 *
 * @ORM\Table(name="survey_vote")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SurveyVoteRepository")
 */
class SurveyVote extends CoreEntity implements UuidEntityInterface, SurveyVoteInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
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
    protected $textReview = SurveyVoteInterface::PUBLICATION_PENDING;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_date", type="date", nullable=false)
     */
    protected $createdDate;

    /**
     * @var SurveyInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Survey\Survey",
     *     cascade={"persist"}, inversedBy="votes")
     *
     * @ORM\JoinColumn(name="survey_id", referencedColumnName="id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $survey;

    /**
     * @var UserInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User",
     *     cascade={"persist"}, inversedBy="surveyVotes")
     *
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $user;

    /**
     * @throws Exception
     */
    public function __construct(bool $isAgreed, string $text, SurveyInterface $survey, UserInterface $user)
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
        return $this->hasText() && SurveyVoteInterface::PUBLICATION_APPROVED === $this->getTextReview();
    }

    /**
     * A review is required if a) it has not yet happened and b) there is a text to review.
     */
    public function isReviewRequired(): bool
    {
        return $this->hasText() && SurveyVoteInterface::PUBLICATION_PENDING === $this->getTextReview();
    }

    public function hasText(): bool
    {
        return '' !== $this->getText();
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    public function getSurvey(): SurveyInterface
    {
        return $this->survey;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public static function getTextReviewAllowedValues(): array
    {
        return [
            SurveyVoteInterface::PUBLICATION_PENDING,
            SurveyVoteInterface::PUBLICATION_APPROVED,
            SurveyVoteInterface::PUBLICATION_REJECTED,
        ];
    }
}
