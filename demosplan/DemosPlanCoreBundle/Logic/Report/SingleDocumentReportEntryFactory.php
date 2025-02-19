<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Report;


use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;

class SingleDocumentReportEntryFactory extends AbstractReportEntryFactory
{
    public function __construct(
        CurrentUserInterface $currentUserProvider,
        CustomerService $currentCustomerProvider,
    ) {
        parent::__construct($currentUserProvider, $currentCustomerProvider);
    }

    /**
     * @throws JsonException
     */
    private function createSingleDocumentReportEntry(string $procedureId, array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setGroup(ReportEntry::GROUP_SINGLE_DOCUMENT);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    private function createData(SingleDocument $singleDocument): array
    {
        return [
            'documentId'  => $singleDocument->getId(),
            'documentTitle' => $singleDocument->getTitle(),
            'documentCategory' => $singleDocument->getCategory(), //eg. file, e_unterlagen, arbeitskreis, informationen,...
            'elementCategory' => $singleDocument->getElement()->getCategory(), //eg map, file, statement, paragraph, ..
            'elementTitle' => $singleDocument->getElement()->getTitle(), //eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'visible' => $singleDocument->getVisible(),
            'statement_enabled' => $singleDocument->isStatementEnabled(),
            'procedurePhase' => $singleDocument->getProcedure()->getPhase(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function createSingleDocumentCreateEntry(SingleDocument $singleDocument): ReportEntry
    {
        $data = $this->createData($singleDocument);
        $data['date'] = $singleDocument->getCreateDate()->getTimestamp();
        $reportEntry = $this->createSingleDocumentReportEntry($singleDocument->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_ADD);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createSingleDocumentUpdateEntry(SingleDocument $singleDocument): ReportEntry
    {
        $data = $this->createData($singleDocument);
        $data['date'] = $singleDocument->getModifyDate()->getTimestamp();
        $reportEntry = $this->createSingleDocumentReportEntry($singleDocument->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_UPDATE);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createSingleDocumentDeleteEntry(SingleDocument $singleDocument): ReportEntry
    {
        $data = $this->createData($singleDocument);
        $data['date'] = Carbon::now()->getTimestamp();
        $reportEntry = $this->createSingleDocumentReportEntry($singleDocument->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_DELETE);

        return $reportEntry;
    }


    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_SINGLE_DOCUMENT);

        return $reportEntry;
    }

    private function getCurrentUser(): User
    {
        try {
            $currentUser = $this->currentUserProvider->getUser();
        } catch (UserNotFoundException) {
            $currentUser = new AnonymousUser();
        }

        return $currentUser;
    }
}
