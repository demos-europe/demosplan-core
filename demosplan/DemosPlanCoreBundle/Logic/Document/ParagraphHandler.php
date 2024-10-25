<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Handler\ParagraphHandlerInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class ParagraphHandler extends CoreHandler implements ParagraphHandlerInterface
{

    public function __construct(
        protected readonly ParagraphService $paragraphService,
        protected readonly ParagraphRepository $paragraphRepository,
        private readonly FlashMessageHandler $flashMessageHandler,
        MessageBag $messageBag,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($messageBag);
    }

    public function administrationDocumentNewHandler(string $procedure, string $category, array $data, $elementId)
    {
        $document = [];

        if (!array_key_exists('r_action', $data)) {
            return false;
        }

        if ('documentnew' !== $data['r_action']) {
            return false;
        }

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('heading'),
                ]),
            ];
        }
        if (!array_key_exists('r_text', $data) || '' === trim((string) $data['r_text'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('paragraph.text'),
                ]),
            ];
        }

        if (!array_key_exists('r_visible', $data) || '' === trim((string) $data['r_visible'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('status'),
                ]),
            ];
        }

        if (0 < count($mandatoryErrors)) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        if (array_key_exists('r_title', $data)) {
            $document['title'] = $data['r_title'];
        }

        if (array_key_exists('r_text', $data)) {
            $document['text'] = $data['r_text'];
        }

        if (array_key_exists('r_visible', $data)) {
            if ('1' === $data['r_visible']) {
                $document['visible'] = true;
            } else {
                $document['visible'] = false;
            }
        }

        if (array_key_exists('r_elementId', $data)) {
            $document['elementId'] = $data['r_elementId'];
            // set max possible order only if no parent paragraph is set
            if (!array_key_exists('r_parentId', $data) && 0 < strlen((string) $data['r_parentId'])) {
                $document['order'] = $this->paragraphService->getMaxOrderFromElement(
                    $document['elementId']
                ) + 1;
            }
        }

        if (array_key_exists('r_parentId', $data) && 0 < strlen((string) $data['r_parentId'])) {
            $document = $this->prepareParagraphParentTree(
                $data,
                $elementId,
                $document
            );
        }

        $document['pId'] = $procedure;
        $document['category'] = $category;

        return $this->paragraphService->addParaDocument($document);
    }

    /**
     * @param string $procedureId
     * @param array  $data
     * @param string $elementId
     *
     * @return array|bool
     */
    public function administrationDocumentEditHandler($procedureId, $data, $elementId)
    {
        $document = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('documentedit' !== $data['action']) {
            return false;
        }

        // Array auf
        if (array_key_exists('r_ident', $data)) {
            $document['ident'] = $data['r_ident'];
        }

        if (array_key_exists('r_title', $data)) {
            $document['title'] = $data['r_title'];
        }

        if (array_key_exists('r_text', $data)) {
            $document['text'] = $data['r_text'];
        }

        if (array_key_exists('r_lockReason', $data)) {
            $document['lockReason'] = $data['r_lockReason'];
        }

        if (array_key_exists('r_visible', $data)) {
            $document['visible'] = $data['r_visible'];
        }

        if (array_key_exists('r_parentId', $data)) {
            $document = $this->prepareParagraphParentTree(
                $data,
                $elementId,
                $document
            );
        }

        return $this->paragraphService->updateParaDocument($document);
    }

    /**
     * Update order of all other paragraphs to insert paragraph into tree.
     *
     * @param array  $data
     * @param string $elementId
     * @param array  $document
     *
     * @return array
     */
    protected function prepareParagraphParentTree($data, $elementId, $document)
    {
        $parentParagraphId = '0' === $data['r_parentId'] ? null : $data['r_parentId'];
        $documentId = $document['ident'] ?? null;

        if ($this->paragraphService->isChildOf($parentParagraphId, $documentId)) {
            // Prohibits assigning own children as the parent.

            $this->getSession()->getFlashBag()->add(
                'warning',
                $this->translator->trans('error.paragraph.no_circular_references')
            );

            return $document;
        } elseif ($parentParagraphId === $documentId && null !== $documentId) {
            // Prohibits assigning oneself as the parent.
            // Second condition deactivates this for newly created chapters, see T11773.

            $this->getSession()->getFlashBag()->add(
                'warning',
                $this->translator->trans('error.paragraph.no_self_references')
            );

            return $document;
        } elseif (isset($documentId)
            && (
                $this->paragraphService->isDirectParentOf($parentParagraphId, $documentId)
                || (
                    null === $parentParagraphId
                    && !$this->paragraphService->hasParent($document['ident'])
                )
            )
        ) {
            // Prohibits assigning same parent as was before
            // First condition deactivates this for newly created chapters.

            return $document;
        } else {
            $document['parentId'] = $parentParagraphId;
            // get max order of new parent level
            if (null === $parentParagraphId) {
                $maxOrder = $this->paragraphService->getMaxOrderFromElement($elementId);
            } else {
                $maxOrder = $this->paragraphService->calculateLastOrder($parentParagraphId);
            }

            $document['order'] = $maxOrder + 1;

            $offset = $this->paragraphService->incrementChildrenOrders(
                $documentId,
                $maxOrder + 1
            );

            // update paragraph ordering for subsequent paragraphs
            $this->paragraphService->incrementSubsequentOrders(
                $maxOrder,
                $elementId,
                $offset + 1
            );

            return $document;
        }
    }

    public function administrationDocumentDeleteHandler(ProcedureInterface $procedure, ElementsInterface $element)
    {
        return $this->paragraphRepository->deleteByProcedureIdAndElementId($procedure->getId(), $element->getId());
    }
}
