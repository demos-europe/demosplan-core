<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\ValueObject\User\DataProtectionOrganisation;
use demosplan\DemosPlanCoreBundle\ValueObject\User\ImprintOrganisation;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrgaHandler extends CoreHandler
{
    public function __construct(private readonly OrgaService $orgaService, MessageBagInterface $messageBag, private readonly TranslatorInterface $translator, private readonly CurrentUserInterface $currentUser)
    {
        parent::__construct($messageBag);
    }

    public function getOrgaService(): OrgaService
    {
        return $this->orgaService;
    }

    /**
     * @return DataProtectionOrganisation[]
     */
    public function getDataProtectionMunicipalities(Customer $customer): array
    {
        return $this->getOrgaService()->getDataProtectionMunicipalities($customer);
    }

    /**
     * @return ImprintOrganisation[]
     */
    public function getImprintMunicipalities(Customer $customer): array
    {
        return $this->getOrgaService()->getImprintMunicipalities($customer);
    }

    /**
     * @param string[] $data
     *
     * @return array[]
     */
    public function validateOrgaData(array $data): array
    {
        $mandatoryErrors = [];
        if (!array_key_exists('name', $data) || '' === trim($data['name'])) {
            $mandatoryErrors[] = $this->createMandatoryErrorMessage('name');
        }

        if (!array_key_exists('registrationStatuses', $data) || 0 === (is_countable($data['registrationStatuses']) ? count($data['registrationStatuses']) : 0)) {
            $mandatoryErrors[] = $this->createMandatoryErrorMessage('type');
        } else {
            $regStatus = $data['registrationStatuses'][0];
            if (!array_key_exists('status', $regStatus) || '' === trim((string) $regStatus['status'])
                || !array_key_exists('subdomain', $regStatus) || '' === trim((string) $regStatus['subdomain'])
                || !array_key_exists('type', $regStatus) || '' === trim((string) $regStatus['type'])) {
                $mandatoryErrors[] = $this->createMandatoryErrorMessage('type');
            }
        }

        if (array_key_exists('slug', $data)) {
            throw new InvalidArgumentException('no slug must be provided when creating an orga, it will be created with its ID as default slug');
        }

        return $mandatoryErrors;
    }

    // @improve T15377

    /**
     * Delete orga.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/deletion_of_entity_objects/ delete entity objects
     *
     * @param string $orgaId
     *
     * @return bool|null
     *
     * @throws MessageBagException
     */
    public function deleteOrga($orgaId)
    {
        try {
            return $this->getOrgaService()->deleteOrga($orgaId);
        } catch (Exception) {
            $this->getMessageBag()->add(
                'error',
                $this->translator->trans('error.save')
            );

            return null;
        }
    }

    /**
     * Get Orga by Id.
     *
     * @param string $orgaId
     *
     * @return Orga|null
     *
     * @throws Exception
     */
    public function getOrga($orgaId)
    {
        return $this->getOrgaService()->getOrga($orgaId);
    }

    /**
     * @param array<string,mixed>|mixed[] $attributes an array with the attribute names as keys
     */
    public function checkWritabilityOfAttributes(array $attributes)
    {
        $allowedAttributes = $this->getWritableAttributes();
        $deniedAttributes = array_diff_key($attributes, $allowedAttributes);

        if ([] !== $deniedAttributes) {
            throw AccessDeniedException::deniedWriteAccessToAttributes('Orga', array_keys($deniedAttributes));
        }
    }

    /**
     * Limits the fields writeable using API requests by three criteria.
     *
     * 1. Some fields should not be updates over the API at all. Intiuitive examples
     * are 'id' (should never change at all) and 'deleted' (not to be changed using
     * an update (HTTP PATCH) request but by using a HTTP DELETE request.
     *
     * 2. Another case would be the frontend sending a field the user has no
     * permission to write to. In that case we don't want to make that field
     * writable.
     *
     * 3. If the information for orgas is provided by the gateway we do not allow
     * editing (most of) them over our UI, as the changes would be overwritten
     * by the gateway anyway.
     *
     * @return array<string,string>|string[] the attribute names as keys and values
     */
    public function getWritableAttributes(): array
    {
        // these are writable in 'portal' mode as well as in 'gateway' mode
        $writableAttributes = [
            'ccEmail2',
            'copySpec',
            'cssvars',
            'emailNotificationEndingPhase',
            'emailNotificationNewStatement',
            'participationEmail',
            'registrationStatuses',
            'showlist',
            'showlistChangeReason',
            'showname',
            'canCreateProcedures',
        ];

        if ($this->currentUser->hasPermission('field_organisation_email2_cc')) {
            $writableAttributes[] = 'email2';
        }

        if ($this->currentUser->hasPermission('field_organisation_management_paper_copy')) {
            $writableAttributes[] = 'copy';
        }

        if ($this->currentUser->hasPermission('field_data_protection_text_customized_edit_orga')) {
            $writableAttributes[] = 'dataProtection';
        }

        if ($this->currentUser->hasPermission('field_imprint_text_customized_edit_orga')) {
            $writableAttributes[] = 'imprint';
        }

        // these are writable in 'portal' mode only
        if ('portal' === $this->getDemosplanConfig()->getProjectType()) {
            $writableAttributes = [...$writableAttributes, 'allowedRoleIds', 'competence', 'contactPerson', 'city', 'dataProtection', 'emailReviewerAdmin', 'houseNumber', 'imprint', 'name', 'paperCopy', 'paperCopySpec', 'phone', 'postalcode', 'street', 'submissionType', 'types'];
        }

        // use the attribute names both as keys and as values in the array
        return array_combine($writableAttributes, $writableAttributes);
    }

    /**
     * Generiere einen Eintrag für die notwendigen Felder.
     *
     * @param string $translatorLabel
     */
    public function createMandatoryErrorMessage($translatorLabel): array
    {
        return [
            'type'    => 'error',
            'message' => $this->translator->trans(
                'error.mandatoryfield',
                [
                    'name' => $translatorLabel,
                ]
            ),
        ];
    }
}
