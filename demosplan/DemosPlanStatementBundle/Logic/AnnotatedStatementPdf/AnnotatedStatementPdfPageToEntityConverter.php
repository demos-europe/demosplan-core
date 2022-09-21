<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdfPage;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ExternalFileSaver;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

class AnnotatedStatementPdfPageToEntityConverter extends CoreService
{
    /**
     * @var ExternalFileSaver
     */
    private $externalFileSaver;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    public function __construct(ExternalFileSaver $externalFileSaver, UrlGeneratorInterface $router)
    {
        $this->externalFileSaver = $externalFileSaver;
        $this->router = $router;
    }

    /**
     * @throws Throwable
     */
    public function convert(
        AnnotatedStatementPdf $annotatedStatementPdf,
        string $annotatedStatementPdfPagesJson,
        bool $updatePages
    ): AnnotatedStatementPdf {
        $annotatedStatementPdfArray = Json::decodeToArray($annotatedStatementPdfPagesJson);

        if (isset($annotatedStatementPdfArray['data']['attributes']['statementtext'])) {
            $annotatedStatementPdf->setStatementText(
                $annotatedStatementPdfArray['data']['attributes']['statementtext']
            );
        }
        $submitterJson = isset($annotatedStatementPdfArray['data']['attributes']['sender'])
            ? Json::encode((object) $annotatedStatementPdfArray['data']['attributes']['sender'])
            : '';
        $annotatedStatementPdf->setSubmitterJson($submitterJson);

        if (isset($annotatedStatementPdfArray['data']['meta']['updateToken'])) {
            $annotatedStatementPdf->setPiResourceUrl(
                $annotatedStatementPdfArray['data']['meta']['updateToken'],
            );
        }

        if ($updatePages) {
            $currentPages = collect($annotatedStatementPdf->getAnnotatedStatementPdfPages())
                ->flatMap(static function (AnnotatedStatementPdfPage $annotatedStatementPdfPage) {
                    return [$annotatedStatementPdfPage->getPageOrder() => $annotatedStatementPdfPage];
                })
                ->sortKeys();

            $updatedPages = new ArrayCollection();

            foreach ($annotatedStatementPdfArray['included'] as $pageNumber => $annotatedStatementPdfPageArray) {
                $annotatedStatementPdfPage = new AnnotatedStatementPdfPage();

                if ($currentPages->has($pageNumber)) {
                    $this->logger->info('Trying to update existing page');

                    // use existing page and update contents accordingly
                    /** @var AnnotatedStatementPdfPage $annotatedStatementPdfPage */
                    $annotatedStatementPdfPage = $currentPages[$pageNumber];

                    // we only want to update contents if the pdf was not touched by any user
                    if ($annotatedStatementPdfPage->isConfirmed() || !in_array(
                            $annotatedStatementPdf->getStatus(),
                            [AnnotatedStatementPdf::PENDING, AnnotatedStatementPdf::BOX_REVIEW],
                            true
                        )) {
                        $this->logger->info('Skipping existing page update because of status mismatch', [$annotatedStatementPdf->getStatus()]);

                        // keep the page in the list of pages
                        $updatedPages->add($annotatedStatementPdfPage);

                        continue;
                    }
                }

                $attributes = $annotatedStatementPdfPageArray['attributes'];

                $annotatedStatementPdfPage->setHeight(
                    $attributes[AnnotatedStatementPdfPage::HEIGHT]
                );
                $annotatedStatementPdfPage->setWidth(
                    $attributes[AnnotatedStatementPdfPage::WIDTH]
                );
                $annotatedStatementPdfPage->setGeoJson(
                    Json::encode($attributes['geojson'])
                );

                $annotatedStatementPdfPage->setPageOrder($pageNumber);

                $localImageUrl = $this->getLocalImageUrl(
                    $attributes['url']
                );

                $annotatedStatementPdfPage->setUrl($localImageUrl);
                $annotatedStatementPdfPage->setAnnotatedStatementPdf($annotatedStatementPdf);

                $updatedPages->add($annotatedStatementPdfPage);
            }

            $annotatedStatementPdf->setAnnotatedStatementPdfPages($updatedPages);
        }

        return $annotatedStatementPdf;
    }

    /**
     * @throws Throwable
     */
    private function getLocalImageUrl(string $url): string
    {
        // @improve T24481 this should be called with current procedure
        $localImage = $this->externalFileSaver->save($url);
        $parameters = ['hash' => $localImage->getHash()];

        return $this->router->generate('core_file', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
