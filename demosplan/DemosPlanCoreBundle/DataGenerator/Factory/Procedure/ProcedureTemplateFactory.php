<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;


use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

final class ProcedureTemplateFactory extends ProcedureFactory
{
    private GlobalConfigInterface $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        parent::__construct($globalConfig);
        $this->globalConfig = $globalConfig;
    }

    protected function getDefaults(): array
    {
        $defaults = parent::getDefaults();

        $defaults['externalName'] = 'default procedure Template';
        $defaults['master'] = true;
        $defaults['masterTemplate'] = false;
        $defaults['name'] = 'default procedure Template';
        $defaults['phase'] = $this->globalConfig->getInternalPhaseKeys('hidden')[0];
        $defaults['publicParticipationPhase'] = $this->globalConfig->getExternalPhaseKeys('hidden')[0];

        return $defaults;
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Procedure::class;
    }

    public function asMasterTemplate(): self
    {
        return $this->addState(['masterTemplate' => true]);
    }

}
