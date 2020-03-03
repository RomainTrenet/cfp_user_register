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
use Symfony\Component\DependencyInjection\ContainerInterface;
// @todo : adpat with cfp commerce enfity name.
use Drupal\cfp_information_commerce\Entity\InformationCommerceEntity;

class UserShopRegisterForm extends FormBase {

  /**
   * This constant is used as a key inside the main form state object to gather
   * all the inner form state objects.
   * @const
   * @see getInnerFormState()
   */
  const INNER_FORM_STATE_KEY = 'inner_form_state';
  const MAIN_SUBMIT_BUTTON = 'submit';

  /**
   * @var \Drupal\Core\Form\FormInterface[]
   */
  protected $innerForms = [];

  /**
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $element_info_manager;

  /**
   * @var \Drupal\Core\Form\FormValidatorInterface $form_validator
   */
  protected $form_validator;

  /**
   * @var \Drupal\Core\Form\FormSubmitterInterface $form_submitter
   */
  protected $form_submitter;

  /**
   * Class constructor.
   *
   * Initialize the inner form objects : parts of the form.
   *
   * User and information_commerce are entities.
   *
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info_manager
   *   The Element info manager service.
   *
   * @param \Drupal\Core\Form\FormValidatorInterface $form_validator
   *   The form state validator service.
   *
   * @param \Drupal\Core\Form\FormSubmitterInterface $form_submitter
   *   The form submitter service.
   */
  public function __construct(
    ElementInfoManagerInterface $element_info_manager,
    FormValidatorInterface $form_validator,
    FormSubmitterInterface $form_submitter
  ) {

    // Needed services for our form.
    $this->element_info_manager = $element_info_manager;
    $this->form_validator = $form_validator;
    $this->form_submitter = $form_submitter;

    // Get parts (form) of the form.
    $this->innerForms['user'] = \Drupal::entityTypeManager()
      ->getFormObject('user', 'default')
      ->setEntity(User::create());
    $this->innerForms['information_commerce'] = \Drupal::entityTypeManager()
      ->getFormObject('information_commerce', 'default')
      ->setEntity(InformationCommerceEntity::create([
        'type' => 'information_commerce',
      ]));
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
      $container->get('form_submitter')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return implode('__', [
      'combo_form',
      $this->innerForms['user']->getFormId(),
      $this->innerForms['information_commerce']->getFormId(),
    ]);
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
    $form['#process'] = $this->element_info_manager->getInfoProperty('form', '#process', []);
    $form['#process'][] = '::processForm';

    foreach ($this->innerForms as $key => $inner_form_object) {
      $inner_form_state = static::createInnerFormState($form_state, $inner_form_object, $key);

      // By placing the actual inner form inside a container element (such as
      // details) we gain the freedom to alter the wrapper of the inner form
      // with little damage to the render element attributes of the inner form.
      $inner_form = ['#parents' => [$key]];
      $inner_form = $inner_form_object->buildForm($inner_form, $inner_form_state);
      $form[$key] = [
        '#type' => 'container',
        'form' => $inner_form,
      ];

      $form[$key]['form']['#theme_wrappers'] = $this->element_info_manager->getInfoProperty('container', '#theme_wrappers', []);
      unset($form[$key]['form']['form_token']);

      // The process array is called from the FormBuilder::doBuildForm method
      // with the form_state object assigned to the this (ComboForm) object.
      // This results in a compatibility issues because these methods should
      // be called on the inner forms (with their assigned FormStates).
      // To resolve this we move the process array in the inner_form_state
      // object.
      if (!empty($form[$key]['form']['#process'])) {
        $inner_form_state->set('#process', $form[$key]['form']['#process']);
        unset($form[$key]['form']['#process']);
      }
      else {
        $inner_form_state->set('#process', []);
      }

      // The actions array causes a UX problem because there should only be a
      // single save button and not multiple.
      // The current solution is to move the #submit callbacks of the submit
      // element to the inner form element root.
      if (!empty($form[$key]['form']['actions'])) {
        if (isset($form[$key]['form']['actions'][static::MAIN_SUBMIT_BUTTON])) {
          $form[$key]['form']['#submit'] = $form[$key]['form']['actions'][static::MAIN_SUBMIT_BUTTON]['#submit'];
        }

        unset($form[$key]['form']['actions']);
      }
    }

    // Default action elements.
    $form['actions'] = [
      '#type' => 'actions',
      static::MAIN_SUBMIT_BUTTON => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#validate' => ['::validateForm'],
        '#submit' => ['::submitForm'],
      ],
    ];

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
    foreach ($this->innerForms as $key => $inner_form) {
      $inner_form_state = static::getInnerFormState($form_state, $key);
      foreach ($inner_form_state->get('#process') as $callback) {
        // The callback format was copied from FormBuilder::doBuildForm().
        $element[$key]['form'] = call_user_func_array($inner_form_state->prepareCallback($callback), array(&$element[$key]['form'], &$inner_form_state, &$complete_form));
      }
    }

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->innerForms as $form_key => $inner_form) {
      $inner_form_state = static::getInnerFormState($form_state, $form_key);

      // Pass through both the form elements validation and the form object
      // validation.
      $inner_form->validateForm($form[$form_key]['form'], $inner_form_state);
      //$form_validator->validateForm($inner_form->getFormId(), $form[$form_key]['form'], $inner_form_state);
      $this->form_validator->validateForm($inner_form->getFormId(), $form[$form_key]['form'], $inner_form_state);

      foreach ($inner_form_state->getErrors() as $error_element_path => $error) {
        $form_state->setErrorByName($form_key . '][' . $error_element_path, $error);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $inner_form_states = [];

    // First, submit each form.
    foreach ($this->innerForms as $key => $inner_form) {
      $inner_form_states[$key] = static::getInnerFormState($form_state, $key);

      // The form state needs to be set as submitted before executing the
      // doSubmitForm method.
      $inner_form_states[$key]->setSubmitted();
      $this->form_submitter->doSubmitForm($form[$key]['form'], $inner_form_states[$key]);
    }

    // Then, record user id to the commerce.
    $new_uid = $inner_form_states['user']->getValue('uid');
    $cfp_commerce_id = $inner_form_states['information_commerce']->getValue('cfp_commerce_id');

    // Load our custom commerce entity, and set the user id as reference.
    $commerce = InformationCommerceEntity::load($cfp_commerce_id);
    $commerce->set('uid_test', $new_uid);
    $commerce->save();
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
