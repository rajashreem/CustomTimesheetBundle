<?php

namespace KimaiPlugin\CustomTimesheetBundle\Controller;

use App\Calendar\Google;
use App\Calendar\Source;
use App\Configuration\CalendarConfiguration;
use App\Controller\AbstractController;
use App\Timesheet\TrackingModeService;
use App\Timesheet\UserDateTimeFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller used to display calendars.
 *
 * @Route(path="/custom_calendar")
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 */
class CalendarController extends AbstractController
{
    /**
     * @Route(path="/", name="custom_calendar", methods={"GET"})
     * @param CalendarConfiguration $configuration
     * @param UserDateTimeFactory $dateTime
     * @param TrackingModeService $service
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userCalendar(CalendarConfiguration $configuration, UserDateTimeFactory $dateTime, TrackingModeService $service)
    {
        $mode = $service->getActiveMode();

        return $this->render('@CustomTimesheet/user.html.twig', [
            'config' => $configuration,
            'google' => $this->getGoogleSources($configuration),
            'now' => $dateTime->createDateTime(),
            'is_punch_mode' => !$mode->canEditDuration() && !$mode->canEditBegin() && !$mode->canEditEnd()
        ]);
    }

    /**
     * @return Google
     */
    protected function getGoogleSources(CalendarConfiguration $configuration)
    {
        $apiKey = $configuration->getGoogleApiKey() ?? '';
        $sources = [];

        foreach ($configuration->getGoogleSources() as $name => $config) {
            $source = new Source();
            $source
                ->setColor($config['color'])
                ->setUri($config['id'])
                ->setId($name)
            ;

            $sources[] = $source;
        }

        return new Google($apiKey, $sources);
    }
}
