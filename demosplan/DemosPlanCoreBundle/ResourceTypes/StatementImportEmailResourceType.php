<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementImportEmail\StatementImportEmail;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Parsedown;

/**
 * @template-extends DplanResourceType<StatementImportEmail>
 *
 * @property-read End                           $creationDate
 * @property-read ProcedureResourceType         $procedure
 * @property-read UserResourceType              $forwardingUser
 * @property-read End                           $forwarderEmailAddress
 * @property-read End                           $from
 * @property-read End                           $subject
 * @property-read End                           $plainTextContent
 * @property-read End                           $htmlTextContent
 * @property-read End                           $rawEmailText
 * @property-read OriginalStatementResourceType $createdStatements
 */
class StatementImportEmailResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'StatementImportEmail';
    }

    public function getEntityClass(): string
    {
        return StatementImportEmail::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_admin_import',
            'feature_import_statement_via_email'
        );
    }

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            ...$this->procedure->id
        );
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->creationDate)->readable(
                false,
                function (StatementImportEmail $email): string {
                    return $this->formatDate($email->getCreationDate());
                }
            ),
            $this->createToOneRelationship($this->forwardingUser)->readable(),
            $this->createAttribute($this->subject)->readable(),
            $this->createAttribute($this->from)->readable(),
            $this->createAttribute($this->plainTextContent)->readable(false, static function (StatementImportEmail $statementImportEmail): string {
                return (new Parsedown())->text($statementImportEmail->getPlainTextContent());
            }),
            $this->createAttribute($this->htmlTextContent)->readable(),
            $this->createToManyRelationship($this->createdStatements)->readable(),
            $this->createAttribute($this->forwarderEmailAddress),
            $this->createAttribute($this->rawEmailText),
        ];
    }
}
