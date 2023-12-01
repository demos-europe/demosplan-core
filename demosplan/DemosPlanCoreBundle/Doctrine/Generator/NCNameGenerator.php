<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Generator;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Ramsey\Uuid\Uuid;

/**
 * Generates a UUID in code that confirms to https://www.w3.org/TR/1999/REC-xml-names-19990114/#NT-NCName
 * to be able to be used as xs:ID type in XML messages.
 */
class NCNameGenerator extends AbstractIdGenerator
{
    public function uuid(): string
    {
        $uuid = '';
        $tryAgain = true;
        while ($tryAgain) {
            $uuid = Uuid::uuid4()->toString();
            if (0 !== preg_match('/[A-Za-z]/', $uuid[0])) {
                $tryAgain = false;
            }
        }

        return $uuid;
    }

    public function generateId(EntityManagerInterface $em, $entity): string
    {
        return $this->uuid();
    }
}
