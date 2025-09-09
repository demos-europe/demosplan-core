<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Forum;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\DevelopmentUserStoryVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_progression_userstory_votes")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DevelopmentUserStoryVoteRepository")
 */
class DevelopmentUserStoryVote extends CoreEntity implements UuidEntityInterface, DevelopmentUserStoryVoteInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_puv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_puv_orga_id", referencedColumnName="_o_id", nullable=false, onDelete="RESTRICT")
     */
    protected $orga;

    /**
     * @var string
     */
    protected $orgaId;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_puv_user_id", referencedColumnName="_u_id", nullable=false, onDelete="RESTRICT")
     */
    protected $user;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var DevelopmentUserStory
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory")
     *
     * @ORM\JoinColumn(name="_puv_userstroy_id", referencedColumnName="_pu_id", nullable=false, onDelete="CASCADE")
     */
    protected $userStory;

    /**
     * @var string
     */
    protected $userStoryId;

    /**
     * todo: smallint to int.
     *
     * @var int
     *
     * @ORM\Column(name="_puv_number_of_votes", type="smallint", length=2, nullable=false, options={"unsigned":true, "default":1})
     */
    protected $numberOfVotes;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_puv_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_puv_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link DevelopmentUserStoryVote::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set Orga.
     *
     * @param Orga $orga
     *
     * @return DevelopmentUserStoryVote
     */
    public function setOrga($orga)
    {
        $this->orga = $orga;
        if ($orga instanceof Orga) {
            $this->orgaId = $orga->getId();
        }

        return $this;
    }

    /**
     * Get orga.
     *
     * @return Orga
     */
    public function getOrga()
    {
        return $this->orga;
    }

    /**
     * Get orgaId.
     *
     * @return string
     */
    public function getOrgaId()
    {
        if (is_null($this->orgaId) && $this->orga instanceof Orga) {
            $this->orgaId = $this->orga->getId();
        }

        return $this->orgaId;
    }

    /**
     * Set User.
     *
     * @param User $user
     *
     * @return DevelopmentUserStoryVote
     */
    public function setUser($user)
    {
        $this->user = $user;
        if ($user instanceof User) {
            $this->userId = $user->getId();
        }

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get userId.
     *
     * @return string
     */
    public function getUserId()
    {
        if (is_null($this->userId) && $this->user instanceof User) {
            $this->userId = $this->user->getId();
        }

        return $this->userId;
    }

    /**
     * Set UserStory.
     *
     * @param DevelopmentUserStory $userStory
     *
     * @return DevelopmentUserStoryVote
     */
    public function setUserStory($userStory)
    {
        $this->userStory = $userStory;
        if ($userStory instanceof DevelopmentUserStory) {
            $this->userStoryId = $userStory->getIdent();
        }

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUserStory()
    {
        return $this->userStory;
    }

    /**
     * Get userId.
     *
     * @return string
     */
    public function getUserStoryId()
    {
        if (is_null($this->userStoryId) && $this->userStory instanceof DevelopmentUserStory) {
            $this->userStoryId = $this->userStory->getIdent();
        }

        return $this->userStoryId;
    }

    /**
     * Set NumberOfVotes.
     *
     * @param int $numberOfVotes
     *
     * @return DevelopmentUserStoryVote
     */
    public function setNumberOfVotes($numberOfVotes)
    {
        $this->numberOfVotes = $numberOfVotes;

        return $this;
    }

    /**
     * Get numberOfVotes.
     *
     * @return int
     */
    public function getNumberOfVotes()
    {
        return $this->numberOfVotes;
    }

    /**
     * Set modifiedDate.
     *
     * @param DateTime $modifiedDate
     *
     * @return DevelopmentUserStoryVote
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get modifiedDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return DevelopmentUserStoryVote
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }
}
