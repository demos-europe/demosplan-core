<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Psr\Log\LoggerInterface;

abstract class PiErrorManagerAbstract
{
    /**
     * @var PiCommunication
     */
    protected $piCommunication;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $maxPiRetries;

    public function __construct(
        PiCommunication $piCommunication,
        LoggerInterface $logger,
        int $maxPiRetries
    ) {
        $this->logger = $logger;
        $this->maxPiRetries = $maxPiRetries;
        $this->piCommunication = $piCommunication;
    }

    /**
     * Reacts to an error in PI for a given doctrine Entity.
     */
    public function managePiError(
        CoreEntity $entity,
        string $entityId,
        string $errorInfo
    ): void {
        $errorInfo = '' === $errorInfo ? 'No error info received' : $errorInfo;
        $this->logger->error(
            get_class($entity)."#$entityId produced error in PI\n$errorInfo"
        );

        if ($this->maxPiRetries > $this->getNumberOfRetries($entity)) {
            $this->incrementNumberOfRetries($entity);
            $this->piCommunication->request($entity);
        } else {
            $this->logger->error(get_class($entity)."#$entityId can't be handled by PI.");
        }
    }

    /**
     * Gets the number of retries so far for the specific Pi Request.
     *
     * @param CoreEntity $entity
     */
    abstract protected function getNumberOfRetries($entity): int;

    /**
     * Increments the number of retries for the specific Pi Request.
     *
     * @param CoreEntity $entity
     */
    abstract protected function incrementNumberOfRetries($entity): void;
}
