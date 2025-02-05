<?php

namespace Tests\Core\Core\Unit\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\RpcTagAndTopicDeleter;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\Base\RpcApiTest;

/**
 * Class RpcTagAndTopicDeleterTest
 * this class tests {{ @link RpcTagAndTopicDeleter }}
 */
class RpcTagAndTopicDeleterTest extends RpcApiTest
{
    public function testDeleteTag(): void
    {
        $tag = TagFactory::createOne();
        $procedure = $tag->getTopic()->getProcedure();
        $tag->_save();
        $user = UserFactory::createOne();

//        $userRoleInCustomer = new UserRoleInCustomer();
//        $fpaRole = new Role();
//        $fpaRole->setCode(RoleInterface::PLANNING_AGENCY_ADMIN)
//            ->setName('Planning Agency Admin')->setGroupName('planner');
//        $userRoleInCustomer->setRole($fpaRole)->setUser($user->_real())->setCustomer($user->getCurrentCustomer());
//        $userRolesInCustomer = new ArrayCollection([$userRoleInCustomer]);
//        $user->setRoleInCustomers($userRolesInCustomer);

        $tagsOfProcedure = $procedure->getTags();
        self::assertContains($tag->_real(), $tagsOfProcedure);
        $responseBody = $this->executeRpcRequest(
            RpcTagAndTopicDeleter::DELETE_TAGS_METHOD,
            'someId',
            $user->_real(),
            [
                'ids' => [
                    [
                        'id' => $tag->getId(),
                        'type' => 'Tag',
                    ],
                ],
            ],
            $procedure
        );

        self::assertIsArray($responseBody);
        self::assertArrayHasKey('result', $responseBody[0]);
        self::assertTrue($responseBody[0]['result']);
    }

}
