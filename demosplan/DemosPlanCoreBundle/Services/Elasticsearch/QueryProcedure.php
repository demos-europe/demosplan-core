<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

use demosplan\DemosPlanCoreBundle\Exception\InvalidElasticsearchQueryConfigurationException;

/**
 * Query Procedures.
 */
class QueryProcedure extends AbstractQuery
{
    /**
     * Which procedures should be queried.
     *
     * @var string internal|external|planner
     */
    protected $scopes = ['external'];

    /**
     * Keep orgaId as it is needed to build query.
     *
     * @var string|null
     */
    protected $orgaId;

    /**
     * @var string|null
     */
    protected $userId;

    public function getEntity(): string
    {
        return 'procedure';
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @throws InvalidElasticsearchQueryConfigurationException
     */
    protected function isValidConfiguration(array $queryDefinition): bool
    {
        // set definition from config
        if (!array_key_exists('procedure', $queryDefinition)) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        if (
            !array_key_exists('filter', $queryDefinition['procedure']) ||
            !array_key_exists('search', $queryDefinition['procedure']) ||
            !array_key_exists('sort', $queryDefinition['procedure']) ||
            !array_key_exists('sort_default', $queryDefinition['procedure']) ||
            !array_key_exists('internal', $queryDefinition['procedure']['sort_default'])
        ) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        return true;
    }

    /**
     * @return string
     */
    public function getOrgaId(): ?string
    {
        return $this->orgaId;
    }

    /**
     * @param string $orgaId
     */
    public function setOrgaId($orgaId): void
    {
        $this->orgaId = $orgaId;
    }

    /**
     * @param string $scope
     */
    public function addScope($scope): AbstractQuery
    {
        $scopes = collect($this->scopes);
        // when planner scope is added (sic!) external scope should not be available any more
        if (self::SCOPE_PLANNER === $scope && $scopes->contains(self::SCOPE_EXTERNAL)) {
            // reset existing scopes without external scope
            $this->setScopes(
                $scopes->filter(function ($value) {
                    return self::SCOPE_EXTERNAL !== $value;
                })->toArray()
            );
        }

        return parent::addScope($scope);
    }

    /**
     * get only Fields to be used in interface.
     *
     * @return FilterDisplay[]
     */
    public function getInterfaceFilters(): array
    {
        $availableFilters = collect(parent::getInterfaceFilters());

        $availableFilters->map(function ($element): void {
            /** @var FilterDisplay $element */
            $values = $element->getValues();
            $translatedValues = [];
            foreach ($values as $value) {
                if ('phase' === $element->getName()) {
                    $name = $this->globalConfig->getPhaseNameWithPriorityInternal($value['value']);
                    $value['label'] = $this->translator->trans($name);
                } elseif ('publicParticipationPhase' === $element->getName()) {
                    $name = $this->globalConfig->getPhaseNameWithPriorityExternal($value['value']);
                    $value['label'] = $this->translator->trans($name);
                } elseif (in_array($element->getName(), ['phasePermissionset', 'publicParticipationPhasePermissionset'])) {
                    /*
                     * Possible values:
                     * procedure.filter.permissionset.value.hidden
                     * procedure.filter.permissionset.value.read
                     * procedure.filter.permissionset.value.write
                     */
                    $value['label'] = $this->translator->trans('procedure.filter.permissionset.value.'.$value['value']);
                } else {
                    $value['label'] = $this->translator->trans($value['label']);
                }
                $translatedValues[] = $value;
            }
            $translatedValues = collect($translatedValues)->sortBy('label')->values()->toArray();
            $element->setValues($translatedValues);
        });

        return $availableFilters->toArray();
    }
}
