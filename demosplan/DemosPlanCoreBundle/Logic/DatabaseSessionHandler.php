<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class DatabaseSessionHandler extends PdoSessionHandler
{
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $dsn = 'mysql:host='.$parameterBag->get('database_host').';dbname='.$parameterBag->get('database_name');
        $pdoOptions = [
            'db_username' => $parameterBag->get('database_user'),
            'db_password' => $parameterBag->get('database_password'),
        ];
        parent::__construct($dsn, $pdoOptions);
    }

}