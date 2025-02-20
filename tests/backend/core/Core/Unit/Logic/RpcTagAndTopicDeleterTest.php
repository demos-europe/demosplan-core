<?php

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\SlugFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\RpcTagAndTopicDeleter;
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

    public function testDeleteTopic(): void
    {
        $topic = TagTopicFactory::createOne();
        $procedure = $topic->getProcedure();
        $topic->_save();
        $user = $this->loginTestUser();

        $topicsOfProcedure = $procedure->getTopics();
        self::assertContains($topic->_real(), $topicsOfProcedure);

        $responseBody = $this->executeRpcRequest(
            RpcTagAndTopicDeleter::DELETE_TAGS_METHOD,
            'someId',
            $user,
            [
                'ids' => [
                    [
                        'id' => $topic->getId(),
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

    public function testMeeting(): void
    {
        // this works but why
        $procedure = ProcedureFactory::createOne();
        $procedureId = $procedure->getId();
//        $all = $this->getEntityManager()->getRepository(Procedure::class)->findAll();
        $procedure = $this->getEntityManager()->find(Procedure::class, $procedureId);
        $this->getEntityManager()->persist($procedure);
        $this->getEntityManager()->flush();

        $reicht = 5;
    }

}
