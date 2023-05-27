<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer;

use Symfony\Contracts\Service\Attribute\Required;
use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiResourceServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\Transformer\BaseTransformerInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use League\Fractal\TransformerAbstract;
use LogicException;

abstract class BaseTransformer extends TransformerAbstract implements BaseTransformerInterface
{
    protected $type;

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    /**
     * @var ApiResourceServiceInterface
     */
    protected $resourceService;

    public function __construct()
    {
        if (!is_string($this->type) || '' === $this->type) {
            throw new LogicException('Transformer must set it\'s type');
        }
    }

    public function getClass(): string
    {
        return static::class;
    }

    public function getInstance(): self
    {
        return $this;
    }

    /**
     * @deprecated use {@link ResourceTypeInterface::getName()} where possible, because it should
     *             be the actual source for this information and not the transformer
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getPermissions(): PermissionsInterface
    {
        return $this->permissions;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setPermissions(PermissionsInterface $permissions): void
    {
        $this->permissions = $permissions;
    }

    /**
     * @param DateTime $date
     */
    public function formatDate($date): string
    {
        if ($date instanceof DateTime) {
            return Carbon::instance($date)->toIso8601String();
        }

        return '';
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setResourceService(ApiResourceServiceInterface $resourceService): void
    {
        $this->resourceService = $resourceService;
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setGlobalConfig(GlobalConfigInterface $globalConfig): void
    {
        $this->globalConfig = $globalConfig;
    }
}
