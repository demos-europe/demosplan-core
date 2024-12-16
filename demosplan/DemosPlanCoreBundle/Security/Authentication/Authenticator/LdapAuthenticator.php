<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class LdapAuthenticator extends DplanAuthenticator
{

    public function supports(Request $request): ?bool
    {
        return LoginFormAuthenticator::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }



    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    public function authenticate(Request $request): Passport
    {
        $credentials = $this->getCredentials($request);
        return new SelfValidatingPassport(new UserBadge($credentials->getLogin()));
    }

    protected function getCredentials(Request $request): Credentials
    {
        $login = trim($request->request->get('r_useremail', ''));
        $credentialsVO = new Credentials();
        $credentialsVO->setLogin($login);
        $credentialsVO->setPassword(trim($request->request->get('password', '')));
        $credentialsVO->setToken($request->request->get('_csrf_token'));
        $credentialsVO->lock();

        return $credentialsVO;
    }
}
