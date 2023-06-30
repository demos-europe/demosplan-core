<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Transformers\SlugDraftTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\SlugDraftValueObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SlugDraftApiController.
 */
#[Route(path: '/api/1.0/slug-draft', name: 'dp_api_slug_draft_', options: ['expose' => true])]
class SlugDraftApiController extends APIController
{
    /**
     * Currently this route is only needed when editing procedure or orga settings
     * but can be used by anyone as it has no security implications.
     * However, that changes if the route starts to support an
     * "is-slug-already-taken"-functionality, in this case adjust the permissions
     * accordingly.
     *
     * @DplanPermissions("feature_short_url")
     *
     * @return APIResponse|JsonResponse
     */
    #[Route(methods: ['POST'], name: 'create')]
    public function createAction(SlugDraftTransformer $slugDraftTransformer)
    {
        $slugDraftType = $slugDraftTransformer->getType();

        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }

        $slugDrafts = $this->requestData[$slugDraftType];
        if (1 !== (is_countable($slugDrafts) ? count($slugDrafts) : 0)) {
            throw new BadRequestException('exactly one slug-draft resource must be provided in the request');
        }

        /** @var ResourceObject $slugDraftResourceObject */
        $slugDraftResourceObject = $this->requestData->getFirst($slugDraftType);

        $slugifier = new Slugify();
        $id = $slugDraftResourceObject['id'];
        $originalValue = $slugDraftResourceObject['attributes.originalValue'];
        $slugifiedValue = $slugifier->slugify($originalValue);
        $slugDraft = new SlugDraftValueObject();
        $slugDraft->setId($id);
        $slugDraft->setOriginalValue($originalValue);
        $slugDraft->setSlugifiedValue($slugifiedValue);
        $slugDraft->lock();

        return $this->renderItem($slugDraft, SlugDraftTransformer::class, Response::HTTP_CREATED);
    }
}
