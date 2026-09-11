<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use DateTime;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ScheduledExport',
    operations: [
        new GetCollection(uriTemplate: '/ScheduledExport', paginationEnabled: false),
        new Get(uriTemplate: self::ITEM_URI_TEMPLATE),
        new Post(
            uriTemplate: '/ScheduledExport',
            validationContext: ['groups' => ['scheduledExport:create']],
            read: false,
            processor: ScheduledExportProcessor::class,
        ),
        new Patch(
            uriTemplate: self::ITEM_URI_TEMPLATE,
            validationContext: ['groups' => ['scheduledExport:update']],
            processor: ScheduledExportProcessor::class,
        ),
        new Delete(
            uriTemplate: self::ITEM_URI_TEMPLATE,
            output: false,
            read: false,
            deserialize: false,
            processor: ScheduledExportProcessor::class,
        ),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: ScheduledExportProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class ScheduledExportResource
{
    private const ITEM_URI_TEMPLATE = '/ScheduledExport/{id}';

    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: true)]
    #[Assert\NotBlank(message: 'A frequency is required to create a scheduled export.', groups: ['scheduledExport:create'])]
    #[Assert\NotBlank(allowNull: true, message: 'A frequency may not be empty.', groups: ['scheduledExport:update'])]
    #[Assert\Choice(choices: ['daily', 'weekly', 'monthly'], message: 'Frequency must be one of: daily, weekly, monthly.')]
    public ?string $frequency = null;

    /**
     * Only meaningful when {@see $frequency} is weekly. Never required by a validation group here,
     * since whether it is needed depends on frequency's value rather than on which operation is
     * running - that cross-field check happens in the processor instead.
     */
    #[ApiProperty(readable: true, writable: true)]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'Weekday must be between 1 (Monday) and 7 (Sunday).')]
    public ?int $weekday = null;

    /**
     * Only meaningful when {@see $frequency} is monthly. Same reasoning as {@see $weekday}: always
     * optional here, required-when-monthly is enforced in the processor.
     */
    #[ApiProperty(readable: true, writable: true)]
    #[Assert\Choice(choices: [1, 5, 10, 15, 20, 25], message: 'Day of month must be one of the allowed presets.')]
    public ?int $dayOfMonth = null;

    #[ApiProperty(readable: true, writable: true)]
    #[Assert\NotBlank(message: 'Parameters are required to create a scheduled export.', groups: ['scheduledExport:create'])]
    #[Assert\NotBlank(allowNull: true, message: 'Parameters may not be empty.', groups: ['scheduledExport:update'])]
    public ?string $parameters = null;

    #[ApiProperty(readable: false, writable: false)]
    public string $procedureId = '';

    #[ApiProperty(readable: false, writable: false)]
    public string $parametersHash = '';

    #[ApiProperty(readable: false, writable: false)]
    public string $userId = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $nextRunAt = '';

    /**
     * Null until the schedule has actually fired once.
     */
    #[ApiProperty(readable: true, writable: false)]
    public ?string $lastRunAt = null;

    public static function fromEntity(ExportSchedule $exportSchedule): self
    {
        $lastRunAt = $exportSchedule->getLastRunAt();

        $resource = new self();
        $resource->id = $exportSchedule->getId();
        $resource->frequency = $exportSchedule->getFrequency();
        $resource->weekday = $exportSchedule->getWeekday();
        $resource->dayOfMonth = $exportSchedule->getDayOfMonth();
        $resource->parameters = $exportSchedule->getParameters();
        $resource->procedureId = $exportSchedule->getProcedureId();
        $resource->parametersHash = $exportSchedule->getParametersHash();
        $resource->userId = $exportSchedule->getUserId();
        $resource->nextRunAt = $exportSchedule->getNextRunAt()->format(DateTime::ATOM);
        $resource->lastRunAt = $lastRunAt instanceof DateTime ? $lastRunAt->format(DateTime::ATOM) : null;

        return $resource;
    }
}
