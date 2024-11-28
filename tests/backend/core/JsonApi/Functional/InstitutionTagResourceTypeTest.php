<?php declare(strict_types=1);


namespace Tests\Core\JsonApi\Functional;


use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use Tests\Base\JsonApiTest;

class InstitutionTagResourceTypeTest extends JsonApiTest
{
    public function testList(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_CUSTOMER_MASTER);

        $tags = $this->executeListRequest(
            InstitutionTagResourceType::getName(),
            $user
        );

        self::assertCount(2, $tags['data']);
    }

    public function testDeleteRelated(string $fixtureNewsReferenceName): void
    {
        $testTag = InstitutionTagFactory::createOne();

    }
    public function testDeleteUnrelated(string $fixtureNewsReferenceName): void
    {
        /** @var News $singleNews */
        $singleNews = $this->fixtures->getReference($fixtureNewsReferenceName);


        $testTag = InstitutionTagFactory::createOne();



        $user = $this->getUserReference(LoadUserData::TEST_USER_CUSTOMER_MASTER);

        $procedure = $this->getEntityManager()->find(Procedure::class, $singleNews->getPId());

        $this->executeDeletionRequest(
            InstitutionTagResourceType::getName(),
            $singleNews->getId(),
            $user,
            $procedure
        );

        $count = $this->countEntries(News::class, ['ident' => $singleNews->getId()]);
        self::assertSame(0, $count);
    }

}
