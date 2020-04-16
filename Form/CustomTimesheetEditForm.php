<?php

namespace KimaiPlugin\CustomTimesheetBundle\Form;

use App\Entity\Timesheet;
use App\Form\FormTrait;
use App\Form\Type\DatePickerType;
use App\Form\Type\DurationType;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Timesheet\UserDateTimeFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the form used to manipulate Timesheet entries.
 */
class CustomTimesheetEditForm extends AbstractType
{
    use FormTrait;

    /**
     * @var CustomerRepository
     */
    private $customers;
    /**
     * @var ProjectRepository
     */
    private $projects;
    /**
     * @var UserDateTimeFactory
     */
    protected $dateTime;

    /**
     * @param CustomerRepository $customer
     * @param ProjectRepository $project
     * @param UserDateTimeFactory $dateTime
     */
    public function __construct(CustomerRepository $customer, ProjectRepository $project, UserDateTimeFactory $dateTime)
    {
        $this->customers = $customer;
        $this->projects = $project;
        $this->dateTime = $dateTime;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $activity = null;
        $project = null;
        $customer = null;
        $begin = null;
        $customerCount = $this->customers->countCustomer(true);
        $timezone = $this->dateTime->getTimezone()->getName();
        $isNew = true;

        if (isset($options['data'])) {
            /** @var Timesheet $entry */
            $entry = $options['data'];

            $activity = $entry->getActivity();
            $project = $entry->getProject();
            $customer = null === $project ? null : $project->getCustomer();

            if (null !== $entry->getId()) {
                $isNew = false;
            }

            if (null === $project && null !== $activity) {
                $project = $activity->getProject();
            }

            if (null !== ($begin = $entry->getBegin())) {
                $timezone = $begin->getTimezone()->getName();
            }
        }

        $dateTimeOptions = [
            'model_timezone' => $timezone,
            'view_timezone' => $timezone,
        ];

        // primarily for API usage, where we cannot use a user/locale specific format
        if (null !== $options['date_format']) {
            $dateTimeOptions['format'] = $options['date_format'];
        }

        if ($options['allow_begin_datetime']) {
            $builder->add('begin', DatePickerType::class, array_merge($dateTimeOptions, [
                'label' => 'label.begin'
            ]));
        }

        $this->addDuration($builder);

        if ($this->showCustomer($options, $isNew, $customerCount)) {
            $this->addCustomer($builder, $customer);
        }

        $this->addProject($builder, $isNew, $project, $customer);
        $this->addActivity($builder, $activity, $project);
        $this->addDescription($builder);
    }

    protected function showCustomer(array $options, bool $isNew, int $customerCount): bool
    {
        if (!$isNew && $options['customer']) {
            return true;
        }

        if ($customerCount < 2) {
            return false;
        }

        if (!$options['customer']) {
            return false;
        }

        return true;
    }

    protected function addDuration(FormBuilderInterface $builder)
    {
        $builder->add('duration', DurationType::class, [
            'required' => true,
            'docu_chapter' => 'timesheet.html#duration-format',
            'attr' => [
                'placeholder' => '08:00'
            ]
        ]);

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {
                /** @var Timesheet|null $data */
                $data = $event->getData();
                if (null === $data || null === $data->getEnd()) {
                    $event->getForm()->get('duration')->setData(null);
                }
            }
        );

        // make sure that duration is mapped back to end field
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                /** @var Timesheet $data */
                $data = $event->getData();
                $duration = $data->getDuration();
                $end = null;
                if (null !== $duration) {
                    $end = clone $data->getBegin();
                    $end->modify('+ ' . $duration . 'seconds');
                }
                $data->setEnd($end);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Timesheet::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'timesheet_edit',
            'include_user' => false,
            'docu_chapter' => 'timesheet.html',
            'method' => 'POST',
            'date_format' => null,
            'customer' => false, // for API usage
            'allow_begin_datetime' => true,
            'attr' => [
                'data-form-event' => 'kimai.timesheetUpdate',
                'data-msg-success' => 'action.update.success',
                'data-msg-error' => 'action.update.error',
            ],
        ]);
    }
}
