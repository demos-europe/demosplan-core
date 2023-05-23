<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string[] getOrgasNotInvited()
 * @method string[] getOrgasInvited()
 */
class InvitationEmailResult extends ValueObject
{
    /** @var string[] */
    protected $orgasNotInvited;
    /** @var string[] */
    protected $orgasInvited;

    /**
     * @param string[] $orgasInvited
     * @param string[] $orgasNotInvited
     *
     * @return static
     */
    public static function create(array $orgasInvited, array $orgasNotInvited): self
    {
        $self = new self();
        $self->orgasInvited = $orgasInvited;
        $self->orgasNotInvited = $orgasNotInvited;
        $self->lock();

        return $self;
    }
}
