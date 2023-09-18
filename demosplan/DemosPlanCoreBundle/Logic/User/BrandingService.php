<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\ValueObject\OrgaBranding;
use Exception;

class BrandingService extends CoreService
{
    public function __construct(private readonly ProcedureService $procedureService)
    {
    }

    /**
     * Uses the procedureId to get the orga branding information.
     *
     * @throws Exception
     */
    public function createOrgaBrandingFromProcedureId(string $procedureId): OrgaBranding
    {
        $procedure = $this->getProcedureService()->getProcedure($procedureId);
        if (!$procedure instanceof Procedure) {
            throw new MissingDataException(sprintf('Requested procedure with ID "%s" not found.', $procedureId));
        }
        $orga = $procedure->getOrga();

        return $this->createOrgaBranding($orga);
    }

    /**
     * Uses the orga to get the orga branding information.
     */
    public function createOrgaBranding(Orga $orga): OrgaBranding
    {
        $orgaBranding = new OrgaBranding();

        // orga data
        $orgaId = $orga->getId() ?? $orga->getIdent();
        $orgaLogo = $orga->getLogo();
        if ($orgaLogo instanceof File) {
            $orgaLogo->getHash();
        }

        $orgaBranding->setLogo($orgaLogo);
        $orgaBranding->setOrgaId($orgaId);
        $orgaBranding->lock();

        return $orgaBranding;
    }

    protected function getProcedureService(): ProcedureService
    {
        return $this->procedureService;
    }
}
