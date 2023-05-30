<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Form\AbstractProcedureFormType;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores the data of an procedure sent or shown in a form.
 *
 * Needed by the {@link AbstractProcedureFormType}.
 */
class ProcedureFormData extends ValueObject
{
    /**
     * @Assert\Type(type=EmailAddressVO::class)
     *
     * @var EmailAddressVO
     */
    protected $agencyMainEmailAddress;

    /**
     * @Assert\Type(type=Collection::class)
     * @Assert\Count(max=100)
     * @Assert\NotNull()
     * @Assert\All({
     *     @Assert\Type(type=EmailAddressVO::class)
     * })
     *
     * @var Collection<int, EmailAddressVO>
     */
    protected $agencyExtraEmailAddresses;

    /**
     * @var array<int,string>
     */
    protected $allowedSegmentAccessProcedureIds;

    public function __construct(Procedure $procedure = null)
    {
        if (null === $procedure) {
            $this->agencyMainEmailAddress = new EmailAddressVO('');
            $this->agencyExtraEmailAddresses = new ArrayCollection();
            $this->allowedSegmentAccessProcedureIds = [];
        } else {
            $this->agencyMainEmailAddress = new EmailAddressVO($procedure->getAgencyMainEmailAddress());
            $this->agencyExtraEmailAddresses = $procedure->getAgencyExtraEmailAddresses()->map(
                static function (EmailAddress $emailAddress): EmailAddressVO {
                    return new EmailAddressVO($emailAddress->getFullAddress());
                }
            );
            $this->allowedSegmentAccessProcedureIds = $procedure
                ->getSettings()
                ->getAllowedSegmentAccessProcedures()
                ->map(static function (Procedure $allowedProcedure): string {
                    return $allowedProcedure->getId();
                })
                ->getValues();
        }
    }

    public function getAgencyMainEmailAddress(): EmailAddressVO
    {
        return $this->agencyMainEmailAddress;
    }

    public function getAgencyMainEmailAddressFullString(): string
    {
        return $this->agencyMainEmailAddress->getFullAddress() ?? '';
    }

    public function setAgencyMainEmailAddress(EmailAddressVO $agencyMainEmailAddress)
    {
        $this->agencyMainEmailAddress = $agencyMainEmailAddress;
    }

    /**
     * @return Collection<int, EmailAddressVO>
     */
    public function getAgencyExtraEmailAddresses(): Collection
    {
        return $this->agencyExtraEmailAddresses;
    }

    /**
     * @param Collection<int, EmailAddressVO> $agencyExtraEmailAddresses
     */
    public function setAgencyExtraEmailAddresses(Collection $agencyExtraEmailAddresses): void
    {
        $this->agencyExtraEmailAddresses = $agencyExtraEmailAddresses;
    }

    public function addExtraEmailAddress(EmailAddressVO $agencyExtraEmailAddress): void
    {
        $this->agencyExtraEmailAddresses->add($agencyExtraEmailAddress);
    }

    public function removeExtraEmailAddress(EmailAddressVO $agencyExtraEmailAddress): void
    {
        $this->agencyExtraEmailAddresses->removeElement($agencyExtraEmailAddress);
    }

    /**
     * @return array<int, string>
     */
    public function getAgencyExtraEmailAddressesFullStrings(): array
    {
        return $this->agencyExtraEmailAddresses->map(
            function (EmailAddressVO $address): string {
                return $address->getFullAddress();
            }
        )->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedSegmentAccessProcedureIds(): array
    {
        return $this->allowedSegmentAccessProcedureIds;
    }

    /**
     * @param array<int, string> $allowedSegmentAccessProcedureIds
     */
    public function setAllowedSegmentAccessProcedureIds(array $allowedSegmentAccessProcedureIds): void
    {
        $this->allowedSegmentAccessProcedureIds = $allowedSegmentAccessProcedureIds;
    }

    public function addAllowedSegmentAccessProcedureId(string $allowedSegmentAccessProcedureId): void
    {
        $this->allowedSegmentAccessProcedureIds[] = $allowedSegmentAccessProcedureId;
    }
}
