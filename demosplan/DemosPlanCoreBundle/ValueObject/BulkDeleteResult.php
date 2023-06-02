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
 * @method int  getCountSuccessful()
 * @method void setCountSuccessful(int $countSuccessful)
 * @method int  getCountUnsuccessful()
 * @method void setCountUnsuccessful(int $countUnsuccessful)
 * @method int  getCountNotFound()
 * @method void setCountNotFound(int $countNotFound)
 * @method bool getIsErroneous()
 * @method void setIsErroneous(bool $isErroneous)
 */
class BulkDeleteResult extends ValueObject
{
    /**
     * @var int
     */
    protected $countSuccessful = 0;

    /**
     * @var int
     */
    protected $countUnsuccessful = 0;

    /**
     * @var int
     */
    protected $countNotFound = 0;

    /**
     * @var bool
     */
    protected $isErroneous = false;

    public function __construct(
        int $countSuccessful,
        int $countUnsuccessful,
        int $countNotFound,
        bool $isErroneous
    ) {
        $this->countSuccessful = $countSuccessful;
        $this->countUnsuccessful = $countUnsuccessful;
        $this->countNotFound = $countNotFound;
        $this->isErroneous = $isErroneous;
    }
}
