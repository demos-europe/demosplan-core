<?php

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @method array getRolleDiPlanBeteiligung()
 * @method string getEmailAdresse()
 * @method string getNutzerId()
 * @method string getProviderId()
 * @method string getVerfahrenstraeger()
 * @method string getVerfahrenstraegerGatewayId()
 * @method string getVollerName()
 */

class OzgKeycloakResponseValueObject extends ValueObject
{
    /**
     * @var array<int,string> $rolleDiPlanBeteiligung
     */
    protected array $rolleDiPlanBeteiligung;
    protected string $emailAdresse;
    protected string $nutzerId;
    protected string $providerId;
    protected string $verfahrenstraeger;
    protected string $verfahrenstraegerGatewayId;
    protected string $vollerName;

    public function __construct(array $keycloakResponseValues)
    {
        $this->rolleDiPlanBeteiligung = [];
        if (array_key_exists('rolleDiPlanBeteiligung', $keycloakResponseValues)
            && is_array($keycloakResponseValues['rolleDiPlanBeteiligung'])
        ) {
            $this->rolleDiPlanBeteiligung = $keycloakResponseValues['rolleDiPlanBeteiligung'];
        }
        $this->emailAdresse = $keycloakResponseValues['emailAdresse'] ?? '';
        $this->nutzerId = $keycloakResponseValues['nutzerId'] ?? '';
        $this->providerId = $keycloakResponseValues['providerId'] ?? '';
        $this->verfahrenstraeger = $keycloakResponseValues['verfahrenstraeger'] ?? '';
        $this->verfahrenstraegerGatewayId = $keycloakResponseValues['verfahrenstraegerGatewayId'] ?? '';
        $this->vollerName = $keycloakResponseValues['vollerName'] ?? '';
        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    private function checkMandatoryValuesExist() {
        if ('' === $this->nutzerId
            || '' === $this->providerId
            || '' === $this->emailAdresse
            || '' === $this->verfahrenstraeger
            || '' === $this->vollerName
        ) {
            throw new AuthenticationCredentialsNotFoundException('mandatory information missing in requestValues');
        }
    }
}
