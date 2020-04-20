<?php

namespace KimaiPlugin\CustomTimesheetBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use KevinPapst\AdminLTEBundle\Model\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private $security;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * MenuSubscriber constructor.
     * @param TokenStorageInterface $tokenStorage
     * @param AuthorizationCheckerInterface $security
     */
    public function __construct(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $security)
    {
        $this->security = $security;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100]
        ];
    }

    /**
     * @param ConfigureMainMenuEvent $event
     */
    public function onMenuConfigure(ConfigureMainMenuEvent $event)
    {
        $auth = $this->security;

        if (!$auth->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        $menu = $event->getMenu();
        if ($auth->isGranted('ROLE_USER')) {
            $menu->addItem(
                new MenuItemModel('custom_timesheet', 'My timesheets', 'custom_timesheet', [], 'fas fa-clock')
            );
            $menu->addItem(
                new MenuItemModel('custom_calendar', 'Calendar', 'custom_calendar', [], 'far fa-calendar-alt')
            );

        }
    }
}
