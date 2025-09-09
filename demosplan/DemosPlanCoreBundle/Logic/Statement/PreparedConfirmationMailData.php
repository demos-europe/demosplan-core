<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PdfFile;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string|null            getDestinationAddress()
 * @method array                  getExternIds()
 * @method PdfFile|null           getPdfResult()
 * @method Orga|null              getProcedureOrga()
 * @method ConsultationToken|null getConsultationToken()
 * @method string|null            getConsultationTokenString()
 */
class PreparedConfirmationMailData extends ValueObject
{
    /**
     * @var string|null
     */
    protected $destinationAddress;

    /**
     * @var array
     */
    protected $externIds;

    /**
     * @var PdfFile|null
     */
    protected $pdfResult;

    /**
     * @var Orga|null
     */
    protected $procedureOrga;

    /**
     * @var ConsultationToken|null
     */
    protected $consultationToken;

    /**
     * @var string|null
     */
    protected $consultationTokenString;

    public function __construct(
        ?string $destinationAddress,
        array $externIds,
        ?PdfFile $pdfResult,
        ?Orga $procedureOrga,
        ?ConsultationToken $consultationToken,
        ?string $consultationTokenString
    ) {
        $this->destinationAddress = $destinationAddress;
        $this->externIds = $externIds;
        $this->pdfResult = $pdfResult;
        $this->procedureOrga = $procedureOrga;
        $this->consultationToken = $consultationToken;
        $this->consultationTokenString = $consultationTokenString;
        $this->lock();
    }
}
