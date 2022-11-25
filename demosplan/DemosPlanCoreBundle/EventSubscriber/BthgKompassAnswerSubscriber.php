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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\BthgKompassAnswer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\statement\AdditionalDataEvent;
use demosplan\DemosPlanDocumentBundle\Repository\BthgKompassAnswerRepository;

class BthgKompassAnswerSubscriber implements EventSubscriberInterface
{
    private BthgKompassAnswerRepository $bthgKompassAnswerRepository;

    public function __construct(BthgKompassAnswerRepository $bthgKompassAnswerRepository)
    {
        $this->bthgKompassAnswerRepository = $bthgKompassAnswerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdditionalDataEvent::class                     => 'additionalStatementDataEvent',
        ];
    }

    public function additionalStatementDataEvent(AdditionalDataEvent $event): void
    {
        $statement = $event->getEntity();
        if (!$statement instanceof Statement) {
            return;
        }
        $data = $event->getData();
        /** @var BthgKompassAnswer $bthgKompassAnswer **/
        $bthgKompassAnswer = $this->bthgKompassAnswerRepository->findOneBy([
            'statements' => $statement->getId(),
        ]);
        $url = null === $bthgKompassAnswer ? null : $bthgKompassAnswer->getUrl();
        $data['bthgKompassAnswer']['url'] = $url;
        $event->setData($data);
    }
}
