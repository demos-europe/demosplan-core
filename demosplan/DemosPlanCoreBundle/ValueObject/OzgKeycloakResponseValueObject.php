<?php

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * @method array getRolleDiPlanBeteiligung()
 * @method bool getEmailVerified()
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
    protected bool $emailVerified;
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

        $this->emailVerified = (bool) ($keycloakResponseValues['email_verified'] ?? false);
        $this->emailAdresse = $keycloakResponseValues['emailAdresse'] ?? '';
        $this->nutzerId = $keycloakResponseValues['nutzerId'] ?? '';
        $this->providerId = $keycloakResponseValues['providerId'] ?? '';
        $this->verfahrenstraeger = $keycloakResponseValues['verfahrenstraeger'] ?? '';
        $this->verfahrenstraegerGatewayId = $keycloakResponseValues['verfahrenstraegerGatewayId'] ?? '';
        $this->vollerName = $keycloakResponseValues['vollerName'] ?? '';
        $this->lock();
    }
}
