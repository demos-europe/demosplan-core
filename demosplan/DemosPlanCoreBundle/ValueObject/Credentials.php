<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Class Credentials.
 *
 * @method string|null getPassword()
 * @method             setPassword(?string $password)
 * @method string|null getToken()
 * @method             setToken(?string $token)
 * @method string|null getLogin()
 * @method             setLogin(?string $login)
 */
class Credentials extends ValueObject
{
    /**
     * @var string|null
     */
    protected $password;

    /**
     * @var string|null
     */
    protected $token;

    /**
     * @var string|null
     */
    protected $login;
}
