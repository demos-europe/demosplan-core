<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GdprConsentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class GdprConsent.
 *
 * The GDPR (named DSGVO in Germany) defines that a user has to give consent for they personal data to be used in
 * the system. Regarding statements this consent is at least required from the submitter of the statement.
 *
 * However in the system the author may not even create an actual Statement but only a DraftStatement which may be
 * submitted by someone else. Only when the DraftStatement is submitted an original Statement will be created.
 *
 * A DraftStatement is created in the background even if the user creates a Statement in a single step action.
 *
 * To keep things simple we assume that only the submitters consent is needed for a statement which makes this
 * an one-to-one relationship between a Statement and a GdprConsent. The author is ignored for now.
 *
 * If the authors consent is needed as well not only needs the statement to hold multiple consents (resulting
 * in a one-to-many relationship -> crosstable) but the DraftStatements need to hold the consents as well which
 * results in an additional one-to-many relationship between DraftStatements and GdprConsent).
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\GdprConsentRepository")
 */
class GdprConsent extends CoreEntity implements UuidEntityInterface, GdprConsentInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * The consent was given to this entity.
     *
     * No onDelete="CASCADE" as this is already done by doctrine. See {@link StatementInterface::gdprConsent}.
     *
     * @var StatementInterface
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="gdprConsent")
     *
     * @ORM\JoinColumn(referencedColumnName="_st_id")
     */
    protected $statement;

    /**
     * Will be set to true if consent was legally received from the user. A false value is used in cases when the
     * consent was not determined yet (old statements and manual statements) or was for some reason not given
     * (should currently not be possible for new statements) yet.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $consentReceived = false;

    /**
     * The date the consent was received. May be differ from the Statement creation time in case of old
     * Statements (and – if at some point relevant – DraftStatement, as they consent is not given at creation).
     * Will be null if no consent was received yet.
     *
     * @var DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default":null})
     */
    protected $consentReceivedDate;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false})
     */
    protected $consentRevoked = false;

    /**
     * Will be set to the current time when the consent is revoked.
     * A null value indicates that the consent was not revoked yet.
     *
     * @var DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default":null})
     */
    protected $consentRevokedDate;

    /**
     * The person that has (or has not) gave (or revoked) consent if it is present as user entity in the database.
     * Will be set to a null value if the user is not in the database.
     *
     * Will be set to null if the user deletes they account. The consent will then be handled as if the user
     * submitted the statement without an user account at all. To be clear: we want to keep the consent if the
     * user deletes they account, as a person does not lose they right to revoke with the deletion.
     *
     * @var UserInterface|null
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", onDelete="SET NULL")
     */
    protected $consentee;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return DateTime|null
     */
    public function getConsentReceivedDate()
    {
        return $this->consentReceivedDate;
    }

    /**
     * @param DateTime|null $consentReceivedDate
     */
    public function setConsentReceivedDate($consentReceivedDate)
    {
        $this->consentReceivedDate = $consentReceivedDate;
    }

    /**
     * @return DateTime|null
     */
    public function getConsentRevokedDate()
    {
        return $this->consentRevokedDate;
    }

    /**
     * @param DateTime|null $date
     */
    public function setConsentRevokedDate($date)
    {
        $this->consentRevokedDate = $date;
    }

    /**
     * @return UserInterface|null
     */
    public function getConsentee()
    {
        return $this->consentee;
    }

    /**
     * @param UserInterface|null $consentee
     */
    public function setConsentee($consentee)
    {
        $this->consentee = $consentee;
    }

    /**
     * @return StatementInterface
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @throws InvalidDataException
     */
    public function setStatement(StatementInterface $statement)
    {
        $this->statement = $statement;

        $statement->setGdprConsent($this);
    }

    public function isConsented(): bool
    {
        return $this->isConsentReceived() && !$this->isConsentRevoked();
    }

    public function isConsentReceived(): bool
    {
        return $this->consentReceived;
    }

    public function setConsentReceived(bool $consentReceived)
    {
        $this->consentReceived = $consentReceived;
    }

    public function isConsentRevoked(): bool
    {
        return $this->consentRevoked;
    }

    public function setConsentRevoked(bool $consentRevoked)
    {
        $this->consentRevoked = $consentRevoked;
    }

    /**
     * @return bool true if the consent was given regarding the author data of the statement
     */
    public function isConsentedToAuthorData(): bool
    {
        // submitted by unregistered citizen
        if ($this->getStatement()->hasBeenSubmittedAndAuthoredByUnregisteredCitizen()) {
            return true;
        }

        // submitted by registered citizen
        if ($this->getStatement()->hasBeenSubmittedAndAuthoredByRegisteredCitizen()) {
            return true;
        }

        // submitted by Institution Sachbearbeiter
        if ($this->getStatement()->hasBeenAuthoredByInstitutionSachbearbeiterAndSubmittedByInstitutionKoordinator()) {
            return true;
        }

        // all other cases
        return false;
    }

    /**
     * @return bool true if the consent was given regarding the submitter data of the statement
     */
    public function isConsentedToSubmitterData(): bool
    {
        // submitted by unregistered citizen
        if ($this->getStatement()->hasBeenSubmittedAndAuthoredByUnregisteredCitizen()) {
            return true;
        }

        // submitted by registered citizen
        if ($this->getStatement()->hasBeenSubmittedAndAuthoredByRegisteredCitizen()) {
            return true;
        }

        // submitted by Institution Koordinator
        if ($this->getStatement()->hasBeenSubmittedAndAuthoredByInvitableInstitutionKoordinator()) {
            return true;
        }

        // all other cases
        return false;
    }
}
