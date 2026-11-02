<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ai;

use DemosEurope\DemosplanAddon\Contracts\Ai\AiModeProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Ai\AiModeResolverInterface;
use DemosEurope\DemosplanAddon\Contracts\Ai\AiModes;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

class AiModeResolver implements AiModeResolverInterface, ResetInterface
{
    /**
     * @var array<string, string> resolved mode by orga id
     */
    private array $resolvedModeByOrgaId = [];

    /**
     * @param iterable<AiModeProviderInterface> $providers priority-ordered
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(OrgaInterface $orga): string
    {
        $orgaId = $orga->getId();
        if (null !== $orgaId && isset($this->resolvedModeByOrgaId[$orgaId])) {
            return $this->resolvedModeByOrgaId[$orgaId];
        }

        $mode = $this->determine($orga);
        if (null !== $orgaId) {
            $this->resolvedModeByOrgaId[$orgaId] = $mode;
        }

        return $mode;
    }

    /**
     * Allowed modes: {@link AiModes::NONE} plus everything the providers declare.
     *
     * @return array<int, string>
     */
    public function getAllowedModes(): array
    {
        $modes = [AiModes::NONE];
        foreach ($this->providers as $provider) {
            $modes = [...$modes, ...$provider->getAvailableModes()];
        }

        return array_values(array_unique($modes));
    }

    public function reset(): void
    {
        $this->resolvedModeByOrgaId = [];
    }

    private function determine(OrgaInterface $orga): string
    {
        $allowed = $this->getAllowedModes();

        $stored = $orga->getAiMode();
        if (null !== $stored) {
            if (in_array($stored, $allowed, true)) {
                return $stored;
            }
            $this->logger->warning('Stored AI mode is no longer allowed, falling back', [
                'orgaId' => $orga->getId(),
                'mode'   => $stored,
            ]);
        }

        foreach ($this->providers as $provider) {
            $default = $provider->getDefaultMode($orga);
            if (null !== $default && in_array($default, $allowed, true)) {
                return $default;
            }
        }

        return AiModes::NONE;
    }
}
