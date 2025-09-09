<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Event;


use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use Webmozart\Assert\Assert;

class CreateReportEntryEvent extends DPlanEvent
{
    public function __construct(
        protected readonly CoreEntity $entity,
        protected readonly string $category,
    ) {
        Assert::oneOf($category, [ReportEntry::CATEGORY_ADD, ReportEntry::CATEGORY_UPDATE, ReportEntry::CATEGORY_DELETE]);
    }

    public function getEntity(): CoreEntity
    {
        return $this->entity;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
