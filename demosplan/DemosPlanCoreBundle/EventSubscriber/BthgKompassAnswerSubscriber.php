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
use demosplan\DemosPlanCoreBundle\Event\statement\AdditionalDataEvent;
use demosplan\DemosPlanDocumentBundle\Repository\BthgKompassAnswerRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BthgKompassAnswerSubscriber implements EventSubscriberInterface
{
    private BthgKompassAnswerRepository $bthgKompassAnswerRepositor;

    private const ADDON_NAME = 'bthgKompassAnswerAddon';

    public function __construct(BthgKompassAnswerRepository $bthgKompassAnswerRepository)
    {
        $this->bthgKompassAnswerRepository = $bthgKompassAnswerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SetBthgKompassAnswerEvent::class               => 'setBthgKompassAnswerEvent',
            AdditionalDataEvent::class            => 'additionalStatementDataEvent',
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

    public function additionalStatementDataEvent(AdditionalDataEvent $event): void
    {
        if (!$event->getEntity() instanceof Statement) {

            return;
        }
        if (self::ADDON_NAME === $event->getAddon()) {
            $statement = $event->getEntity();
            /** @var BthgKompassAnswer $bthgKompassAnswer */
            $bthgKompassAnswer = $this->bthgKompassAnswerRepository->getBthgKompassAnswerwithStatementId($statement->getId());
            $data = [];
            $data['bthgKompassAnswer'] = $bthgKompassAnswer;
            $event->setData($data);

            //{
                // when no data than retrieve the BthgKompassAnswer that belong to statement but it can be also an update or an
                // add action and this will be hard to see this way !!
            //}
        }
    }
}
