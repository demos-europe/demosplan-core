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
use demosplan\DemosPlanCoreBundle\Event\GetBthgKompassAnswerEvent;
use demosplan\DemosPlanCoreBundle\Event\SetBthgKompassAnswerEvent;
use demosplan\DemosPlanDocumentBundle\Repository\BthgKompassAnswerRepository;

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
            SetBthgKompassAnswerEvent::class            => 'setBthgKompassAnswerEvent',
            GetBthgKompassAnswerEvent::class            => 'getBthgKompassAnswerEvent'
        ];
    }

    public function setBthgKompassAnswerEvent(SetBthgKompassAnswerEvent $event): void
    {
        $data = $event->getData();
        $statementId = $event->getStatementId();
        $bthgKompassAnswer = $this->bthgKompassAnswerRepository->getBthgKompassAnswerwithStatementId($statementId);
        if ('' === $data['bthg_kompass_answer']) {
            $bthgKompassAnswer->setBthgKompassAnswer(null);
        } else {
            /** @var BthgKompassAnswer $answer */
            $answer = $em->getReference(BthgKompassAnswer::class, $data['bthg_kompass_answer']);
            $statement->setBthgKompassAnswer($answer);
        }
    }

    public function getBthgKompassAnswerEvent(GetBthgKompassAnswerEvent $event): void
    {
        $statementId = $event->getStatementId();
        $answer = $this->bthgKompassAnswerRepository->getBthgKompassAnswerwithStatementId($statementId);
        $event->setAnswer($answer);
    }
}
