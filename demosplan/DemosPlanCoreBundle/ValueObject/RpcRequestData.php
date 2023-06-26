<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Class RpcRequestData.
 *
 * @method array getActions()
 * @method array getData()
 * @method void  setActions(array $actions)
 * @method void  setData(array $data)
 */
class RpcRequestData extends ValueObject
{
    /**
     * @var array<string,mixed>
     */
    protected $actions;

    /**
     * @var array<string,mixed>
     */
    protected $data;
}
