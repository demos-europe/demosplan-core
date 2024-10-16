<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\SettingInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_settings", indexes={@ORM\Index(name="_s_key", columns={"_s_key"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SettingRepository")
 */
class Setting extends CoreEntity implements UuidEntityInterface, SettingInterface
{
    /**
     * @var string|null
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     *
     * @ORM\Column(name="_s_id", type="string", length=36, options={"fixed":true})
     */
    protected $ident;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="_s_procedure_id", referencedColumnName="_p_id", onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * Virtuelle Eigenshaft der Id des Verfahrens.
     *
     * @var string
     */
    protected $procedureId;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_s_user_id", referencedColumnName="_u_id", onDelete="RESTRICT")
     */
    protected $user;

    /**
     * Virtuelle Eigenshaft der Id des Benutzers.
     *
     * @var string
     */
    protected $userId;

    /**
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_s_orga_id", referencedColumnName="_o_id", onDelete="RESTRICT")
     */
    protected $orga;

    /**
     * Virtuelle Eigenshaft der Id der Ogranisation.
     *
     * @var string
     *
     * @ORM\Column(name="_s_orga_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $orgaId;

    /**
     * @var string
     *
     * @ORM\Column(name="_s_key", type="string", nullable=false)
     */
    protected $key;

    /**
     * @var string
     *
     * @ORM\Column(name="_s_content", type="text", length=65535, nullable=false)
     */
    protected $content;

    /**
     * Date of creation.
     *
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_s_create_date", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * Date of last modifying.
     *
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_s_modified_date", type="datetime", nullable=false)
     */
    protected $modified;

    /**
     * @deprecated use {@link Setting::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * Set Ident.
     *
     * @param string $ident
     *
     * @return Setting
     */
    public function setIdent($ident)
    {
        $this->ident = $ident;

        return $this;
    }

    /**
     * Get procedure id.
     *
     * @return string
     */
    public function getProcedureId()
    {
        if (is_null($this->procedure)) {
            return $this->procedureId;
        }

        return $this->procedure->getId();
    }

    /**
     * Get procedure.
     *
     * @return Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * Set Procedure.
     *
     * @param Procedure $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
        $this->procedureId = $procedure->getId();
    }

    /**
     * Get User Id.
     *
     * @return string
     */
    public function getUserId()
    {
        if (is_null($this->user)) {
            return null;
        }

        return $this->user->getId();
    }

    /**
     * Get User.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set User.
     *
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
        $this->userId = $user->getId();
    }

    /**
     * Get Orga Id.
     *
     * @return string
     */
    public function getOrgaId()
    {
        if (is_null($this->orga)) {
            return null;
        }

        return $this->orga->getId();
    }

    /**
     * Get Orga.
     *
     * @return Orga
     */
    public function getOrga()
    {
        return $this->orga;
    }

    /**
     * Set Orga.
     *
     * @param Orga $orga
     */
    public function setOrga($orga)
    {
        $this->orga = $orga;
        $this->orgaId = $orga->getId();
    }

    /**
     * Get key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     *  Set  Key.
     *
     * @param string $key
     *
     * @return Setting
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get Content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get Content as a boolean if applicable.
     *
     * @return bool|null
     */
    public function getContentBool()
    {
        $return = null;
        $return = match ($this->getContent()) {
            'true'  => true,
            'false' => false,
            default => $return,
        };

        return $return;
    }

    /**
     * Set Content.
     *
     * @param string|bool $content
     *
     * @return Setting
     */
    public function setContent($content)
    {
        // boolean values are saved as string. smells a bit
        if (is_bool($content)) {
            $content = true === $content ? 'true' : 'false';
        }
        $this->content = $content;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param DateTime $created
     *
     * @return Setting
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param DateTime $modified
     *
     * @return Setting
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }
}
