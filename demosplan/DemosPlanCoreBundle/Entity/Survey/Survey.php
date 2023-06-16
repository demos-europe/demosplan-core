<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Survey;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SurveyVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see https://yaits.demos-deutschland.de/w/demosplan/functions/survey/ Wiki: Survey
 *
 * @ORM\Table(name="survey")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SurveyRepository")
 */
class Survey extends CoreEntity implements UuidEntityInterface, SurveyInterface
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
     * @var string
     *
     * @ORM\Column(name="title", type="text", length=255)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", length=65535)
     */
    protected $description;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="start_date", type="date", nullable=false)
     */
    protected $startDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="end_date", type="date")
     */
    protected $endDate;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=50)
     */
    protected $status;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure",
     *     cascade={"persist"}, inversedBy="surveys")
     *
     * @ORM\JoinColumn(name="p_id", referencedColumnName="_p_id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var Collection<int, SurveyVoteInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote",
     *      mappedBy="survey", cascade={"persist", "remove"})
     */
    protected $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function setProcedure(ProcedureInterface $procedure): void
    {
        $this->procedure = $procedure;
    }

    public function getVotes(): Collection
    {
        return $this->votes;
    }

    /**
     * Returns Survey Vote with given id or null if it doesn't exist.
     *
     * @param string $voteId
     */
    public function getVote($voteId): ?SurveyVoteInterface
    {
        /** @var SurveyVoteInterface $vote */
        foreach ($this->votes as $vote) {
            if ($vote->getId() == $voteId) {
                return $vote;
            }
        }

        return null;
    }

    public function addVote(SurveyVoteInterface $vote): void
    {
        $this->votes[] = $vote;
    }

    public function getPositiveVotes(): Collection
    {
        return $this->votes->filter(
            static fn(SurveyVoteInterface $vote) => $vote->isAgreed()
        );
    }

    public function getNegativeVotes(): Collection
    {
        return $this->votes->filter(
            static fn(SurveyVoteInterface $vote) => !$vote->isAgreed()
        );
    }

    public function getReviewRequiredVotes(): Collection
    {
        return $this->votes->filter(
            static fn(SurveyVoteInterface $vote) => $vote->isReviewRequired()
        );
    }
}
