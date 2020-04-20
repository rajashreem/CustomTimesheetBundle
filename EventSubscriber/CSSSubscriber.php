<?php

namespace KimaiPlugin\CustomTimesheetBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CSSSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => ['renderStylesheet', 100],
        ];
    }

    public function renderStylesheet(ThemeEvent $event)
    {
        $css = '<style type="text/css">li#calendar, li#timesheet {display:none;}</style>';
        $event->addContent($css);
    }

}
