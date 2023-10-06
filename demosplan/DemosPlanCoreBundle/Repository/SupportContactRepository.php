<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;

class SupportContactRepository extends FluentRepository
{


    public function get(string $supportContactId): SupportContact
    {
        return $this->find($supportContactId);
    }

    public function add(SupportContact $supportContact): void
    {
        $this->update($supportContact);
    }

    public function update(SupportContact $supportContact): void
    {
        $supportContact->setText($this->sanitize($supportContact->getText()));
        $this->getEntityManager()->persist($supportContact);
        $this->getEntityManager()->flush();
    }

    public function delete(SupportContact $supportContact): void
    {
        $this->getEntityManager()->remove($supportContact);
        $this->getEntityManager()->flush();
    }
}
