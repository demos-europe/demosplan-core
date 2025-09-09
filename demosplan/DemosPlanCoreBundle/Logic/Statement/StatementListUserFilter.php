<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

class StatementListUserFilter
{
    /**
     * @var bool|null
     */
    private $released;

    /**
     * @var bool|null
     */
    private $submitted;

    /**
     * @var bool|null
     */
    private $otherCompanies;

    /**
     * @var bool|null
     */
    private $showToAll;

    /**
     * @var bool|null
     */
    private $oName;

    /**
     * @var string|null
     */
    private $action;

    /**
     * @var string|null
     */
    private $department;

    /**
     * @var string|null
     */
    private $element;

    /**
     * @var bool|null
     */
    private $flipStatus;

    /**
     * @var string|null
     */
    private $orga;

    /**
     * @return string|null
     */
    private $someOnesUserId;

    /**
     * @var mixed|null
     */
    private $submit;

    /**
     * @var string|null
     */
    private $currentUserId;

    public function getReleased(): ?bool
    {
        return $this->released;
    }

    public function setReleased(bool $released): self
    {
        $this->released = $released;

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getSubmitOfIncomingListField()
    {
        return $this->submit;
    }

    public function setSubmitOfIncomingListField($submit): self
    {
        $this->submit = $submit;

        return $this;
    }

    public function getSubmitted(): ?bool
    {
        return $this->submitted;
    }

    public function setSubmitted(bool $submitted): self
    {
        $this->submitted = $submitted;

        return $this;
    }

    public function getOtherCompaniesFilter(): ?bool
    {
        return $this->otherCompanies;
    }

    public function setOtherCompaniesFilter(bool $otherCompanies): self
    {
        $this->otherCompanies = $otherCompanies;

        return $this;
    }

    public function getShowToAll(): ?bool
    {
        return $this->showToAll;
    }

    public function setShowToAll(bool $showToAll): self
    {
        $this->showToAll = $showToAll;

        return $this;
    }

    public function getOrganisationNameFilter(): ?bool
    {
        return $this->oName;
    }

    public function setOrganisationNameFilter(bool $oName): self
    {
        $this->oName = $oName;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(string $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getElement(): ?string
    {
        return $this->element;
    }

    public function setElement(string $element): self
    {
        $this->element = $element;

        return $this;
    }

    public function getFlipStatus(): ?bool
    {
        return $this->flipStatus;
    }

    public function setFlipStatus(bool $flipStatus): self
    {
        $this->flipStatus = $flipStatus;

        return $this;
    }

    public function getOrga(): ?string
    {
        return $this->orga;
    }

    public function setOrga(string $orga): self
    {
        $this->orga = $orga;

        return $this;
    }

    public function getSomeOnesUserId(): ?string
    {
        return $this->someOnesUserId;
    }

    public function setSomeOnesUserId(string $someOnesUserId): self
    {
        $this->someOnesUserId = $someOnesUserId;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->currentUserId;
    }

    public function setUserId(string $userId): self
    {
        $this->currentUserId = $userId;

        return $this;
    }

    public function toArray(): array
    {
        $filterArray = [];

        // To replicate the array prior this Filter-Object, the properties with null value do not set a key
        if (null !== $this->action) {
            $filterArray['action'] = $this->action;
        }
        if (null !== $this->department) {
            $filterArray['department'] = $this->department;
        }
        if (null !== $this->element) {
            $filterArray['element'] = $this->element;
        }
        if (null !== $this->flipStatus) {
            $filterArray['flip_status'] = $this->flipStatus;
        }
        if (null !== $this->oName) {
            $filterArray['oName'] = $this->oName;
        }
        if (null !== $this->orga) {
            $filterArray['orga'] = $this->orga;
        }
        if (null !== $this->otherCompanies) {
            $filterArray['otherCompanies'] = $this->otherCompanies;
        }
        if (null !== $this->showToAll) {
            $filterArray['showToAll'] = $this->showToAll;
        }
        if (null !== $this->submit) {
            $filterArray['submit'] = $this->submit;
        }
        if (null !== $this->submitted) {
            $filterArray['submitted'] = $this->submitted;
        }
        if (null !== $this->someOnesUserId) {
            $filterArray['uId'] = $this->someOnesUserId;
        }
        if (null !== $this->currentUserId) {
            $filterArray['user'] = $this->currentUserId;
        }
        if (null !== $this->released) {
            $filterArray['released'] = $this->released;
        }

        return $filterArray;
    }
}
