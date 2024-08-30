<?php
#declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Events\StatementUpdatedEventInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementPart;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementUpdatedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementPartsDivider;
use demosplan\DemosPlanCoreBundle\Repository\StatementPartRepository;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use DOMDocument;
use DOMXPath;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StatementSegmentsSynchronizerListener implements EventSubscriberInterface
{
    public function __construct(private readonly StatementPartsDivider $statementPartsDivider, private readonly StatementPartRepository $statementPartRepository)
    {
    }

    public function postLoad(StatementUpdatedEvent $event)
    {
        $statementParts = $this->statementPartsDivider->getStatementParts($event->getStatement());
        $this->statementPartRepository->upsert($statementParts);
    }
    public function preUpdate(Statement $statement, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('memo')) {
            $old = $event->getOldValue('memo');
            $new = $event->getNewValue('memo');
            $this->getSegmentsFromStatement($statement);
        }

        //dd('here');
    }


    public static function getSubscribedEvents(): array
    {
        return [
            StatementUpdatedEventInterface::class  => 'postLoad',
        ];
    }
}
