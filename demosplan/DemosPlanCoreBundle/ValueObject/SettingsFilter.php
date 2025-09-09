<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

/**
 * @method string|null getProcedureId()
 * @method string|null getUserId()
 * @method string|null getOrgaId()
 * @method string|null getContent()
 */
class SettingsFilter extends ValueObject
{
    /**
     * @var string|null
     */
    protected $procedureId;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $orgaId;

    /**
     * @var string|null
     */
    protected $content;

    /**
     * Use the static functions instead.
     */
    private function __construct()
    {
    }

    public static function whereProcedureId(string $procedureId): self
    {
        $self = new self();
        $self->procedureId = $procedureId;

        return $self;
    }

    public static function whereUser(User $user): self
    {
        $self = new self();
        $self->userId = $user->getId();

        return $self;
    }

    public static function whereOrga(Orga $orga): self
    {
        $self = new self();
        $self->orgaId = $orga->getId();

        return $self;
    }

    public function andUser(User $user): self
    {
        $this->userId = $user->getId();

        return $this;
    }

    public function andContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return array<string, string|bool>
     */
    public function asArray(): array
    {
        $filterArray = [];

        if (null !== $this->getProcedureId()) {
            $filterArray['procedureId'] = $this->getProcedureId();
        }

        if (null !== $this->getOrgaId()) {
            $filterArray['orgaId'] = $this->getOrgaId();
        }

        if (null !== $this->getUserId()) {
            $filterArray['userId'] = $this->getUserId();
        }

        if (null !== $this->getContent()) {
            $filterArray['content'] = $this->getContent();
        }

        return $filterArray;
    }
}
