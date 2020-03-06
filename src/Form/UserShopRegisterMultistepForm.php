<?php

namespace Drupal\cfp_user_register\Form;

use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
// @todo : adpat with cfp commerce enfity name.
use Drupal\cfp_information_commerce\Entity\InformationCommerceEntity;

class UserShopRegisterMultistepForm extends FormBase {

  /**
   * This constant is used as a key inside the main form state object to gather
   * all the inner form state objects.
   * @const
   * @see getInnerFormState()
   */
  const INNER_FORM_STATE_KEY = 'inner_form_state';
  const INNER_FORM_KEY = 'inner_form';
  const MAIN_SUBMIT_BUTTON = 'submit';
  const PREVIOUS_BUTTON = 'previous';
  const NEXT_BUTTON = 'next';

  /**
   * @var \Drupal\Core\Form\FormInterface[]
   */
  protected $innerForms = [];

  /**
   * @var array
   */
  protected $temp_steps;

  /**
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfoManager;

  /**
   * @var \Drupal\Core\Form\FormValidatorInterface $formValidator
   */
  protected $formValidator;

  /**
   * @var \Drupal\Core\Form\FormSubmitterInterface $formSubmitter
   */
  protected $formSubmitter;

  /**
   * @var \Drupal\Core\Entity\EntityTypeInterface $entityTypeManager;
   */
  protected $entityTypeManager;

  /**
   * The form steps id ranged by page id.
   * @var array
   */
  protected $form_step_ids;

  /**
   * The first step id, to avoid calculating many times.
   * @var int|string|null
   */
  protected $first_step_id;

  /**
   * The last step id, to avoid calculating many times.
   * @var int|string|null
   */
  protected $last_step_id;

  /**
   * Class constructor.
   *
   * Initialize the inner form objects : parts of the form.
   *
   * User and information_commerce are entities.
   *
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager
   *   The Element info manager service.
   * @param \Drupal\Core\Form\FormValidatorInterface $formValidator
   *   The form state validator service.
   * @param \Drupal\Core\Form\FormSubmitterInterface $formSubmitter
   *   The form submitter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    ElementInfoManagerInterface $elementInfoManager,
    FormValidatorInterface $formValidator,
    FormSubmitterInterface $formSubmitter,
    EntityTypeManagerInterface $entityTypeManager
  ) {

    // Needed services for our form.
    $this->elementInfoManager = $elementInfoManager;
    $this->formValidator = $formValidator;
    $this->formSubmitter = $formSubmitter;
    $this->entityTypeManager = $entityTypeManager;

    // @todo : delete.
    // Get parts (form) of the form.
    /*
    $this->innerForms['user'] = $this->entityTypeManager
      ->getFormObject('user', 'default')
      ->setEntity(User::create());
    // @todo : adapt with correct entity name.
    $this->innerForms['information_commerce'] = $this->entityTypeManager
      ->getFormObject('information_commerce', 'default')
      ->setEntity(InformationCommerceEntity::create([
        'type' => 'information_commerce',
      ]));
    */

    $this->temp_steps = [
      1 => [
        'form_id' => 'user',
        'form' => $this->entityTypeManager
          ->getFormObject('user', 'default')
          ->setEntity(User::create()),
        'form_state' => '',
      ],
      2 => [
        'form_id' => 'information_commerce',
        'form' => $this->entityTypeManager
          ->getFormObject('information_commerce', 'default')
          ->setEntity(InformationCommerceEntity::create([
            'type' => 'information_commerce',
          ])),
        'form_state' => '',
      ],
    ];
    // Record first and last step id.
    reset($this->temp_steps);
    $this->first_step_id = key($this->temp_steps);
    end($this->temp_steps);
    $this->last_step_id = key($this->temp_steps);

    // Define form steps and forms id.
    $this->form_step_ids = [
      1 => 'user',
      2 => 'information_commerce',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load element info service.
      $container->get('element_info'),
      $container->get('form_validator'),
      $container->get('form_submitter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    $parts = [
      'combo_form',
    ];
    foreach ($this->temp_steps as $steps) {
      $parts[] = $steps['form']->getFormId();
    }
    return implode('__', $parts);
  }

  /**
   * The build form needs to take care of the following:
   *   - Creating a custom form state object for each inner form (and keep it
   *     inside the main form state.
   *   - Generating a render array for each inner form.
   *   - Handle compatibility issues such as #process array and action elements.
   *
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Instantiate current step id value, if the form doesn't have one.
    if (!$form_state->has('current_step_id')) {
      $form_state->set('current_step_id', $this->first_step_id);
    }

    //////////
    // Get current form object with state.
    $step_id = $form_state->get('current_step_id');
    //$step_id = 2;
    //$form_id = $this->temp_steps[$step_id]['form_id'];
    $inner_form_object = $this->temp_steps[$step_id]['form'];

    // If step is more than first, get $form['#attributes'] and reset $form.
    if($step_id > 1) {
      $attributes = isset($form['#attributes']) ? $form['#attributes'] : NULL;

      // Reset $form.
      $form = [];
      if(isset($attributes)) {
        $form['#attributes'] = $attributes;
      }

      // Delete former form state values.
      $form_state->setValues([]);
    }

    $form['#process'] = $this->elementInfoManager->getInfoProperty('form', '#process', []);
    $form['#process'][] = '::processForm';

    /*
    // The process array is called from the FormBuilder::doBuildForm method
    // with the form_state object assigned to the this (ComboForm) object.
    // This results in a compatibility issues because these methods should
    // be called on the inner forms (with their assigned FormStates).
    // To resolve this we move the process array in the inner_form_state
    // object.
    if (!empty($form[$form_id]['form']['#process'])) {
      $inner_form_state->set('#process', $form[$form_id]['form']['#process']);
      unset($form[$form_id]['form']['#process']);
    }
    else {
      $inner_form_state->set('#process', []);
    }*/

    $inner_form = [];
    $inner_form = $inner_form_object->buildForm($inner_form, $form_state);
    if(isset($inner_form['#entity_builders'])) {
      unset($inner_form['#entity_builders']);
    }
    $form = [
      '#type' => 'container',
      'form' => $inner_form,
      '#access' => TRUE,
    ];
    //$form[$form_id]['form']['#theme_wrappers'] = $this->elementInfoManager->getInfoProperty('container', '#theme_wrappers', []);
    //unset($form[$form_id]['form']['form_token']);

    /*if (!empty($form['form']['actions'])) {
      //@todo : no need, because no submit button.
      if (isset($form['form']['actions'][static::MAIN_SUBMIT_BUTTON])) {
        $form['form']['#submit'] = $form['form']['actions'][static::MAIN_SUBMIT_BUTTON]['#submit'];
      }

      unset($form[$form_id]['form']['actions']);
    }*/

    // Default action elements.
    $form['form']['actions'] = [
      '#type' => 'actions',
      static::PREVIOUS_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Back'),
        '#submit' => [
          '#submit' => ['::my_module_register_next_previous_form_submit'],
        ],
        '#access' => $form_state->get('current_step_id') != $this->first_step_id && $this->first_step_id != $this->last_step_id,
        //'#limit_validation_errors' => [],
        '#weight' => 1,
      ],
      static::NEXT_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Next'),
        '#validate' => ['::validateForm'],
        '#submit' => [
          '::my_module_register_next_previous_form_submit'
        ],
        //'#limit_validation_errors' => $validation,
        '#access' => $form_state->get('current_step_id') != $this->last_step_id && $this->first_step_id != $this->last_step_id,
        '#weight' => 2,
      ],
      static::MAIN_SUBMIT_BUTTON => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#validate' => ['::validateForm'],
        '#submit' => ['::submitForm'],
        '#access' => $form_state->get('current_step_id') == $this->last_step_id,
        '#weight' => 3,
      ],
    ];

    // The actions array causes a UX problem because there should only be a
    // single save button and not multiple.
    // The current solution is to move the #submit callbacks of the submit
    // element to the inner form element root.
    /*if (!empty($form[$form_id]['form']['actions'])) {
      //@todo : no need, because no submit button.
      /*if (isset($form[$form_id]['form']['actions'][static::MAIN_SUBMIT_BUTTON])) {
        $form[$form_id]['form']['#submit'] = $form[$form_id]['form']['actions'][static::MAIN_SUBMIT_BUTTON]['#submit'];
      }* /

      unset($form[$form_id]['form']['actions']);
    }*/
    /////////

    drupal_set_message('end of build');
    return $form;
  }

  /**
   * This method will be called from FormBuilder::doBuildForm during the process
   * stage.
   * In here we call the #process callbacks that were previously removed.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $complete_form
   * @return array
   *   The altered form element.
   *
   * @see \Drupal\Core\Form\FormBuilder::doBuildForm()
   */
  public function processForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    return $element;
  }

  /**
   * Validate form.
   *
   * As the form is splited in steps, validate only the current step form.
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->has('current_step_id')) {
      $step_id = $form_state->get('current_step_id');
      //$inner_form_id = $this->form_step_ids[$step_id];

      // @todo : problem with last submit.
      //$inner_form_state = static::getInnerFormState($form_state, $inner_form_id);

      // Pass through both the form elements validation and the form object
      // validation.
      $this->temp_steps[$step_id]['form']->validateForm($form['form'], $form_state);
      $this->formValidator->validateForm($this->temp_steps[$step_id]['form']->getFormId(), $form['form'], $form_state);

      // @todo if form state get errors none, record form state in $temp_steps

      /*foreach ($form_state->getErrors() as $error_element_path => $error) {
        $form_state->setErrorByName($error_element_path, $error);
      }*/

    }
    drupal_set_message('end of validate');
  }

  /**
   * {@inheritDoc}
   */
  /*
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ksm('submit | Step : ' . $form_state->get('current_step_id'));
    if ($form_state->has('current_step_id')) {
      // Get the steps, current and last step number, inner form state.
      $current_step_id = $form_state->get('current_step_id');
      $current_inner_form_id = $this->form_step_ids[$current_step_id];

      //$last_step = array_key_last($steps);
      $inner_form_state = static::getInnerFormState($form_state, $current_inner_form_id);

      // The form state needs to be set as submitted before executing the
      // doSubmitForm method.
      $inner_form_state->setSubmitted();
      $this->formSubmitter->doSubmitForm($form[$current_inner_form_id]['form'], $inner_form_state);

      // If last step, then record user id to the commerce.
      if ($current_step_id == $this->last_step_id) {
        $new_uid = $inner_form_state['user']->getValue('uid');
        //$cfp_commerce_id = $inner_form_states['information_commerce']->getValue('cfp_commerce_id');
        $cfp_commerce_id = static::getInnerFormState($form_state, $current_inner_form_id)->getValue('cfp_commerce_id');

        // Load our custom commerce entity, and set the user id as reference.
        InformationCommerceEntity::load($cfp_commerce_id)
          ->set('uid_test', $new_uid)
          ->save();
      }
    }
  }*/

  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message('Begin of submit | Step : ' . $form_state->get('current_step_id') . ' and form state is : ');
    $inner_form_states = [];

    // First, submit each form.
    /*foreach ($this->form_step_ids as $step_id => $form_id) {
      // The form state needs to be set as submitted before executing the
      // doSubmitForm method.
      $form_state->get([static::INNER_FORM_STATE_KEY, $form_id])->setSubmitted();
      //$inner_form_states[$form_id]->setSubmitted();

      //$inner_form_states[$form_id] = static::getInnerFormState($form_state, $form_id);
      $inner_form_states[$form_id] = $form_state->get([static::INNER_FORM_STATE_KEY, $form_id]);

      //@todo : submit user form doesn't work.

      // $form[$form_id]['form'] -> form_id user missing.
      if(isset($this->innerForms[$form_id])) {
        //$current_form = $this->innerForms[$form_id];
        $current_form_state = $inner_form_states[$form_id];
        //$this->formSubmitter->doSubmitForm($current_form, $current_form_state);
        //$this->innerForms[$form_id]->submitForm($this->innerFormsArray[$form_id], $inner_form_states[$form_id]);
        //$this->formSubmitter->doSubmitForm($this->innerFormsArray[$form_id], $inner_form_states[$form_id]);
        //$this->formSubmitter->doSubmitForm( ->submitForm($this->innerForms[$form_id]->getFormId(), $form[$form_id]['form'], $inner_form_states[$form_id]);
        if($form_id == 'user') {
          /*
          $user_values = $inner_form_states['user']->getValues();
          $new_user = User::create($user_values);
          $truc = $this->entityTypeManager
            ->getFormObject('user', 'default')
            //->setEntity($new_user)
            ->submitForm($current_form, $current_form_state);
          //$this->innerForms['user']->submitForm($truc, $form_state);
          //\Drupal::formBuilder()->submitForm('user_register', $current_form_state);
          //$this->formSubmitter->submitForm($form[$form_id]['form'], $inner_form_states[$form_id]);
          * /

        }
      }
      /*if(isset($form[$form_id])) {
        //ksm($form[$form_id]['form']);
        //ksm($inner_form_states[$form_id]);
        //$this->formSubmitter->doSubmitForm($form[$form_id]['form'], $inner_form_states[$form_id]);
      }* /

    }*/

/*
    // Then, record user id to the commerce.
    $new_uid = $inner_form_states['user']->getValue('uid');
    $cfp_commerce_id = $inner_form_states['information_commerce']->getValue('cfp_commerce_id');

    // Load our custom commerce entity, and set the user id as reference.
    InformationCommerceEntity::load($cfp_commerce_id)
      ->set('uid_test', $new_uid)
      ->save();

    drupal_set_message('end of submit and form state is :');
    ksm($inner_form_states);
*/
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function my_module_register_next_previous_form_submit(array &$form, FormStateInterface $form_state) {

    // Get the current page that was submitted.
    $step_id = $form_state->get('current_step_id');
    $inner_form_id = $this->form_step_ids[$step_id];

    drupal_set_message('next / previous BEGIN | Step : ' . $step_id . ' and form state is :');
    ksm($form_state);//OK step 1

    if ($form_state->getValue('next')) {
      // Increment the page number.
      $step_id ++;
    }
    else if ($form_state->getValue('previous')) {
      // Decrement the page number.
      $step_id --;
    }

    $inner_form_states = [];

    // First, submit each form.
    /*
    foreach ($this->innerForms as $key => $inner_form) {
      $inner_form_states[$key] = static::getInnerFormState($form_state, $key);

      // The form state needs to be set as submitted before executing the
      // doSubmitForm method.
      $inner_form_states[$key]->setSubmitted();
    }*/

    $form_state
      ->set('current_step_id', $step_id )
      //->set('page_values', $page_values)
      ->setRebuild(TRUE);

    drupal_set_message('next / previous END | Step : ' . $step_id);
  }

  /**
   * Before returning the innerFormState object, we need to set the
   * complete_form, values and user_input properties from the main form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The main form state.
   * @param string $key
   *   The key used to store the inner form state.
   * @return \Drupal\Core\Form\FormStateInterface
   *   The inner form state.
   */
  protected static function getInnerFormState(FormStateInterface $form_state, $key) {
    /** @var \Drupal\Core\Form\FormStateInterface $inner_form_state */
    $inner_form_state = $form_state->get([static::INNER_FORM_STATE_KEY, $key]);
    $inner_form_state->setCompleteForm($form_state->getCompleteForm());
    $inner_form_state->setValues($form_state->getValues() ?: []);
    $inner_form_state->setUserInput($form_state->getUserInput() ?: []);
    return $inner_form_state;
  }

  /**
   * After the initialization of the inner form state, we need to assign it with
   * the inner form object and set it inside the main form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The main form state.
   * @param \Drupal\Core\Form\FormInterface $form_object
   *   The inner form object
   * @param string $key
   *   The key used to store the inner form state.
   * @return \Drupal\Core\Form\FormStateInterface
   *   The inner form state.
   */
  protected static function createInnerFormState(FormStateInterface $form_state, FormInterface $form_object, $key) {
    $inner_form_state = new FormState();
    $inner_form_state->setFormObject($form_object);
    $form_state->set([static::INNER_FORM_STATE_KEY, $key], $inner_form_state);
    return $inner_form_state;
  }

}
