<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\MailTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerUrlGenerator;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\FunctionalTestCase;

/**
 * The daily "assigned tasks" mail is created without a request, so its links must not depend on
 * the request context but on the customer of the procedure.
 */
class AssignedTaskNotificationLinksTest extends FunctionalTestCase
{
    protected ?EntityContentChangeService $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(EntityContentChangeService::class);
        // the template is part of the production data only, the service silently skips the mail without it
        MailTemplateFactory::createOne([
            'label'    => 'dm_stellungnahme',
            'language' => 'de_DE',
            'title'    => '${mailsubject}',
            'content'  => '${mailbody}',
        ]);
    }

    public function testMailLinksPointToTheCustomerOfTheProcedure(): void
    {
        // Arrange
        $customerA = CustomerFactory::createOne(['subdomain' => 'customer-a'])->_real();
        $customerB = CustomerFactory::createOne(['subdomain' => 'customer-b'])->_real();
        $this->createRecentlyAssignedSegment($customerA, $this->createAssignee('assignee-a@example.org'));
        $this->createRecentlyAssignedSegment($customerB, $this->createAssignee('assignee-b@example.org'));

        // Act
        $this->sut->sendAssignedTaskNotificationMails(Segment::class);

        // Assert
        $mailTextA = $this->getMailTextFor('assignee-a@example.org');
        $mailTextB = $this->getMailTextFor('assignee-b@example.org');
        self::assertStringContainsString('://customer-a.', $mailTextA);
        self::assertStringNotContainsString('customer-b', $mailTextA);
        self::assertStringContainsString('://customer-b.', $mailTextB);
        self::assertStringNotContainsString('customer-a', $mailTextB);
    }

    public function testEnvironmentLabelOfTheContextHostIsKept(): void
    {
        // Arrange
        $customer = CustomerFactory::createOne(['subdomain' => 'customer-a'])->_real();
        $router = $this->getContainer()->get(RouterInterface::class);
        $originalHost = $router->getContext()->getHost();
        $router->getContext()->setHost('environment.base-domain.test');
        $sut = new CustomerUrlGenerator(
            $this->getContainer()->get(CustomerRepository::class),
            $this->getContainer()->get(GlobalConfigInterface::class),
            new NullLogger(),
            $router
        );

        // Act
        $url = $sut->generate($customer, 'DemosPlan_user_portal');
        $router->getContext()->setHost($originalHost);

        // Assert: "environment" is no customer, so the customer is prefixed instead of replacing it
        self::assertStringContainsString('://customer-a.environment.base-domain.test/', $url);
    }

    /**
     * The user factory switches the assigned task notification off by default.
     */
    private function createAssignee(string $email): User
    {
        $user = UserFactory::createOne(['email' => $email, 'login' => $email])->_real();
        $user->setAssignedTaskNotification(true);
        $this->getEntityManager()->flush();

        return $user;
    }

    /**
     * Segments that never had their assignee changed are only part of the mail if they were created recently.
     */
    private function createRecentlyAssignedSegment(Customer $customer, User $assignee): void
    {
        SegmentFactory::createOne([
            'procedure' => ProcedureFactory::createOne(['customer' => $customer])->_real(),
            'assignee'  => $assignee,
            'created'   => new DateTime(),
        ]);
    }

    private function getMailTextFor(string $recipient): string
    {
        $mails = $this->getEntityManager()->getRepository(MailSend::class)->findBy(['to' => $recipient]);
        self::assertCount(1, $mails, "Expected exactly one mail for {$recipient}");

        return (string) $mails[0]->getContent();
    }
}
