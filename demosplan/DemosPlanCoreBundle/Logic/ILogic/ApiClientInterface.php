<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ILogic;

/**
 * Interface ApiClientInterface.
 */
interface ApiClientInterface
{
    public const POST = 'post';
    public const GET = 'get';

    /**
     * By now, support for post and get methods.
     */
    public function request(string $url, array $options, string $method): string;
}
