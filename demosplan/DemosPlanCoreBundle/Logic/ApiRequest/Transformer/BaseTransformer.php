<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use League\Fractal\TransformerAbstract;
use LogicException;

abstract class BaseTransformer extends TransformerAbstract
{
    protected $type;

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    /**
     * @var ApiResourceService
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
     *
     * @required
     */
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
     *
     * @required
     */
    public function setResourceService(ApiResourceService $resourceService): void
    {
        $this->resourceService = $resourceService;
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setGlobalConfig(GlobalConfigInterface $globalConfig): void
    {
        $this->globalConfig = $globalConfig;
    }
}
