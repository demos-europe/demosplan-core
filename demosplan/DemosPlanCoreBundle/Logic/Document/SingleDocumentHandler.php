<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DemosEurope\DemosplanAddon\Contracts\Handler\SingleDocumentHandlerInterface;
use demosplan\DemosPlanCoreBundle\Logic\LegacyFlashMessageCreator;
use ReflectionException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SingleDocumentHandler implements SingleDocumentHandlerInterface
{
    /**
     * @var SingleDocumentService
     */
    protected $service;

    public function __construct(
        private readonly LegacyFlashMessageCreator $legacyFlashMessageCreator,
        SingleDocumentService $service,
        private readonly TranslatorInterface $translator
    ) {
        $this->service = $service;
    }

    /**
     * @param string $procedureId
     *
     * @return array|array[]|bool
     *
     * @throws ReflectionException
     */
    public function administrationDocumentNewHandler($procedureId, ?string $category, string $elementID, array $data)
    {
        $document = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if (DocumentHandler::ACTION_SINGLE_DOCUMENT_NEW !== $data['action']) {
            return false;
        }

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];

        if (!array_key_exists('r_statement_enabled', $data) || '' === trim((string) $data['r_statement_enabled'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError', ['fieldLabel' => $this->translator->trans('statement.possible')]
                ),
            ];
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return ['mandatoryfieldwarning' => $mandatoryErrors];
        }
        if (!array_key_exists('r_document', $data) || '' === trim((string) $data['r_document'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError', ['fieldLabel' => $this->translator->trans('pdf.zip.document')]
                ),
            ];
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return ['mandatoryfieldwarning' => $mandatoryErrors];
        }
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError', ['fieldLabel' => $this->translator->trans('heading')]
                ),
            ];
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return ['mandatoryfieldwarning' => $mandatoryErrors];
        }

        if (array_key_exists('r_text', $data)) {
            $document['text'] = $data['r_text'];
        }

        if (array_key_exists('r_title', $data)) {
            $document['title'] = $data['r_title'];
        }

        if (array_key_exists('r_statement_enabled', $data)) {
            if ('1' == $data['r_statement_enabled']) {
                $document['statement_enabled'] = true;
            } else {
                $document['statement_enabled'] = false;
            }
        }

        if (array_key_exists('r_document', $data)) {
            if (null != $data['r_document']) {
                $document['document'] = $data['r_document'];
            }
        }

        $document['visible'] = true;
        $document['pId'] = $procedureId;
        $document['elementId'] = $elementID;
        $document['category'] = $category;

        return $this->service->addSingleDocument($document);
    }

    /**
     * @param array $data
     *
     * @return array|bool
     *
     * @throws ReflectionException
     */
    public function administrationDocumentEditHandler($data)
    {
        $document = [];

        if (!array_key_exists('r_action', $data)) {
            return false;
        }

        if ('singledocumentedit' !== $data['r_action']) {
            return false;
        }

        if (array_key_exists('r_ident', $data)) {
            $document['ident'] = $data['r_ident'];
        }

        if (array_key_exists('r_title', $data)) {
            $document['title'] = $data['r_title'];
        }

        if (array_key_exists('r_text', $data)) {
            $document['text'] = $data['r_text'];
        }

        if (array_key_exists('r_document', $data)) {
            if (null != $data['r_document']) {
                $document['document'] = $data['r_document'];
            }
        }

        if (array_key_exists('r_visible', $data)) {
            if ('1' == $data['r_visible']) {
                $document['visible'] = true;
            } else {
                $document['visible'] = false;
            }
        }
        if (array_key_exists('r_statement_enabled', $data)) {
            if ('1' == $data['r_statement_enabled']) {
                $document['statement_enabled'] = true;
            } else {
                $document['statement_enabled'] = false;
            }
        }

        return $this->service->updateSingleDocument($document);
    }

    public function administrationDocumentDeleteHandler($data)
    {
        $this->service->deleteSingleDocument($data['r_ident']);
    }
}
