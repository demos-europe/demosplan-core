<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class FileResponseGeneratorStrategy
{
    /**
     * @param iterable<FileResponseGeneratorAbstract> $generators
     */
    public function __construct(private readonly iterable $generators, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws DemosException
     */
    public function __invoke(string $format, array $file): Response
    {
        /** @var FileResponseGeneratorAbstract $generator */
        foreach ($this->generators as $generator) {
            if ($generator->supports($format)) {
                return $generator($file);
            }
        }

        $this->logger->error("Format $format not supported");
        throw new DemosException('error.generic');
    }
}
