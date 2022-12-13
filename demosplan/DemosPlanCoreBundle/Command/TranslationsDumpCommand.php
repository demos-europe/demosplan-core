<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use DemosEurope\DemosplanAddon\Utilities\Json;
use EFrane\ConsoleAdditions\Batch\Batch;
use EFrane\ConsoleAdditions\Batch\StringCommandAction;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TranslationsDumpCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:translations:dump';
    protected static $defaultDescription = 'Dump translations into a ES6 importable JS module';

    protected function configure(): void
    {
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED);
    }

    /**
     * @return int|void|null
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tempDir = DemosPlanPath::getTemporaryPath(uniqid('dplan_translations', true));

        $tempDirForBazinga = quotemeta($tempDir);

        $action = new StringCommandAction(
            'bazinga:js-translation:dump --format=json --merge-domains --no-debug %s',
            $tempDirForBazinga
        );

        $batch = Batch::create($this->getApplication(), $output)->addAction($action);

        $batch->run();

        $translationsPath = $tempDir.DIRECTORY_SEPARATOR.'translations'.DIRECTORY_SEPARATOR;

        $files = (new Finder())
            ->files()
            ->in($translationsPath)
            ->notName('config.json');

        $config = Json::decodeToArray(file_get_contents($translationsPath.'config.json'));

        // put all languages into one translations array
        $languages = collect(iterator_to_array($files))
            ->map(
                static function (SplFileInfo $file) use ($translationsPath) {
                    $languageFile = $translationsPath.$file->getRelativePathname();

                    return Json::decodeToArray(file_get_contents($languageFile));
                }
            )
            ->toArray();

        // add the config vars to the resulting array
        $translations['fallback'] = $config['fallback'];
        $translations['defaultDomain'] = $config['defaultDomain'];

        foreach ($languages as $language) {
            $languageIdentifier = array_keys($language['translations'])[0];
            $translations['translations'][$languageIdentifier] = $language['translations'][$languageIdentifier];
        }

        $translationsJsonPath = DemosPlanPath::getRootPath('client/js/generated/translations.json');
        file_put_contents(
            $translationsJsonPath,
            Json::encode(
                $translations,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        if (file_exists($translationsJsonPath) && $output->isVerbose()) {
            $output->writeln('Succesfully wrote translations to core client bundle');
        }

        DemosPlanPath::recursiveRemovePath($tempDir);

        return 0;
    }
}
