<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Security\HTTP\EventListener;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessageSerializable;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\WeakPasswordCheckerBadge;
use demosplan\DemosPlanCoreBundle\Validator\PasswordValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class WeakPasswordCheckerEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly PasswordValidator $passwordValidator,
        private readonly TranslatorInterface $translator,
        private readonly MessageBagInterface $messageBag
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['checkPassport']];
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(WeakPasswordCheckerBadge::class)) {
            return;
        }

        /** @var WeakPasswordCheckerBadge $badge */
        $badge = $passport->getBadge(WeakPasswordCheckerBadge::class);

        // check for password strength and warn if it is too weak
        $violations = $this->passwordValidator->validate($badge->getPassword());
        if (0 < $violations->count()) {
            $linkChangeText = $this->translator->trans('password.change');
            $this->messageBag->addObject(LinkMessageSerializable::createLinkMessage(
                'warning',
                'warning.password.weak',
                [],
                'DemosPlan_user_portal',
                [],
                $linkChangeText)
            );
        }
    }
}
