<?php declare(strict_types=1);


namespace Tests\Core\Faq\Functional;


use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\SupportContactFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use Tests\Base\FunctionalTestCase;

class SupportContactTest extends FunctionalTestCase
{
    /**
     * @var FaqHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomerHandler::class);
    }
    public function testCreateSupportContact()
    {
        $testCustomer = CustomerFactory::createOne();
        $createdContact = $this->sut->createContact(
            $testCustomer->object(),
            'title',
            '79898234759',
            'emailAddress@mail.de',
            'this is a text'
        );

        static::assertSame($createdContact->getCustomer(), $testCustomer->object());
        static::assertContains($createdContact->object, $testCustomer->getContacts());
    }

    public function testUpdateSupportContact()
    {
        $testCustomer1 = CustomerFactory::createOne();
        $testCustomer2 = CustomerFactory::createOne();
        $testContact = SupportContactFactory::createOne(['customer' => $testCustomer1]);

        $testContact->setCustomer($testCustomer2);
        $this->sut->updateContact($testContact);

        static::assertSame($testCustomer2->object, $testContact->getCustomer());
    }

    public function testDeleteSupportContact()
    {
        $testCustomer = CustomerFactory::createOne();
        $testCustomerId = $testCustomer->getId();
        $testContact = SupportContactFactory::createOne(['customer' => $testCustomer]);
        $testContactId = $testContact->getId();

        $this->sut->deleteSupportContact($testCustomer);

        static::assertNull($this->find(SupportContact::class, $testContactId));
        static::assertInstanceOf(Customer::class, $this->find(Customer::class, $testCustomerId));
    }

    public function testCascadeDeleteSupportContactOnDeleteCustomer()
    {
        self::markTestSkipped('Deletion of customer not implemented yet.');

        $testCustomer = CustomerFactory::createOne();
        $testCustomerId = $testCustomer->getId();
        $testContact = SupportContactFactory::createOne(['customer' => $testCustomer]);
        $testContactId = $testContact->getId();

        $this->sut->deleteCustomer($testCustomer);

        static::assertNull($this->find(Customer::class, $testCustomerId));
        static::assertNull($this->find(SupportContact::class, $testContactId));
    }
}
