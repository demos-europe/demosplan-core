<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadConsultationTokenData extends TestFixture implements DependentFixtureInterface
{
    final public const CONSULTATION_TOKEN = 'consultationToken';
    final public const CONSULTATION_TOKEN_2 = 'consultationToken2';

    public function load(ObjectManager $manager): void
    {
        $statement = $this->getReference(LoadStatementData::MANUAL_STATEMENT_IN_PUBLIC_PARTICIPATION_PHASE);
        $consultationToken = new ConsultationToken('12345678', $statement, false);

        $manager->persist($consultationToken);
        $this->setReference(self::CONSULTATION_TOKEN, $consultationToken);

        $statementWithToken = $this->getReference(LoadStatementData::TEST_STATEMENT_WITH_TOKEN);
        /** @var MailSend $mail */
        $mail = $this->getReference('testMailSend');
        $consultationToken2 = new ConsultationToken('abcdefgh', $statementWithToken, false);
        $consultationToken2->setSentEmail($mail);

        $manager->persist($consultationToken2);
        $this->setReference(self::CONSULTATION_TOKEN_2, $consultationToken2);

        $manager->flush();
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array
    {
        return [
            LoadStatementData::class,
        ];
    }
}
