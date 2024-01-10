<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\GdprConsentRevokeTokenInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * This token allows the anonymization of statements connected to it directly or indirectly.
 *
 * Tokens are (as of writing) only provided to unregistered citizens, as these by definition do not
 * have an account where they could list all their submitted statements and revoke their GDPR
 * consent. However the usage of tokens is (as of writing) not limited by roles to allow users to
 * register at a later point in time and still being able to use the link while being logged in.
 *
 * All existing token instances should be considered as allowed to be used. This means an instance
 * should only be created when its usage is allowed. In contrast creating tokens in advance for
 * statements which are not intended to be revoked by a token (currently all statements not
 * submitted by unregistered citizens) is wrong.
 *
 * The token must neither have a created-date nor a used-date as these would make it possible to
 * connect the token after usage to the statements or the GdprConsent of the statement. A
 * valid-till date was deemed as not needed. If it is at some point however the date problematic
 * must be taken into account too while considering substraction of fixed time span values.
 *
 * The usage of the token is possible only once. However the token must not be deleted to be able
 * to inform the user that the token was already used in case he tries to use it multiple times.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\GdprConsentRevokeTokenRepository")
 */
class GdprConsentRevokeToken extends CoreEntity implements UuidEntityInterface, GdprConsentRevokeTokenInterface
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
     * Tokens must only be connected to original statements. Other type of statement instances are
     * referenced directly or indirectly by their original statement which makes a direct
     * connection from these to the token unnecessary.
     *
     * Even though only one statement can be submitted at once and a new token should be created
     * for each submission a token can connect to multiple statements because original statements
     * may be copied into other procedures resulting in another original statement. The token needs
     * to be connected to the copy in these cases. Both the source and the copy need to be
     * anonymized when the token is used.
     *
     * To avoid leaking GDPR relevant information the connection between token and statements must
     * be severed during the anonymization.
     *
     * The cross table was choosen over an additional statement column to avoid reams of null
     * values in that column. If at any point original statements have become a separate table this
     * can be the column variant may make more sense.
     *
     * Notice the unique=true, making this a OneToMany instead of a ManyToMany.
     *
     * @var Statement[]
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     *
     * @ORM\JoinTable(name="gdpr_consent_revoke_token_statements",
     *      joinColumns={@ORM\JoinColumn(name="token_id", referencedColumnName="id", nullable=false)},
     *      inverseJoinColumns={@ORM\JoinColumn(name="statement_id", referencedColumnName="_st_id", unique=true, nullable=false)}
     * )
     */
    protected $statements;

    /**
     * Do not simply use the ID of this entity as token value but this separate field. Beside
     * security concerns regarding the usage of IDs for anything else than uniquely identifying
     * entities this also allows to chose token values shorter or longer than the ID.
     *
     * As of writing a filter hash is used to maintain a relatively short token value.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=12, nullable=false, unique=true, options={"fixed":true})
     */
    protected $token;

    /**
     * Used with the $token value to authenticate the person using the token.
     * This must be set to null when the token is used to anonymize corresponding statements to not
     * leave GDPR relevant data behind. As the creation of tokens without an email address makes no
     * sense this property can be used to check if the taken was already used.
     *
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\EmailAddress",
     *     cascade={"persist"})
     *
     * @ORM\JoinColumn(name="email_id",
     *     referencedColumnName="id",
     *     nullable = false)
     */
    protected $emailAddress;

    /**
     * Set the initial values for the instance.
     */
    public function __construct()
    {
        $this->statements = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return Collection<int, Statement>
     */
    public function getStatements()
    {
        return $this->statements;
    }

    public function setStatements(Collection $statements)
    {
        $this->statements = $statements;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    public function getEmailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(EmailAddress $emailAddress): self
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }
}
