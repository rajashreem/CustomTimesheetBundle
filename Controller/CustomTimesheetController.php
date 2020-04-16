<?php

namespace KimaiPlugin\CustomTimesheetBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\Timesheet;
use App\Event\TimesheetMetaDisplayEvent;
use App\Form\Toolbar\TimesheetToolbarForm;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TagRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Timesheet\TrackingMode\TrackingModeInterface;
use App\Timesheet\TrackingModeService;
use KimaiPlugin\CustomTimesheetBundle\Form\CustomTimesheetEditForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(path="/custom-timesheets")
 * @Security("is_granted('ROLE_USER')")
 */
class CustomTimesheetController extends TimesheetAbstractController
{
    /**
     * @var TimesheetRepository
     */
    protected $repository;

    /**
     * @var TrackingModeService
     */
    protected $trackingModeService;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var TimesheetService
     */
    private $service;

    /**
     * EmptyDescriptionCheckerController constructor.
     * @param TimesheetRepository $repository
     * @param TrackingModeService $trackingModeService
     * @param EventDispatcherInterface $dispatcher
     * @param TimesheetService $timesheetService
     */
    public function __construct(
        TimesheetRepository $repository,
        TrackingModeService $trackingModeService,
        EventDispatcherInterface $dispatcher,
        TimesheetService $timesheetService
    )
    {
        $this->repository = $repository;
        $this->trackingModeService = $trackingModeService;
        $this->dispatcher = $dispatcher;
        $this->service = $timesheetService;
    }

    /**
     * @Route(path="/", defaults={"page": 1}, name="custom_timesheet", methods={"GET"})
     * @Route(path="/page/{page}", requirements={"page": "[1-9]\d*"}, name="custom_timesheet_paginated", methods={"GET"})
     * @Security("is_granted('view_own_timesheet')")
     *
     * @param int $page
     * @param Request $request
     * @return Response
     */
    public function indexAction($page, Request $request)
    {
        return $this->index($page, $request, '@CustomTimesheet/index.html.twig', TimesheetMetaDisplayEvent::TIMESHEET);
    }

    /**
     * @Route(path="/create", name="custom_timesheet_create", methods={"GET", "POST"})
     * @Security("is_granted('create_own_timesheet')")
     *
     * @param Request $request
     * @param ProjectRepository $projectRepository
     * @param ActivityRepository $activityRepository
     * @param TagRepository $tagRepository
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createAction(
        Request $request,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        TagRepository $tagRepository
    )
    {
        return $this->create(
            $request,
            '@CustomTimesheet/edit.html.twig',
            $projectRepository,
            $activityRepository,
            $tagRepository
        );
    }

    /**
     * @Route(path="/{id}/edit", name="custom_timesheet_edit", methods={"GET", "POST"})
     * @Security("is_granted('edit', entry)")
     *
     * @param Timesheet $entry
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Timesheet $entry, Request $request)
    {
        return $this->edit($entry, $request, '@CustomTimesheet/edit.html.twig');
    }

    protected function index($page, Request $request, string $renderTemplate, string $location): Response
    {
        $query = new TimesheetQuery();
        $query->setPage($page);

        $form = $this->getToolbarForm($query);
        $form->setData($query);
        $form->submit($request->query->all(), false);

        if (!$form->isValid()) {
            $query->resetByFormError($form->getErrors());
        }

        if (null !== $query->getBegin()) {
            $query->getBegin()->setTime(0, 0, 0);
        }
        if (null !== $query->getEnd()) {
            $query->getEnd()->setTime(23, 59, 59);
        }

        $this->prepareQuery($query);

        $pager = $this->repository->getPagerfantaForQuery($query);

        return $this->render($renderTemplate, [
            'entries' => $pager,
            'page' => $query->getPage(),
            'query' => $query,
            'toolbarForm' => $form->createView(),
            'multiUpdateForm' => $this->getMultiUpdateActionForm()->createView(),
            'showSummary' => $this->includeSummary(),
        ]);
    }

    /**
     * @param TimesheetQuery $query
     * @return FormInterface
     */
    protected function getToolbarForm(TimesheetQuery $query)
    {
        return $this->createForm(TimesheetToolbarForm::class, $query, [
            'action' => $this->generateUrl('custom_timesheet', [
                'page' => $query->getPage(),
            ]),
            'method' => 'GET',
            'include_user' => $this->includeUserInForms('toolbar'),
        ]);
    }

    protected function create(
        Request $request,
        string $renderTemplate,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        TagRepository $tagRepository
    ): Response
    {
        $entry = $this->service->createNewTimesheet($this->getUser());

        if ($request->query->get('project')) {
            $project = $projectRepository->find($request->query->get('project'));
            $entry->setProject($project);
        }

        if ($request->query->get('activity')) {
            $activity = $activityRepository->find($request->query->get('activity'));
            $entry->setActivity($activity);
        }

        $this->service->prepareNewTimesheet($entry, $request);

        $mode = $this->getTrackingMode();
        $createForm = $this->getCreateForm($entry, $mode);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            try {
                $this->service->saveNewTimesheet($entry);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute($this->getTimesheetRoute());
            } catch (\Exception $ex) {
                $this->flashError('action.update.error', ['%reason%' => $ex->getMessage()]);
            }
        }

        return $this->render($renderTemplate, [
            'timesheet' => $entry,
            'form' => $createForm->createView(),
        ]);
    }

    protected function edit(Timesheet $entry, Request $request, string $renderTemplate): Response
    {
        $editForm = $this->getEditForm($entry, $request->get('page'));
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->repository->save($entry);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute($this->getTimesheetRoute(), ['page' => $request->get('page', 1)]);
            } catch (\Exception $ex) {
                $this->flashError('action.update.error', ['%reason%' => $ex->getMessage()]);
            }
        }

        return $this->render($renderTemplate, [
            'timesheet' => $entry,
            'form' => $editForm->createView(),
        ]);
    }

    protected function getCreateForm(Timesheet $entry, TrackingModeInterface $mode): FormInterface
    {
        return $this->createForm(CustomTimesheetEditForm::class, $entry, [
            'action' => $this->generateUrl($this->getCreateRoute()),
            'allow_begin_datetime' => $mode->canEditBegin(),
            'customer' => true,
        ]);
    }

    /**
     * @param Timesheet $entry
     * @param int $page
     * @return FormInterface
     */
    protected function getEditForm(Timesheet $entry, $page)
    {
        $mode = $this->getTrackingMode();

        return $this->createForm(CustomTimesheetEditForm::class, $entry, [
            'action' => $this->generateUrl($this->getEditRoute(), [
                'id' => $entry->getId(),
                'page' => $page,
            ]),
            'allow_begin_datetime' => $mode->canEditBegin(),
            'customer' => true,
        ]);
    }

    protected function getCreateRoute(): string
    {
        return 'custom_timesheet_create';
    }

    protected function getEditRoute(): string
    {
        return 'custom_timesheet_edit';
    }

}
