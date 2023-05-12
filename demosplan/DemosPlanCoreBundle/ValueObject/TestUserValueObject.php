<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Class TestUserValueObject.
 *
 * @method string getName()
 * @method string setName($name)
 * @method string getOrga()
 * @method string setOrga($orga)
 * @method string getDepartment()
 * @method string setDepartment($department)
 * @method string getLogin()
 * @method string setLogin($login)
 * @method string getEmail()
 * @method string setEmail($email)
 * @method string getRoles()
 * @method string addRoles($roles)
 */
class TestUserValueObject extends ValueObject
{
    protected $name;
    protected $orga;
    protected $department;
    protected $login;
    protected $email;
    protected $roles;
}
