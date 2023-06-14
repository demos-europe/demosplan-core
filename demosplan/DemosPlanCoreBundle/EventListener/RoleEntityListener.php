<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use LogicException;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleEntityListener
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /** @PostLoad */
    public function postLoad(Role $role, LifecycleEventArgs $event): void
    {
        $code = $role->getCode();
        $codeMap = Role::ROLE_CODE_NAME_MAP;

        if (!isset($codeMap[$code])) {
            throw new LogicException('Invalid role code.');
        }

        $role->setName($this->translator->trans($codeMap[$code]));
    }
}
