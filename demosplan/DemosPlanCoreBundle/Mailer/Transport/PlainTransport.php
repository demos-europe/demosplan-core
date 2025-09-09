<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Mailer\Transport;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * This transport enforces that certificate of mailserver is never checked
 * This might be helpful when Mailserver is misconfigured to offer STARTTLS
 * even when called on port 25 and then presents a self signed certificate
 * (or even none at all).
 * Should only be used when really needed to.
 */
class PlainTransport extends EsmtpTransport
{
    protected function doHeloCommand(): void
    {
        SmtpTransport::doHeloCommand();
    }
}
