<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqResourceType;
use League\Fractal\Resource\Collection;

class FaqCategoryTransformer extends BaseTransformer
{
    protected $type = 'FaqCategory';

    protected array $defaultIncludes = ['faq'];

    /** @var FaqHandler */
    protected $faqHandler;

    public function __construct(PermissionsInterface $permissions, FaqHandler $faqHandler)
    {
        parent::__construct();
        $this->permissions = $permissions;
        $this->faqHandler = $faqHandler;
    }

    public function transform(FaqCategory $faqCategory): array
    {
        return [
            'id'    => $faqCategory->getId(),
            'title' => $faqCategory->getTitle(),
        ];
    }

    public function includeFaq(FaqCategory $faqCategory): Collection
    {
        $faqList = [];
        if ($this->permissions->hasPermission('area_admin_faq')) {
            $faqList = $this->faqHandler->getEnabledAndDisabledFaqList($faqCategory);
        }

        $faqList = $this->faqHandler->orderFaqsByManualSortList($faqList, $faqCategory);

        return $this->resourceService->makeCollectionOfResources($faqList, FaqResourceType::getName());
    }
}
