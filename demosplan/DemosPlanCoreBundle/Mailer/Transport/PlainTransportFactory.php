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
use Symfony\Component\Mailer\Transport\TransportInterface;

class PlainTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $port = $dsn->getPort(0);
        $host = $dsn->getHost();

        return new PlainTransport($host, $port, false, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smtp-plain'];
    }
}
