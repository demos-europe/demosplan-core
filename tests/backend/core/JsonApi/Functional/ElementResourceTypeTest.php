<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\ElementsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use Tests\Base\JsonApiTest;

class ElementResourceTypeTest extends JsonApiTest
{
    private \demosplan\DemosPlanCoreBundle\Entity\User\User|\Zenstruck\Foundry\Persistence\Proxy $user;
    private Role|\Zenstruck\Foundry\Persistence\Proxy $role;
    private $customer;

    public function testCreate(): void
    {
    }

    public function testCreateReportOnUpdate(): void
    {
        $this->setUpHttpClient();
        $this->user = UserFactory::createOne();
        $this->customer = CustomerFactory::createOne();
        $this->role = RoleFactory::createOne([
            'name'      => Role::PLANNING_AGENCY_ADMIN,
            'code'      => Role::PLANNING_AGENCY_ADMIN,
            'groupCode' => Role::GLAUTH,
            'groupName' => Role::GLAUTH,
        ]);
        $this->user->setDplanroles([$this->role->_real()]);
        $this->user->_save();
        $this->user->setCurrentCustomer($this->customer->_real());
        $this->user->_save();
        $this->tokenStorage = $this->getContainer()->get('security.token_storage');
        $this->logIn($this->user->_real());

        $this->enablePermissions(['feature_admin_element_edit', 'feature_json_api_update', 'area_admin_single_document']);
        $testElement = ElementsFactory::createOne()->_enableAutoRefresh();

        static::assertInstanceOf(Elements::class, $this->find(Elements::class, $testElement->getId()));

        $testElement = $testElement->_real();
        /** @var Elements $testElement */
        $enabledBeforeUpdate = $testElement->getEnabled();
        $user = $this->user->_real();

        $result = $this->executeUpdateRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $testElement->getId(),
            $user,
            [
                'data' => [
                    'id'         => $testElement->getId(),
                    'type'       => PlanningDocumentCategoryResourceType::getName(),
                    'attributes' => [
                        'enabled' => !$enabledBeforeUpdate,
                    ],
                ],
            ],
            $testElement->getProcedure(),
            204
        );

        self::assertNull($result);
        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'           => 'element',
                'category'        => ReportEntry::CATEGORY_UPDATE,
                'identifierType'  => 'procedure',
                'identifier'      => $testElement->getProcedure()->getId(),
            ]
        );

        $updatedElement = $this->find(Elements::class, $testElement->getId());

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertElementReportEntryMessageKeys($messageArray);
        $this->assertElementReportEntryMessageValues($updatedElement, $messageArray);
    }
}
