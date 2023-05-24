<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Entity\File;

/**
 * @method File|null getOrgaLogo()
 * @method void      setOrgaLogo(File|null $orgaLogo)
 * @method File|null getCustomerLogo()
 * @method void      setCustomerLogo(File|null $orgaLogo)
 * @method string    getOrgaId()
 * @method void      setOrgaId(string $styleId)
 * @method string    getCustomerSubdomain()
 * @method void      setCustomerSubdomain(string $styleId)
 * @method string    getCustomerCss()
 * @method void      setCustomerCss(string $customerCss)
 * @method string    getOrgaCss()
 * @method void      setOrgaCss(string $orgaCss)
 */
class BrandingValueObject extends ValueObject
{
    /**
     * @var File|null
     */
    protected $orgaLogo;

    /**
     * @var File|null
     */
    protected $customerLogo;

    /**
     * @var string
     */
    protected $orgaId;

    /**
     * @var string
     */
    protected $customerSubdomain;

    /**
     * @var string
     */
    protected $customerCss;

    /**
     * @var string
     */
    protected $orgaCss;
}
