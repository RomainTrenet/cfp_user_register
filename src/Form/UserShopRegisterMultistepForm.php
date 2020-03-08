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
  const INNER_FORM = 'cfp_inner_form';
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
  protected $init_steps_form;

  /**
   * @var array
   */
  protected $matching_step_id_for_form_id;

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

    // This are the initial steps, IE at the beginning of the form.
    $this->init_steps_form = [
      1 => [
        'form_id' => 'user',
        'form_object' => $this->entityTypeManager
          ->getFormObject('user', 'default')
          ->setEntity(User::create()),
        'form' => array(),
        'form_state' => new FormState(),
      ],
      2 => [
        'form_id' => 'information_commerce',
        'form_object' => $this->entityTypeManager
          ->getFormObject('information_commerce', 'default')
          ->setEntity(InformationCommerceEntity::create([
            'type' => 'information_commerce',
          ])),
        'form' => array(),
        'form_state' => new FormState(),
      ],
    ];

    // Record first and last step id.
    reset($this->init_steps_form);
    $this->first_step_id = key($this->init_steps_form);
    end($this->init_steps_form);
    $this->last_step_id = key($this->init_steps_form);

    // Record matching step id for form id.
    foreach ($this->init_steps_form as $step_id => $form_settings) {
      $this->matching_step_id_for_form_id[$form_settings['form_id']] = $step_id;
    }
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
    foreach ($this->init_steps_form as $steps) {
      $parts[] = $steps['form_object']->getFormId();
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

    drupal_set_message('build');
    //////

    // Manage form state.

    // Save storage before setting brand new $form_state.
    //$storage = $form_state->getStorage();
    if(!empty($storage)) {
      $truc = 1;
    }
    else {
      $truc = 2;
    }

    // If form get back after errors, don't touch form state.
    if ($form_state::hasAnyErrors()) {
      // Else,
      drupal_set_message('there are errors');
    } else {
      drupal_set_message('no error');
    }

    //////

    // Instantiate form_state current step id value.
    if(!$form_state->has('current_step_id')) {
      $form_state->set('current_step_id', $this->first_step_id);
    }

    // Instantiate form steps.
    if(!$form_state->has('steps_form')) {
      $form_state->set('steps_form', $this->init_steps_form);
    }

    // Get current step id and form.
    $step_id = $form_state->get('current_step_id');
    $step_form_object = $form_state->get('steps_form')[$step_id]['form_object'];


    // If step is more than first, get $form['#attributes'] and reset $form.
    if($step_id > 1) {
      $attributes = isset($form['#attributes']) ? $form['#attributes'] : NULL;

      // Reset $form.
      $form = [];
      if(isset($attributes)) {
        $form['#attributes'] = $attributes;
      }

      // Delete former form state values.
      //$form_state->setValues([]);

      // @todo check if needed to clean input.
      // or get token, etc.
    }

    // @todo : check if needed.
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
    $inner_form = $step_form_object->buildForm($inner_form, $form_state);
    if(isset($inner_form['#entity_builders'])) {
      unset($inner_form['#entity_builders']);
    }
    $form = [
      '#type' => 'container',
      'form' => $inner_form,
      '#access' => TRUE,
    ];

    /*
    $form[$form_id]['form']['#theme_wrappers'] = $this->elementInfoManager->getInfoProperty('container', '#theme_wrappers', []);
    unset($form[$form_id]['form']['form_token']);
    */
    /*
    if (!empty($form['form']['actions'])) {
      //@todo : no need, because no submit button.
      if (isset($form['form']['actions'][static::MAIN_SUBMIT_BUTTON])) {
        $form['form']['#submit'] = $form['form']['actions'][static::MAIN_SUBMIT_BUTTON]['#submit'];
      }

      unset($form[$form_id]['form']['actions']);
    }*/
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

    // Default action elements.
    $form['form']['actions'] = [
      '#type' => 'actions',
      static::PREVIOUS_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Back'),
        // @todo : check submit submit.
        '#submit' => [
          '#submit' => ['::cfp_user_register_next_previous_form_submit'],
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
          '::cfp_user_register_next_previous_form_submit',
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
    $step_id = $form_state->get('current_step_id');
    $step_form_object = $form_state->get('steps_form')[$step_id]['form_object'];

    // Pass through both the form elements validation and the form object
    // validation.
    $step_form_object->validateForm($form['form'], $form_state);
    $this->formValidator->validateForm($step_form_object->getFormId(), $form['form'], $form_state);
    drupal_set_message('end of validate');
  }

  /**
   * Submit handler for next / previous button.
   *
   * @param array $form
   *   The form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cfp_user_register_next_previous_form_submit(array &$form, FormStateInterface $form_state) {
    // Get the current page that was submitted.
    $step_id = $form_state->get('current_step_id');

    drupal_set_message('next / previous BEGIN | Step : ' . $step_id);

    // Manage next and previous behavior.
    if ($form_state->getValue('next')) {
      // Set "rebuild" to true, so that doSubmit can be executed.
      // Without this, the form is not considered as executed.
      $form_state->setRebuild(TRUE);

      // Record form state in the storage.
      $this::cfp_user_register_store_step_form_state($step_id, $form_state);

      // Increment the page number.
      $step_id ++;
      // @todo check if step id exist.
      $form_state->set('current_step_id', $step_id);
    }
    else if ($form_state->getValue('previous')) {
      // Decrement the page number.
      $step_id --;
      // @todo check if step id exist.
      $form_state->set('current_step_id', $step_id);
    }

    drupal_set_message('next / previous END | Step : ' . $step_id);
  }

  /**
   * Final submit.
   *
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message('Begin of submit | Step : ' . $form_state->get('current_step_id') . ' and form state is : ');

    // Prepare variables for user and commerce entity id.
    // $new_uid = ;
    // $cfp_commerce_id;

    // Get $form and $form_state for each subform.
    $steps_form = $form_state->get('steps_form');//[$step_id]['form_object'];
    // @todo : dynamically get form and form state.
    /*foreach ($steps_form as $step_id => $form_settings) {

    }*/

    // Do user register form submission.
    $user_step_id = $this::cfp_user_register_get_form_step_id('user');
    if($user_step_id) {
      // @todo : get form and form state from storage.
      $user_form_state = $this->cfp_user_register_get_storage_step_form_state($user_step_id, $form_state);
      //$form_state->get('steps_form')[$user_step_id]['form_object']->submitForm($form['form'], $form_state);
      //$form_state->get('steps_form')[$user_step_id]['form_object']->save($form['form'], $form_state);
    }

    /*
    // Then, record user id to the commerce.
    $new_uid = $inner_form_states['user']->getValue('uid');
    $cfp_commerce_id = $inner_form_states['information_commerce']->getValue('cfp_commerce_id');

    // Load our custom commerce entity, and set the user id as reference.
    InformationCommerceEntity::load($cfp_commerce_id)
      ->set('uid_test', $new_uid)
      ->save();

    drupal_set_message('end of submit and form state is :');
    */
  }

  /**
   * Store form state for a step, in the $form_state storage.
   *
   * @param $step_id
   *   The id of the step.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function cfp_user_register_store_step_form_state($step_id, FormStateInterface $form_state) {
    drupal_set_message('store step form state');

    // Get the entire steps_form because we can't set only one child.
    $steps_form = $form_state->get('steps_form');

    // Set new value.
    if (isset($steps_form[$step_id]['form_state'])) {
      $steps_form[$step_id]['form_state'] = $form_state;
    }

    // Finally record.
    $form_state->set('steps_form', $steps_form);
  }

  /**
   * Get storage form state for a step.
   *
   * @param $step_id
   *   The id of the step.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface $form_states|null
   */
  private function cfp_user_register_get_storage_step_form_state($step_id, FormStateInterface $form_state) {
    // Get the entire steps_form because we can't set only one child.
    $steps_form = $form_state->get('steps_form');

    // Return form state.
    if (isset($steps_form[$step_id]['form_state'])) {
      return $steps_form[$step_id]['form_state'];
    } else {
      return NULL;
    }
  }

  /**
   * Get step if from form id.
   *
   * @param $form_id
   *   The form id.
   *
   * @return integer\null
   *   The step id.
   */
  private function cfp_user_register_get_form_step_id($form_id) {
    if (isset($this->matching_step_id_for_form_id[$form_id])) {
      return $this->matching_step_id_for_form_id[$form_id];
    } else {
      return NULL;
    }
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
  /*
  protected static function getInnerFormState(FormStateInterface $form_state, $key) {
    /** @var \Drupal\Core\Form\FormStateInterface $inner_form_state * /
    $inner_form_state = $form_state->get([static::INNER_FORM_STATE_KEY, $key]);
    $inner_form_state->setCompleteForm($form_state->getCompleteForm());
    $inner_form_state->setValues($form_state->getValues() ?: []);
    $inner_form_state->setUserInput($form_state->getUserInput() ?: []);
    return $inner_form_state;
  }
  */

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
  /*
  protected static function createInnerFormState(FormStateInterface $form_state, FormInterface $form_object, $key) {
    $inner_form_state = new FormState();
    $inner_form_state->setFormObject($form_object);
    $form_state->set([static::INNER_FORM_STATE_KEY, $key], $inner_form_state);
    return $inner_form_state;
  }
  */

}
