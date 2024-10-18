<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentExportOptionsException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Illuminate\Support\Collection;
use JsonSerializable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A configuration container for export options of statements, original statements and fragments.
 *
 * The export configuration for the above mentioned entities is managed via a yaml dictionary
 * defining several options, as well as templates for different output formattings. This
 * dictionary file resides in `config\statement\assessment_export_options.yml`
 * and can be overriden in the project's statement bundle in a similar way to the existing
 * override mechanisms.
 */
class AssessmentExportOptions implements JsonSerializable
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    protected Collection $options;

    /**
     * Site areas with exports.
     *
     * @const array
     */
    final public const SECTIONS = [
        'original_statements',
        'assessment_table',
        'fragment_list',
    ];

    /**
     * Export formats.
     *
     * @const array
     */
    final public const FORMATS = [
        'docx',
        'pdf',
        'xlsx',
        'zip',
    ];

    /**
     * Kernel environment.
     *
     * @var string
     */
    protected $env;

    public function __construct(private readonly CacheInterface $cache, string $env)
    {
        $this->env = $env;
        $options = $this->loadOptions();

        $this->options = $this->mergeOptions($options);
    }

    /**
     * Loads the assessment export options from the given path.
     *
     * Project overrides, if existing, are handled.
     *
     * @return array
     *
     * @throws AssessmentExportOptionsException on invalid or unmergable option set
     */
    public function loadOptions()
    {
        $cacheKey = 'export_options_yml';

        return $this->cache->get($cacheKey, function (ItemInterface $item) {
            $optionsFiles = [];
            // local file only, no need for flysystem
            $fs = new Filesystem();

            // no need to evaluate assessment_export_options.yml folders dynamically as we can define allowed folders
            $projectPath = DemosPlanPath::getProjectPath('app/Resources/DemosPlanCoreBundle/config/statement/assessment_export_options.yml');

            if ($fs->exists($projectPath)) {
                $optionsFiles[] = $projectPath;
            }

            $optionsFiles[] = DemosPlanPath::getConfigPath('statement/assessment_export_options.yml');

            // uses local file, no need for flysystem
            $optionsYaml = collect($optionsFiles)->map(static fn ($filename) => file_exists($filename) ? file_get_contents($filename) : null)->filter(static fn ($yaml) => null !== $yaml && is_string($yaml))->all();

            $coreOptions = [];
            $projectOptions = [];

            switch (count($optionsYaml)) {
                case 1:
                    $coreOptions = Yaml::parse($optionsYaml[0]);
                    break;

                case 2:
                    $projectOptions = Yaml::parse($optionsYaml[0]);
                    $coreOptions = Yaml::parse($optionsYaml[1]);
                    break;
            }

            /*
             * The option validation and merging strategy here is basically the following:
             *
             * 1) make sure the core options are  valid
             * 2) try replacing with the project overrides
             * 3) make sure the new options are still valid
             */
            $this->validateOptionSet($coreOptions);

            $options = array_replace_recursive($coreOptions, $projectOptions);
            $this->validateOptionSet($options);

            $ttl = 3600;
            if ('dev' === $this->env) {
                $ttl = 10;
            }
            $item->expiresAfter($ttl);

            return $options;
        });
    }

    /**
     * Merge default options into option sets for sections.
     *
     * @return Collection merged options
     *
     * @throws AssessmentExportOptionsException if unmergable
     */
    public function mergeOptions(array $options): Collection
    {
        if (!array_key_exists('defaults', $options)) {
            throw AssessmentExportOptionsException::noDefaultsException();
        }

        return collect(
            array_flip(self::SECTIONS)
        )
            ->map(
                function ($section, $sectionName) use ($options) {
                    $mergedSectionOptions = $options['defaults'];

                    if (!array_key_exists($sectionName, $options)) {
                        throw AssessmentExportOptionsException::missingSectionException($sectionName);
                    }

                    if (is_array($options[$sectionName])) {
                        foreach ($options[$sectionName] as $sectionOption => $sectionValue) {
                            // if the value is scalar, we can just overwrite it, but if it's an array we need
                            // to check wether the items of the array are falsy, if so, this means they should
                            // disable the default, else they get overwritten/appended
                            if (is_array($sectionValue)) {
                                foreach ($sectionValue as $sectionValueArrayKey => $sectionValueArrayValue) {
                                    if (false === $sectionValueArrayValue) {
                                        unset($mergedSectionOptions[$sectionOption][$sectionValueArrayKey]);
                                    } else {
                                        $mergedSectionOptions[$sectionOption][$sectionValueArrayKey] = $sectionValueArrayValue;
                                    }
                                }
                            } else {
                                $mergedSectionOptions[$sectionOption] = $sectionValue;
                            }
                        }
                    }

                    return $mergedSectionOptions;
                }
            );
    }

    /**
     * Validate an option array.
     *
     * @param bool $isOverrideConfig must be true for project override configs
     *
     * @throws AssessmentExportOptionsException if invalid
     */
    public function validateOptionSet(array $options, $isOverrideConfig = false)
    {
        if (count($options) > 0 && !array_key_exists('defaults', $options)) {
            throw AssessmentExportOptionsException::noDefaultsException();
        }

        $missingSections = [];

        foreach (self::SECTIONS as $section) {
            if (!isset($options[$section]) || is_null($options[$section])) {
                array_push($missingSections, $section);
                continue;
            }

            foreach ($options[$section] as $sectionOptionName => $sectionOptionValue) {
                // validate wether options exist for an invalid option name (i.e. output format)
                if (!isset($options['defaults'][$sectionOptionName])) {
                    throw AssessmentExportOptionsException::undefinedOptionNameException($sectionOptionName, self::FORMATS);
                }

                // validate wether sections have configs for disabled defaults
                if (false === $options['defaults'][$sectionOptionName] && !is_null($sectionOptionValue)) {
                    throw AssessmentExportOptionsException::overridingDisabledDefaultsInSectionException($section, $sectionOptionName);
                }
            }
        }

        if (!$isOverrideConfig && count($missingSections) === count(self::SECTIONS)) {
            // there must at least be one section defined in a config
            throw AssessmentExportOptionsException::missingSectionException(implode(', ', $missingSections));
        }
    }

    /**
     * Return all options.
     *
     * This returns the defaults + the options for each section
     *
     * @return array
     */
    public function all()
    {
        return $this->options->all();
    }

    /**
     * Return options for a defined section.
     *
     * @param string $section
     *
     * @return array
     *
     * @throws AssessmentExportOptionsException if section is not defined
     */
    public function get($section)
    {
        if (!isset($this->options[$section])) {
            throw AssessmentExportOptionsException::undefinedSectionNameException($section);
        }

        return $this->options[$section];
    }

    public function jsonSerialize(): Collection
    {
        return $this->options;
    }

    public function toJson($options = 0): string
    {
        return Json::encode($this->jsonSerialize(), $options);
    }
}
