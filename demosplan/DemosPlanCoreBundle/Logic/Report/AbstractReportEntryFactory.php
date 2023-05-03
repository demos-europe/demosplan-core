<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;

abstract class AbstractReportEntryFactory
{
    /**
     * @var CurrentUserInterface
     */
    protected $currentUserProvider;

    /**
     * @var CustomerService
     */
    protected $currentCustomerProvider;

    public function __construct(
        CurrentUserInterface $currentUserProvider,
        CustomerService $currentCustomerProvider
    ) {
        $this->currentUserProvider = $currentUserProvider;
        $this->currentCustomerProvider = $currentCustomerProvider;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = new ReportEntry();
        $reportEntry->setCustomer($this->currentCustomerProvider->getCurrentCustomer());
        $reportEntry->setIncoming('');

        return $reportEntry;
    }
}
