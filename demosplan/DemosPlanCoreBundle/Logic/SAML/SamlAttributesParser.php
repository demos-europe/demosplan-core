<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\SAML;

use demosplan\DemosPlanCoreBundle\Entity\User\User;

class SamlAttributesParser
{
    public function __construct(private readonly User $user, private readonly array $samlAttributes)
    {
    }

    public function parse()
    {
        if (array_key_exists('givenName', $this->samlAttributes)) {
            $this->user->setFirstname($this->samlAttributes['givenName'][0]);
        }
        if (array_key_exists('mail', $this->samlAttributes)) {
            $this->user->setEmail($this->samlAttributes['mail'][0]);
        }
        if (array_key_exists('surname', $this->samlAttributes)) {
            $this->user->setLastname($this->samlAttributes['surname'][0]);
        }
        $this->user->setProvidedByIdentityProvider(true);
    }
}
