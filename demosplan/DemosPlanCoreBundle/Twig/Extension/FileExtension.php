<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FileExtension extends ExtensionBase
{
    /**
     * @var FileService
     */
    protected $fileService;

    public function __construct(ContainerInterface $container, private readonly Environment $twig, FileService $fileService)
    {
        parent::__construct($container);
        $this->fileService = $fileService;
    }

    /**
     * Get Twig Filters.
     *
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('getFile', $this->getFileFilter(...)),
            new TwigFilter('humanFilesize', $this->formatHumanFilesize(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('fileupload', $this->fileupload(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Get File Information.
     *
     * @param string $text
     * @param string $key
     * @param string $scale
     *
     * @return string
     */
    public function getFileFilter($text, $key, $scale = 'KB')
    {
        return $this->fileService->getInfoFromFileString(
            $text,
            $key,
            $scale
        );
    }

    /**
     * // TODO: Combine this with convertSize.
     *
     * @param int|float $bytes
     * @param int       $precision
     *
     * @return string
     */
    public function formatHumanFilesize($bytes, $precision = 2)
    {
        return $this->fileService->formatHumanFilesize($bytes, $precision);
    }

    /**
     * Get bytes from phpini shortcut modifiers.
     *
     * @param int|string $val any PHP ini-style filesize
     *
     * @return int|float
     */
    public function convertPhpiniShorthandvaluesToBytes($val)
    {
        // numeric inputs are assumed to already be in bytes
        if (is_numeric($val)) {
            return $val;
        }

        $val = trim($val);

        $modifier = strtolower($val[strlen($val) - 1]);
        $val = (int) substr($val, 0, -1);

        $pow = 0;

        switch ($modifier) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $pow = 3;
                break;
            case 'm':
                $pow = 2;
                break;
            case 'k':
                $pow = 1;
                break;
        }

        return $val * (1024 ** $pow);
    }

    /**
     * @param string $fieldName
     * @param null   $fieldLabel
     * @param string $type
     * @param string $label
     * @param int    $maxfiles
     * @param bool   $multiInstance
     * @param string $fieldHint
     * @param bool   $required
     * @param string $callback
     * @param string $chunksize
     * @param int    $maxFileSize
     * @param bool   $omitCssClassPrefix
     *
     * @return string
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function fileupload(
        $fieldName = 'r_file',
        $fieldLabel = null,
        $type = 'pdf',
        $label = 'form.button.upload.pdf',
        $maxfiles = 1,
        $multiInstance = false,
        $fieldHint = '',
        $required = false,
        $callback = '',
        $chunksize = 'Infinity',
        $maxFileSize = 0,
        $omitCssClassPrefix = true,
    ) {
        $elementId = $fieldName.'-'.str_replace('.', '', uniqid('', true));

        // Do not allow to pass a value higher that the value found in php.ini
        $maxFileSizeFromPhpIni = $this->convertPhpiniShorthandvaluesToBytes(ini_get('upload_max_filesize'));
        $maxFileSize = $maxFileSize > 0 ? min($maxFileSize, $maxFileSizeFromPhpIni) : $maxFileSizeFromPhpIni;

        $data = [
            'element_id'              => $elementId,
            'field_name'              => $fieldName,
            'field_label'             => $fieldLabel,
            'field_hint'              => $fieldHint,
            'field_required'          => $required,
            'human_max_upload_size'   => $this->formatHumanFilesize($maxFileSize),
            'callback'                => $callback,

            'multi_instance'          => $multiInstance,
            'label'                   => $label,
            'maxfiles'                => $maxfiles,
            'type'                    => $type,
            'chunksize'               => $chunksize,
            'maxfilesize'             => $maxFileSize,
            'omit_css_class_prefix'   => $omitCssClassPrefix,
        ];

        return $this->twig->render('@DemosPlanCore/Extension/fileupload.html.twig', $data);
    }
}
