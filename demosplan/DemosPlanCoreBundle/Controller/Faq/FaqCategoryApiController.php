<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Faq;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\Transformers\FaqCategoryTransformer;
use Exception;
use Symfony\Component\Routing\Annotation\Route;

class FaqCategoryApiController extends APIController
{
    /**
     * @DplanPermissions("area_admin_faq")
     */
    #[Route(path: '/api/1.0/FaqCategory/', methods: ['GET'], name: 'dp_api_faq_category_list', options: ['expose' => true])]
    public function listAction(FaqHandler $faqHandler): APIResponse
    {
        try {
            $categoryTypeNames = FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY;
            $categories = $faqHandler->getCustomFaqCategoriesByNamesOrCustom($categoryTypeNames);

            return $this->renderCollection($categories, FaqCategoryTransformer::class);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
