<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoadProcedureData extends ProdFixture implements DependentFixtureInterface
{
    /** @var TranslatorInterface */
    protected $translator;
    /** @var GlobalConfigInterface */
    protected $globalConfig;
    /** @var PermissionsInterface */
    protected $permissions;
    /** @var ProcedureHandler */
    protected $procedureHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        GlobalConfigInterface $globalConfig,
        PermissionsInterface $permissions,
        ProcedureHandler $procedureHandler,
        TranslatorInterface $translator,
    ) {
        parent::__construct($entityManager);
        $this->translator = $translator;
        $this->globalConfig = $globalConfig;
        $this->permissions = $permissions;
        $this->procedureHandler = $procedureHandler;
    }

    public function load(ObjectManager $manager): void
    {
        $masterProcedurePhase = 'configuration';
        $anonymousUser = new AnonymousUser();
        $this->permissions->initPermissions($anonymousUser);

        $procedureMaster = new Procedure();
        $procedureMaster->setName('Master');
        $procedureMaster->setOrga($this->getReference('orga_demos'));
        $procedureMaster->setOrgaName('DEMOS plan GmbH');
        $procedureMaster->setPhase($masterProcedurePhase);
        $procedureMaster->setPublicParticipationPhase($masterProcedurePhase);
        $procedureMaster->setMaster(true);
        $procedureMaster->setMasterTemplate(true);
        $procedureMaster->setAgencyMainEmailAddress('ihre@emailadresse.de');
        $procedureMaster->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedureMaster));
        $slug = new Slug('master');
        $procedureMaster->addSlug($slug);
        $procedureMaster->setCurrentSlug($slug);
        $procedureSettingsMaster = new ProcedureSettings();
        $procedureSettingsMaster->setPlanningArea('');
        $procedureSettingsMaster->setProcedure($procedureMaster);
        $procedureSettingsMaster->setPlanEnable(true);
        $procedureMaster->setSettings($procedureSettingsMaster);

        $manager->persist($procedureMaster);
        $manager->flush();

        // create GisLayerCategory for MasterBlueprint
        $gisLayerCategoryMaster = new GisLayerCategory();
        $gisLayerCategoryMaster->setName('rootGisLayer');
        $gisLayerCategoryMaster->setProcedure($procedureMaster);
        $manager->persist($gisLayerCategoryMaster);

        // Create GisLayer for MasterBlueprint
        $gisLayer = new GisLayer();
        $gisLayer->setName('basemap');
        $gisLayer->setUrl('https://sgx.geodatenzentrum.de/wms_basemapde');
        $gisLayer->setLayers('de_basemapde_web_raster_farbe');
        $gisLayer->setType('base');
        $gisLayer->setPrint(true);
        $gisLayer->setEnabled(true);
        $gisLayer->setDefaultVisibility(true);
        $gisLayer->setProcedureId($procedureMaster->getId());
        $gisLayer->setCategory($gisLayerCategoryMaster);
        $manager->persist($gisLayer);

        // Create ContextHelp for GisLayer
        $contextHelp = new ContextualHelp();
        $contextHelp->setText('');
        $contextHelp->setKey('gislayer.'.$gisLayer->getId());
        $manager->persist($contextHelp);

        // Add ContextHelp to GisLayer
        $gisLayer->setContextualHelp($contextHelp);

        // fill master with mandatory standard data to be copied on procedure creation

        $element = new Elements();
        $element->setCategory('statement');
        $element->setTitle($this->globalConfig->getElementsStatementCategoryTitle());
        $element->setText($this->translator->trans('elements.statement.global.explanation'));
        $element->setProcedure($procedureMaster);
        $element->setOrder(1);
        $manager->persist($element);

        $element = new Elements();
        $element->setCategory('statement');
        $element->setTitle($this->translator->trans('indicationerror'));
        $element->setText($this->translator->trans('elements.statement.indicationerror.explanation'));
        $element->setProcedure($procedureMaster);
        $element->setOrder(2);
        $manager->persist($element);

        $element = new Elements();
        $element->setCategory('map');
        $element->setTitle($this->translator->trans('drawing'));
        $element->setProcedure($procedureMaster);
        $element->setOrder(3);
        $element->setEnabled(true);
        $manager->persist($element);

        // add default Boilerplate categories
        foreach (['news.notes', 'email', 'consideration'] as $title) {
            $category = new BoilerplateCategory();
            $category->setProcedure($procedureMaster);
            $category->setTitle($title);
            $category->setDescription('');
            $manager->persist($category);
        }

        $this->setReference('procedureMaster', $procedureMaster);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadUserData::class,
        ];
    }
}
