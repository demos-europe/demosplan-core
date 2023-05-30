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
 * Class OrgaBranding.
 *
 * @method File|null getLogo()
 * @method           setLogo(File|null $originalValue)
 * @method string    getOrgaId()
 * @method           setOrgaId(string $orgaId)
 */
class OrgaBranding extends ValueObject
{
    /**
     * @var File
     */
    protected $logo;

    /**
     * @var string
     */
    protected $orgaId;
}
