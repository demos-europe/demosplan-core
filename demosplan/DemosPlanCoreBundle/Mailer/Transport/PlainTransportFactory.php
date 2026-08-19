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

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Registers the `smtp-plain://` DSN scheme: an ESMTP transport with the
 * initial TLS handshake disabled. Useful when the mail server is reachable
 * on port 25 only and should not be contacted over SSL.
 */
class PlainTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $port = $dsn->getPort(0);
        $host = $dsn->getHost();

        return new EsmtpTransport($host, $port, false, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smtp-plain'];
    }
}
