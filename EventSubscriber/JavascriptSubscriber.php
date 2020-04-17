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
        $script = $this->getScriptToResizeDescriptionTextArea();
        $event->addContent("<script type='text/javascript'>${script}</script>");
    }

    public function getScriptToResizeDescriptionTextArea() {
        return <<<SCRIPT
            document.addEventListener('input', function (event) {
                descriptionTextArea = $('#custom_timesheet_edit_form_description');
                
                if($(event.target).is(descriptionTextArea)) {
                    numberOfRows = descriptionTextArea.val().split("\\n").length;
                    descriptionTextArea.attr('rows', numberOfRows);
                }
            }, false);
                
            document.addEventListener('modal-show', function() {
                descriptionTextArea = $('#custom_timesheet_edit_form_description');
                
                if(descriptionTextArea.val()) {
                    numberOfRows = descriptionTextArea.val().split("\\n").length;
                    descriptionTextArea.attr('rows', numberOfRows);
                }
            });
SCRIPT;
    }

}
