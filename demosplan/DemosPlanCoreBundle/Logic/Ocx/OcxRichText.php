<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function preg_replace_callback;

/**
 * Turns OCX rich text into what the editor accepts: inline images point to the delivered file, and
 * markup outside the accepted subset is removed and reported as an OCX warning.
 */
class OcxRichText
{
    /**
     * `src="attachment:<id>"` inside an `img` element, OCX's only allowed image source.
     */
    private const IMAGE_SOURCE = '/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])attachment:([^"\']+)\2/i';

    public function __construct(
        private readonly HTMLSanitizer $htmlSanitizer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function filter(?string $html, string $pointer, OcxEntryContext $context): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        // unknown attachments keep their source; the filter then drops the image and reports it
        $html = preg_replace_callback(self::IMAGE_SOURCE, function (array $match) use ($context): string {
            $fileId = $context->getFileId($match[3]);
            if (null === $fileId) {
                return $match[0];
            }
            $context->useFile($fileId);

            return $match[1].$match[2].$this->urlGenerator->generate('core_file_procedure', [
                'procedureId' => $context->procedureId,
                'hash'        => $fileId,
            ]).$match[2];
        }, $html) ?? $html;

        $filtered = $this->htmlSanitizer->wysiwygFilter($html, [], true);
        if ($this->htmlSanitizer->removesMarkup($html, $filtered)) {
            $context->warn('markup_removed', $pointer, 'Markup outside the accepted subset was removed.');
        }

        return $filtered;
    }
}
