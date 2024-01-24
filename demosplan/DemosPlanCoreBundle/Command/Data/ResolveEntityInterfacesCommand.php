<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


//To run the command bin/console dplan:data:resolve-entity-interfaces
class ResolveEntityInterfacesCommand extends CoreCommand
{

    public static $defaultName = 'dplan:data:resolve-entity-interfaces';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var SymfonyStyle
     */
    protected $output;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            'Command ResolveEntityInterfacesCommand start',
            '============',
            '',
        ]);

        //Get through the entities of DemosPlan and ge their interfaces
        //Then check if the interfaces belong to DemosPlanAddon and if they contain the class name

        $entities = array();
        $meta = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($meta as $m) {

            /*$output->writeln([
                "CLASS " . $m->getName(),
                true,
                '',
            ]);*/


            $interfaces = $m->getReflectionClass()->getInterfaces();
            foreach ($interfaces as $in) {

                if ($in->getNamespaceName() === 'DemosEurope\DemosplanAddon\Contracts\Entities' && str_contains($in->getShortName(), $m->getReflectionClass()->getShortName())) {

                    $entities[] = $in->getName() . ': ' . $m->getName();
                    $output->writeln([
                        $in->getName() . ': ' . $m->getName(),
                        '',
                    ]);
                    /*$output->writeln([
                        "INTERFACE " . $in->getName(),
                        true,
                        '',
                    ]);*/
                }


            }

        }

        //$this->updateYamlDoctrineFile($entities);

        $output->writeln([
            'Command ResolveEntityInterfacesCommand finish',
            '============',
            '',
        ]);

        return Command::SUCCESS;

    }

    private function updateYamlDoctrineFile($entities)
    {

        $doctrineFile = DemosPlanPath::getConfigPath('packages/doctrine.yaml');
        $yaml = Yaml::parse(file_get_contents($doctrineFile));
        $srcData = $yaml['doctrine']['orm']['resolve_target_entities'];
        $yaml['doctrine']['orm']['resolve_target_entities'] = $entities;
        $new_yaml = Yaml::dump($yaml, 5);
        file_put_contents($doctrineFile, $new_yaml);

    }

}
