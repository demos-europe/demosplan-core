<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Base;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidPostDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\InitializeService;
use demosplan\DemosPlanCoreBundle\Logic\ViewRenderer;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Traits\CanTransformRequestVariablesTrait;
use demosplan\DemosPlanCoreBundle\Traits\IsProfilableTrait;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

use function is_array;

abstract class BaseController extends AbstractController
{
    use IsProfilableTrait;
    use CanTransformRequestVariablesTrait;

    /**
     * @var GlobalConfigInterface|GlobalConfig
     */
    protected $globalConfig;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;

    /**
     * @var InitializeService
     */
    private $initializeService;

    /**
     * @var ViewRenderer
     */
    private $viewRenderer;

    /**
     * Initialisiert die Session und Webservices.
     *
     * Hier wird insbesondere der User identifiziert über den Session-Hash oder als nicht eingelogged festgelegt.
     *
     * @deprecated Use `@DplanPermissions($context)` instead
     *
     * @param array $context Permission to test
     *
     * @throws SessionUnavailableException
     */
    public function initialize($context = null)
    {
        $this->initializeService->initialize($context);
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setGlobalConfig(GlobalConfigInterface $globalConfig): void
    {
        $this->globalConfig = $globalConfig;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setMessageBag(MessageBagInterface $messageBag): void
    {
        $this->messageBag = $messageBag;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setViewRenderer(ViewRenderer $viewRenderer): void
    {
        $this->viewRenderer = $viewRenderer;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setInitializeService(InitializeService $initializeService): void
    {
        $this->initializeService = $initializeService;
    }

    protected function getMessageBag(): MessageBagInterface
    {
        return $this->messageBag;
    }

    /**
     * Fehlerbehandlung.
     *
     * @return RedirectResponse|Response Response
     *
     * @deprecated use DplanPermissions({"permission"}) Annotation on controllers instead
     *     try/catch can be omitted, this error handling has moved to ExceptionListener
     *     and CheckPermissionListener
     *
     * @throws Exception in dev mode only
     */
    public function handleError(Throwable $e)
    {
        $return = $this->viewRenderer->handleError($e);

        if ($return instanceof Response) {
            return $return;
        }

        $logger = $this->logger;
        $logger->error($e);
        // Login fehlgeschlagen
        if (1004 === $e->getCode()) {
            try {
                $this->getMessageBag()->add('warning', 'warning.login.failed');
            } catch (MessageBagException) {
                $this->getLogger()->warning('Could not add Message to message bag');
            }

            return $this->redirectToRoute('core_home');
        }

        try {
            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanCore/error.html.twig',
                [
                    'title' => 'Ein Fehler ist aufgetreten',
                ]
            );
        } catch (Exception $e) {
            // improve DX by throwing uncaught exception to see error
            if ('dev' === $this->globalConfig->getKernelEnvironment()) {
                throw $e;
            }
            // Actually, there's not much we can do here but at least we can
            // try to tell the user that something went horribly unexplicabply wrong?
            $logger->error('There was an error during rendering an error message', [$e]);

            // temporarily use hardcoded trans string in deprecated function to avoid
            // to inject TranslatorInterface in all Controllers
            return new Response(
                'Ein interner Fehler ist aufgetreten',
                500
            );
        }
    }

    /**
     * Gib eine Fehlermeldung als JSON zurück.
     *
     * @deprecated Use JSON API instead (APIController::handleApiError)
     */
    protected function handleAjaxError(Throwable $e): JsonResponse
    {
        $this->logger->error('Ajax Error: ', [$e]);

        $code = 500;
        // temporarily use hardcoded trans string in deprecated function to avoid
        // to inject TranslatorInterface in all Controllers
        $message = 'Ein interner Fehler ist aufgetreten';
        switch (true) {
            case $e instanceof SessionUnavailableException:
            case $e instanceof AccessDeniedException:
                $code = 302;
                $message = 'You have to be logged in';
                break;
            case $e instanceof EntityIdNotFoundException:
                $code = 400;
                $message = 'Invalid request';
                break;
            case $e instanceof TooManyRequestsHttpException:
                $code = $e->getStatusCode();
                $message = $e->getMessage();
                break;
        }

        $response = [
            'code'    => $code,
            'success' => false,
            'message' => $message,
        ];

        return new JsonResponse($response, $code);
    }

    /**
     * Suche rekursiv in arrays.
     *
     * @param string $needle
     * @param array  $haystack
     *
     * @return bool|int|string
     */
    protected function recursiveArraySearch($needle, $haystack)
    {
        foreach ($haystack as $key => $value) {
            $current_key = $key;
            if ($needle === $value || (is_array($value) && false !== $this->recursiveArraySearch($needle, $value))) {
                return $current_key;
            }
        }

        return false;
    }

    /**
     * Get Logger.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get form option from globally defined parameter.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getFormParameter($key)
    {
        return $this->globalConfig->getFormOptions()[$key] ?? null;
    }

    /**
     * Always process the Controller's own message bag.
     *
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function renderTemplate($view, array $parameters = [], Response $response = null): Response
    {
        $this->viewRenderer->processRequestStatus();
        $parameters = $this->viewRenderer->processRequestParameters($view, $parameters, $response);

        return $this->render($view, $parameters, $response);
    }

    /**
     * Render data as json response.
     *
     * @param int   $appStatus  application status code
     * @param bool  $success    request success indicator
     * @param int   $httpStatus returned http status
     * @param array $headers    additional http headers
     *
     * @deprecated
     *
     * @return JsonResponse
     */
    public function renderJson(array $data, $appStatus = 200, $success = true, $httpStatus = 200, $headers = [])
    {
        return $this->viewRenderer->renderJson($data, $appStatus, $success, $httpStatus, $headers);
    }

    /**
     * Redirect back to the previous URL.
     *
     * @param string $urlFragment optionally appended fragment or get parameters
     * @param int    $status
     *
     * @return RedirectResponse
     *
     *@see BaseController::redirect()
     */
    public function redirectBack(Request $request, $urlFragment = '', $status = 302)
    {
        if (!$request->headers->has('referer')) {
            $this->logger->warning('Referer for redirect missing: '.$request->getUri());

            // message: Ihre Anfrage wurde erfolgreich durchgeführt, leider konnten wir Sie jedoch nicht auf die korrekte Seite weiterleiten
            return $this->redirectToRoute('core_home_loggedin');
        }

        return $this->redirect($request->headers->get('referer').$urlFragment, $status);
    }

    /**
     * @param string $type
     * @param bool   $useCsrf          set to true by default
     * @param bool   $allowExtraFields set to false by default
     */
    public function getForm(
        FormFactoryInterface $formFactory,
        $defaultData,
        $type,
        $useCsrf = true,
        $allowExtraFields = false,
    ): FormInterface {
        $formOptions = [
            'csrf_protection'    => $useCsrf,
            'csrf_token_id'      => $type,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => $allowExtraFields,
        ];

        return $formFactory->createNamed(
            // we don't use form names for data evaluation, see
            // https://symfony.com/doc/5.4/forms.html#changing-the-form-name
            '',
            $type,
            $defaultData,
            $formOptions
        );
    }

    /**
     * @return GlobalConfigInterface|GlobalConfig
     */
    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * @throws MessageBagException
     */
    protected function writeErrorsIntoMessageBag(FormErrorIterator $errors): void
    {
        foreach ($errors as $error) {
            $this->getMessageBag()->add('error', $error->getMessage());
        }
    }

    protected function getStringParameter(Request $request, string $parameterKey): string
    {
        $requestParameter = $request->request;

        if (!$requestParameter->has($parameterKey)) {
            throw InvalidPostDataException::createForMissingParameter($parameterKey);
        }

        $value = $request->get($parameterKey);

        if (!is_string($value)) {
            throw InvalidPostDataException::createForInvalidParameterType($parameterKey, 'string');
        }

        return $value;
    }
}
