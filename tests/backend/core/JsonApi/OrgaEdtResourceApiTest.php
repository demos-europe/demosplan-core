<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * Covers the EDT based 2.0 API (used by the organisation list). The API Platform 3.0 Orga resource
 * is covered by {@link OrgaResourceApiTest}.
 */
class OrgaEdtResourceApiTest extends AbstractApiTest
{
    private const ORGA_ROUTE = '/api/2.0/Orga/';

    public function testReturnsTechnicalNameNextToDisplayNameWithPermission(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $orga = $user->getOrga();
        $orga->setName('Display name of the orga');
        $orga->setTechnicalName('Official name of the orga');
        $this->getEntityManager()->flush();
        $this->enablePermissions(['field_organisation_technical_name']);

        $response = $this->sendRequest(
            self::ORGA_ROUTE.$orga->getId().'?fields[Orga]=name,technicalName',
            'GET',
            $user,
            null
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $attributes = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['attributes'];
        self::assertSame('Display name of the orga', $attributes['name']);
        self::assertSame('Official name of the orga', $attributes['technicalName']);
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
