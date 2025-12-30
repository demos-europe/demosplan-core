<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ApiResources;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use demosplan\DemosPlanCoreBundle\StateProvider\AdminProcedureStateProvider;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    routePrefix: '/3.0',
    provider: AdminProcedureStateProvider::class,
)]
class AdminProcedureResource
{
    public string $id;

    public string $name;

    public string $externalName;

    /*public ?\DateTimeInterface $creationDate = null;

    public ?\DateTimeInterface $internalStartDate = null;

    public ?\DateTimeInterface $internalEndDate = null;

    public ?\DateTimeInterface $externalStartDate = null;

    public ?\DateTimeInterface $externalEndDate = null;

    public ?int $originalStatementsCount = null;

    public ?int $statementsCount = null;

    public ?string $internalPhaseIdentifier = null;

    public ?string $externalPhaseIdentifier = null;

    public ?string $internalPhaseTranslationKey = null;

    public ?string $externalPhaseTranslationKey = null;

    public ?bool $publicParticipation = null;*/
}
