<?php

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use BabDev\PagerfantaBundle\BabDevPagerfantaBundle;
use Bazinga\Bundle\JsTranslationBundle\BazingaJsTranslationBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Enqueue\Bundle\EnqueueBundle;
use Exercise\HTMLPurifierBundle\ExerciseHTMLPurifierBundle;
use FOS\ElasticaBundle\FOSElasticaBundle;
use FOS\JsRoutingBundle\FOSJsRoutingBundle;
use Hslavich\OneloginSamlBundle\HslavichOneloginSamlBundle;
use Intriro\Bundle\CsvBundle\IntriroCsvBundle;
use JMS\SerializerBundle\JMSSerializerBundle;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Nelmio\SecurityBundle\NelmioSecurityBundle;
use OldSound\RabbitMqBundle\OldSoundRabbitMqBundle;
use Sentry\SentryBundle\SentryBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Yectep\PhpSpreadsheetBundle\PhpSpreadsheetBundle;
use demosplan\DemosPlanCoreBundle\DemosPlanCoreBundle;
use demosplan\DemosPlanProcedureBundle\DemosPlanProcedureBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Liip\FunctionalTestBundle\LiipFunctionalTestBundle;
use Liip\TestFixturesBundle\LiipTestFixturesBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle;
use Bazinga\GeocoderBundle\BazingaGeocoderBundle;
use Rollerworks\Bundle\PasswordStrengthBundle\RollerworksPasswordStrengthBundle;
use EFrane\TusBundle\Bundle\TusBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use KnpU\OAuth2ClientBundle\KnpUOAuth2ClientBundle;
use Enqueue\ElasticaBundle\EnqueueElasticaBundle;
use DemosEurope\DemosplanAddon\DemosPlanAddonBundle;
/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */
return [
    FrameworkBundle::class                              => ['all' => true],
    BabDevPagerfantaBundle::class                              => ['all' => true],
    BazingaJsTranslationBundle::class               => ['all' => true],
    DoctrineBundle::class                               => ['all' => true],
    DoctrineMigrationsBundle::class                   => ['all' => true],
    EnqueueBundle::class                                                => ['all' => true],
    ExerciseHTMLPurifierBundle::class                      => ['all' => true],
    FOSElasticaBundle::class                                        => ['all' => true],
    FOSJsRoutingBundle::class                                      => ['all' => true],
    HslavichOneloginSamlBundle::class                      => ['all' => true],
    IntriroCsvBundle::class                                   => ['all' => true],
    JMSSerializerBundle::class                                    => ['all' => true],
    LexikJWTAuthenticationBundle::class           => ['all' => true],
    NelmioSecurityBundle::class                                  => ['all' => true],
    OldSoundRabbitMqBundle::class                              => ['all' => true],
    SentryBundle::class                                            => ['all' => true],
    StofDoctrineExtensionsBundle::class                  => ['all' => true],
    MakerBundle::class                                      => ['dev' => true],
    MonologBundle::class                                  => ['all' => true],
    SecurityBundle::class                                => ['all' => true],
    TwigBundle::class                                        => ['all' => true],
    PhpSpreadsheetBundle::class                            => ['all' => true],
    DemosPlanCoreBundle::class                           => ['all' => true],
    DemosPlanProcedureBundle::class                 => ['all' => true],
    DoctrineFixturesBundle::class                       => ['test' => true, 'dev' => true],
    LiipFunctionalTestBundle::class                          => ['test' => true],
    LiipTestFixturesBundle::class                              => ['test' => true],
    WebProfilerBundle::class                          => ['test' => true, 'dev' => true],
    DebugBundle::class                                      => ['dev' => true],
    SensioFrameworkExtraBundle::class               => ['all' => true],
    BazingaGeocoderBundle::class                                => ['all' => true],
    RollerworksPasswordStrengthBundle::class => ['all' => true],
    TusBundle::class                                           => ['all' => true],
    KnpMenuBundle::class                                         => ['all' => true],
    KnpUOAuth2ClientBundle::class                              => ['all' => true],
    EnqueueElasticaBundle::class                                => ['all' => true],
    DemosPlanAddonBundle::class                             => ['all' => true],
];
