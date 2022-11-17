<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\Document\BthgKompassAnswer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\SetBthgKompassAnswerEvent;
use demosplan\DemosPlanCoreBundle\Event\statement\AdditionalStatementDataEvent;
use demosplan\DemosPlanDocumentBundle\Repository\BthgKompassAnswerRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BthgKompassAnswerSubscriber implements EventSubscriberInterface
{
    /**
     * @var BthgKompassAnswerRepository
     */
    private $bthgKompassAnswerRepositor;

    public function __construct(BthgKompassAnswerRepository $bthgKompassAnswerRepository)
    {
        $this->bthgKompassAnswerRepository = $bthgKompassAnswerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SetBthgKompassAnswerEvent::class               => 'setBthgKompassAnswerEvent',
            AdditionalStatementDataEvent::class            => 'additionalStatementDataEvent',
        ];
    }

    public function setBthgKompassAnswerEvent(SetBthgKompassAnswerEvent $event): void
    {
        // $em = $this->getEntityManager();
        // $data = $event->getData();
        // $statement = $event->getStatement();
        // /** @var BthgKompassAnswer $bthgKompassAnswer */
        // $bthgKompassAnswer = $this->bthgKompassAnswerRepository->getBthgKompassAnswerwithStatementId($statement->getId());
        // if ($bthgKompassAnswer !== null) {
        //    if ('' !== $data['bthg_kompass_answer']) {
        //        $bthgKompassAnswer->addStatement($statement);
        //    }
        // }
    }

    public function additionalStatementDataEvent(AdditionalStatementDataEvent $event): void
    {
        $data = $event->getData();
        $statement = $event->getStatement();
        /** @var BthgKompassAnswer $bthgKompassAnswer */
        $bthgKompassAnswer = $this->bthgKompassAnswerRepository->getBthgKompassAnswerwithStatementId($statement->getId());
        if (null === $data) {
            if (null !== $bthgKompassAnswer) {
                $event->setAnswer($bthgKompassAnswer);
            }
        } else {
            // when no data than retrieve the BthgKompassAnswer that belong to statement but it can be also an update or an
            // add action and this will be hard to see this way !!
        }
    }
}
