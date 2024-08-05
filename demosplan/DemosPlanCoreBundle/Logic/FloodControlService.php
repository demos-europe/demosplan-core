<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DateInterval;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Flood;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormExtraFieldsEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PublicDetailStatementListLoadedEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationEvent;
use demosplan\DemosPlanCoreBundle\Exception\CookieException;
use demosplan\DemosPlanCoreBundle\Exception\HoneypotException;
use demosplan\DemosPlanCoreBundle\Exception\IpFloodException;
use demosplan\DemosPlanCoreBundle\Repository\FloodRepository;
use Exception;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Collection;
use Twig\Environment;

class FloodControlService extends CoreService
{
    /**
     * Do not allow any login from the current user's IP if the limit has been
     * reached. Default is 50 failed attempts allowed in one hour. This is
     * independent of the per-user limit to catch attempts from one IP to log
     * in to many different user accounts.  We have a reasonably high limit
     * since there may be only one apparent IP for all users at an institution.
     *
     * (This comment is taken from drupal7 user plugin)
     *
     * @var int
     */
    final public const IP_FLOOD_THRESHOLD = 50;

    /**
     * Cookie key to use for floodrelated tasks.
     */
    final public const COOKIE_KEY = 'dplan-flood';

    /**
     * @param int $ipFloodThreshold
     */
    public function __construct(protected Environment $twig, private readonly FloodRepository $floodRepository, protected GlobalConfigInterface $globalConfig, private readonly MessageBagInterface $messageBag, protected $ipFloodThreshold = self::IP_FLOOD_THRESHOLD)
    {
    }

    /**
     * Check, whether Data has been filled out by a bot.
     *
     * @throws HoneypotException
     */
    public function checkHoneypot(RequestValidationEvent $event)
    {
        $this->getLogger()->debug('FloodControl: Honeypot check triggered');

        // if set in parameters.yml disable honeypot check
        if ($this->globalConfig->isHoneypotDisabled()) {
            $this->getLogger()->info('FloodControl: Honeypot check disabled by config');

            return;
        }

        $request = $event->getRequest();

        if ($request->request->has('url') && mb_strlen(trim($request->request->get('url'))) > 0) {
            $this->getLogger()->info('FloodControl: Honeypot field has been filled out');
            throw new HoneypotException('Honeypot field has been filled out');
        }

        if ($request->request->has('r_loadtime')) {
            $loadtime = $request->request->get('r_loadtime');
            // avoid type error fatal errors
            $loadtime = is_int($loadtime) ? $loadtime : 0;
            $totaltime = time() - $loadtime;
            if ($totaltime < $this->globalConfig->getHoneypotTimeout()) {
                $this->getLogger()->info('FloodControl: Honeypot form has been submitted too fast');
                $this->messageBag->add('warning', 'warning.floodcontrol.timeout');
                throw new HoneypotException('Honeypot form has been submitted too fast');
            }
        } else {
            $this->getLogger()->info('FloodControl: Honeypot No loadtime provided');
            throw new HoneypotException('Honeypot No loadtime provided');
        }
        $this->getLogger()->debug('FloodControl: Honeypot successfully checked');
    }

    /**
     * adds Markup needed for Honeypot verification.
     */
    public function getHoneypotMarkup(TwigExtensionFormExtraFieldsEvent $event)
    {
        $event->addMarkup(
            $this->twig->render('@DemosPlanCore/DemosPlanCore/floodControl/honeypotFields.html.twig')
        );
    }

    /**
     * Check, whether Cookie has been set to prevent multiple submissions.
     *
     * @throws CookieException
     */
    public function checkCookie(RequestValidationEvent $event)
    {
        $this->getLogger()->debug('FloodControl: Cookie check triggered');

        $identifier = $event->getScope().'_'.$event->getIdentifier();
        $cookie = $event->getRequest()->cookies->get(self::COOKIE_KEY);
        if (is_null($cookie)) {
            $event->getResponse()->headers->setCookie(
                Cookie::create(self::COOKIE_KEY, Json::encode([$identifier]), 0, '/', null, false, true, false, 'strict'));
            $this->getLogger()->debug('FloodControl: Set Floodcontrol Cookie '.self::COOKIE_KEY);

            return;
        }

        // refactoring note: This used to be `json_decode()` without the associative arg set to true
        // the refactorer did not feel comfortable changing this to Json::decodeToArray() and opted
        // to leave the array cast intact. It should however be noted that this is potentially wrong.
        $cookieValue = collect((array) Json::decodeToMatchingType($cookie));
        if (false !== $cookieValue->search($identifier)) {
            $this->getLogger()->info('FloodControl: User tried to do something twice which is not allowed');
            throw new CookieException('User tried to do something twice which is not allowed');
        }

        // add new identifier to Cookie
        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE_KEY, Json::encode($cookieValue->push($identifier)->toArray()), 0, '/', null, false, true, false, 'strict')
        );

        $this->getLogger()->debug('FloodControl: Cookie successfully checked');
    }

    /**
     * Extract a list of Statements liked by user.
     */
    public function extractStatementUserLikeIds(PublicDetailStatementListLoadedEvent $event)
    {
        $statements = $event->getStatements();
        $statementCookieList = $this->getCookieValueList($event->getRequest());
        $likedStatementIds = $event->getLikedStatementIds();
        $statements->each(function ($statement) use ($statementCookieList, $event, &$likedStatementIds) {
            if (false !== $statementCookieList->search($event->getScope().'_'.$statement['id'])) {
                $likedStatementIds->push($statement['id']);
            }
        });
        $event->setLikedStatementIds($likedStatementIds);
    }

    /**
     * Get saved Cookie values.
     *
     * @return Collection
     */
    public function getCookieValueList(Request $request)
    {
        $cookie = $request->cookies->get(self::COOKIE_KEY);
        if (is_null($cookie)) {
            return collect([]);
        }

        // refactoring note: This used to be `json_decode()` without the associative arg set to true
        // the refactorer did not feel comfortable changing this to Json::decodeToArray() and opted
        // to leave the array cast intact. It should however be noted that this is potentially wrong.
        return collect((array) Json::decodeToMatchingType($cookie));
    }

    /**
     * Check whether there are too many requests from a single IP.
     */
    public function checkFlood(RequestValidationEvent $event)
    {
        $this->getLogger()->debug('FloodControl: IPcheck check triggered');

        $request = $event->getRequest();

        $floodEvent = $event->getIdentifier().'_ip';
        $identifier = $request->getClientIp();

        $entries = [];
        try {
            $entries = $this->floodRepository->getAllOfIdentifier($identifier, $floodEvent);
        } catch (Exception $e) {
            $this->getLogger()->warning('FloodControl: Could not get floodcontrol objects: ', [$e]);
        }

        try {
            if (count($entries ?? []) >= self::IP_FLOOD_THRESHOLD) {
                $this->getLogger()->warning("FloodControl: The IP '".$identifier."' sent too many requests");
                throw new IpFloodException("The IP '".$identifier."' sent too many requests");
            } else {
                $flood = new Flood();
                $flood->setEvent($floodEvent);
                $flood->setIdentifier($identifier);
                $expires = new DateTime('now');
                $expires->add(new DateInterval('PT3600S'));
                $flood->setExpires($expires);
                $this->floodRepository->addObject($flood);
            }
        } catch (Exception $e) {
            if ($e instanceof IpFloodException) {
                throw $e;
            }
            $this->getLogger()->warning('FloodControl: Could not create IpFlood log object: ', [$e]);
        }

        $this->getLogger()->debug('FloodControl: IP Flood successfully checked');
    }

    /**
     * Removes all records that exist beyond their expiration date.
     */
    public function cleanRecords()
    {
        $repo = $this->floodRepository;
        $repo->deleteExpired();
    }
}
