<?php

namespace KimaiPlugin\CustomTimesheetBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JavascriptSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::JAVASCRIPT => ['renderJavascript', 100],
        ];
    }

    public function renderJavascript(ThemeEvent $event)
    {
        $script = $this->getScriptToExpandTextArea();
        $event->addContent("<script type='text/javascript'>${script}</script>");
    }

    public function getScriptToExpandTextArea() {
        return <<<SCRIPT
        var autoExpand = function (field) {
                // Reset field height
                field.style.height = 'inherit';

                // Get the computed styles for the element
                var computed = window.getComputedStyle(field);

                // Calculate the height
                var height = parseInt(computed.getPropertyValue('border-top-width'), 10)
                    + parseInt(computed.getPropertyValue('padding-top'), 10)
                    + field.scrollHeight
                    + parseInt(computed.getPropertyValue('padding-bottom'), 10)
                    + parseInt(computed.getPropertyValue('border-bottom-width'), 10);

                field.style.height = height + 'px';
            };

            document.addEventListener('input', function (event) {
                if (event.target.tagName.toLowerCase() !== 'textarea') return;
                autoExpand(event.target);
            }, false);
SCRIPT;
    }

}
