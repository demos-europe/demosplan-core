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

use demosplan\DemosPlanCoreBundle\Doctrine\Type\EncryptedStringType;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;

/**
 * Injects the SecretEncryptor into the EncryptedStringType DBAL type
 * on every Doctrine connection (both HTTP requests and CLI commands).
 *
 * Doctrine DBAL types are static singletons that cannot receive constructor
 * injection, so this listener bridges the gap by calling the static setter.
 */
class EncryptedStringTypeInitializer
{
    public function __construct(private readonly SecretEncryptor $encryptor)
    {
    }

    public function postConnect(): void
    {
        EncryptedStringType::setEncryptor($this->encryptor);
    }
}
