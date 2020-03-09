<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepFormBase.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

// @todo : adpat with cfp commerce enfity name.
use Drupal\cfp_information_commerce\Entity\InformationCommerceEntity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\user\Entity\User;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class MultistepFormBase extends FormBase {

  /**
   * This constant is used as a key inside the main form state object to gather
   * all the inner form state objects.
   * @const
   * @see getInnerFormState()
   */
  const INNER_FORM = 'form';
  const FORM_STATE = 'form_state';
  const CURRENT_STEP_ID = 'current_step_id';
  const MAIN_SUBMIT_BUTTON = 'submit';
  const PREVIOUS_BUTTON = 'previous';
  const NEXT_BUTTON = 'next';

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  // @todo : it was private.
  protected $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * @var \Drupal\Core\Entity\EntityTypeInterface $entityTypeManager;
   */
  protected $entityTypeManager;

  /**
   * @var array
   */
  protected $steps_form;

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
   * @var array
   */
  protected $matching_step_id_for_form_id;

  /**
   * Constructs a \Drupal\cfp_user_register\Form\Multistep\MultistepFormBase.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    SessionManagerInterface $session_manager,
    AccountInterface $current_user,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entityTypeManager;

    $this->store = $this->tempStoreFactory->get('multistep_data');

    // This are the initial steps, used at the beginning.
    $this->steps_form = [
      1 => [
        'form_id' => 'user',
        'form_route' => 'cfp_user_register.user_register_user',
        'form_object' => $this->entityTypeManager
          ->getFormObject('user', 'default')
          ->setEntity(User::create()),
      ],
      2 => [
        'form_id' => 'information_commerce',
        'form_route' => 'cfp_user_register.user_register_commerce',
        'form_object' => $this->entityTypeManager
          ->getFormObject('information_commerce', 'default')
          ->setEntity(InformationCommerceEntity::create([
            'type' => 'information_commerce',
          ])),
      ],
    ];

    // Record first and last step id.
    reset($this->steps_form);
    $this->first_step_id = key($this->steps_form);
    end($this->steps_form);
    $this->last_step_id = key($this->steps_form);

    // Record matching step id for form id.
    foreach ($this->steps_form as $step_id => $form_settings) {
      $this->matching_step_id_for_form_id[$form_settings['form_id']] = $step_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Instantiate current step id value.
    // By security, each sub-form ensure step id through steps_form.
    $step_id = $this->getCurrentStepId();
    if(empty($step_id)) {
      $this->setCurrentStepId($this->first_step_id);
      $step_id = $this->first_step_id;
    }

    // @todo : form state.
    /*$values = $this->getStoreStepFormStateValues($step_id);
    ksm($values);
    if(isset($values)) {
      $form_state->setValues($values);
    }*/
    //$form_state = new FormState();

    $fs = $this->getStoreStepFormState($step_id);
    if(isset($fs)) {
      //$form_state = $fs;
    }

    // Start a manual session for anonymous users.
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['multistep_form_holds_session'])) {
      $_SESSION['multistep_form_holds_session'] = true;
      $this->sessionManager->start();
    }

    // Default action elements.
    $form['actions'] = [
      '#type' => 'actions',
      static::PREVIOUS_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Back'),
        '#submit' => [
          '::cfp_user_register_next_previous_form_submit',
        ],
        '#access' => $step_id != $this->first_step_id && $this->first_step_id != $this->last_step_id,
        //'#limit_validation_errors' => [],
        '#weight' => 1,
      ],
      static::NEXT_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Next'),
        '#validate' => ['::customValidateForm'],
        '#submit' => [
          '::cfp_user_register_step_form_submit',
          '::cfp_user_register_next_previous_form_submit',
        ],
        //'#limit_validation_errors' => $validation,
        '#access' => $step_id != $this->last_step_id && $this->first_step_id != $this->last_step_id,
        '#weight' => 2,
      ],
      static::MAIN_SUBMIT_BUTTON => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#validate' => ['::customValidateForm'],
        '#submit' => [
          '::cfp_user_register_step_form_submit',
          '::cfp_user_register_final_form_submit',
        ],
        '#access' => $step_id == $this->last_step_id,
        '#weight' => 3,
      ],
    ];

    return $form;
  }

  /**
   * @todo : check if necessary.
   * Saves the data from the multistep form.
   */
  protected function saveData() {
    // Logic for saving data goes here...
    $this->deleteStore();
    drupal_set_message($this->t('The form has been saved.'));

  }

  /**
   * @todo : check if necessary.
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = ['name', 'email', 'age', 'location'];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }

  /**
   * Submit handler for each steps.
   *
   * @param array $form
   *   The form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cfp_user_register_step_form_submit(array &$form, FormStateInterface $form_state) {
    // Get the current page that was submitted.
    $step_id = $this->getCurrentStepId();

    // Record form state in the storage.
    $this::setStoreStepFormState($step_id, $form_state);
  }

  /**
   * Submit handler for next / previous button.
   *
   * @param array $form
   *   The form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function cfp_user_register_next_previous_form_submit(array &$form, FormStateInterface $form_state) {
    // Get the current page that was submitted.
    $step_id = $this->getCurrentStepId();

    // Get operation : next or previous.
    $operation = '';
    if (isset($form_state->getTriggeringElement()['#parents'][0])) {
      $operation = $form_state->getTriggeringElement()['#parents'][0];
    }

    // Increment or decrement step number.
    if ($operation == self::NEXT_BUTTON) {
      $step_id++;
    }
    else if ($operation == self::PREVIOUS_BUTTON) {
      $step_id--;
    }
    // Record it.
    $this->setCurrentStepId($step_id);

    // Manage form state redirect if step_ip exists.
    if (array_key_exists($step_id, $this->steps_form)) {
      $form_state->setRedirect($this->getStepRoute($step_id));
    }
  }

  public function cfp_user_register_final_form_submit(array &$form, FormStateInterface $form_state) {
    // Get the current page that was submitted.
    $step_id = $this->getCurrentStepId();

    // Do user register form submission.
    $user_step_id = $this::cfp_user_register_get_form_step_id('user');
    if($user_step_id) {
      $user_form_state = $this->getStoreStepFormState($user_step_id);
      if(!empty($user_form_state)) {
        $this->steps_form[$user_step_id]['form_object']->submitForm($form['form'], $user_form_state);
        $this->steps_form[$user_step_id]['form_object']->save($form['form'], $user_form_state);
      }
    }

    //
    $commerce_step_id = $this::cfp_user_register_get_form_step_id('information_commerce');
    if($commerce_step_id) {
      $commerce_form_state = $this->getStoreStepFormState($commerce_step_id);
      if(!empty($commerce_form_state)) {
        $this->steps_form[$commerce_step_id]['form_object']->submitForm($form['form'], $commerce_form_state);
        $this->steps_form[$commerce_step_id]['form_object']->save($form['form'], $commerce_form_state);
      }
    }

    // Then, record user id to the commerce.
    $new_uid = $user_form_state->getValue('uid');
    // @todo : adapt cfp_commerce_id.
    $cfp_commerce_id = $commerce_form_state->getValue('cfp_commerce_id');

    // Load our custom commerce entity, and set the user id as reference.
    // @todo : adapt 'uid_test' with commerce's user entity id reference.
    InformationCommerceEntity::load($cfp_commerce_id)
      ->set('uid_test', $new_uid)
      ->save();
  }

  /**
   * Validate form.
   *
   * As the form is splited in steps, validate only the current step form.
   * {@inheritDoc}
   */
  public function customValidateForm(array &$form, FormStateInterface $form_state) {
    $step_id = $this->getCurrentStepId();
    $step_form_object = $this->getStepFormObject($step_id);

    // Pass through both the form elements validation and the form object
    // validation.
    $step_form_object->validateForm($form[static::INNER_FORM], $form_state);
  }

  /**
   * Construct form state stored variable name.
   *
   * @param $form_id
   *   The form id concerned.
   *
   * @return string
   *   The form state variable name.
   */
  private function getFormStateStoredName($step_id) {
    // @todo : int to string.
    $form_id = $this::cfp_user_register_get_step_form_id($step_id);
    return self::FORM_STATE . '_step_' . strval($form_id);
  }

  /**
   * Get form state values for a step, in the $form_state storage.
   *
   * @param $step_id
   *   The id of the step.
   *
   * @return mixed
   *   Array of values.
   */
  /*
  protected function getStoreStepFormStateValues($step_id) {
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    $values = $this->store->get($fs_stored_name);
    if(!empty($values)) {
      return $values;
    }
    return FALSE;
  }
  */
  protected function getStoreStepFormState($step_id) {
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    $fs = $this->store->get($fs_stored_name);
    if(!empty($fs)) {
      return $fs;
    }
    return FALSE;
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
  /*protected function setStoreStepFormStateValues($step_id, FormStateInterface $form_state) {
    // Get name of the store variable.
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    $values = $form_state->getValues();
    try {
      $this->store->set($fs_stored_name, $values);
    } catch (TempStoreException $e) {
    }
  }*/
  protected function setStoreStepFormState($step_id, FormStateInterface $form_state) {
    // Get name of the store variable.
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    try {
      $this->store->set($fs_stored_name, $form_state);
    } catch (TempStoreException $e) {
    }
  }

  /**
   * Get route for a step id.
   * @param $step_id
   *   The id of the step.
   *
   * @return mixed
   *   The route.
   */
  private function getStepRoute($step_id) {
    if (isset($this->steps_form[$step_id]['form_route'])) {
      return $this->steps_form[$step_id]['form_route'];
    }
  }

  /**
   * Get form object for a step id.
   * @param $step_id
   *   The id of the step.
   *
   * @return object
   *   The form object.
   */
  protected function getStepFormObject($step_id) {
    if (isset($this->steps_form[$step_id]['form_object'])) {
      return $this->steps_form[$step_id]['form_object'];
    }
  }

  /**
   * Get current id from private store.
   *
   * @return int
   *   The current step id.
   */
  protected function getCurrentStepId() {
    return $this->store->get(static::CURRENT_STEP_ID);
  }

  /**
   * Store current step id in private store.
   *
   * @param $step_id
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setCurrentStepId($step_id) {
    if(is_integer($step_id)) {
      $this->store->set('current_step_id', $step_id);
    }
  }

  /**
   * Ensure the store step id value, from form_id and init_step_forms.
   *
   * @param $form_id
   *
   */
  protected function ensureStoreStepId($form_id) {
    $step_id = $this::cfp_user_register_get_form_step_id($form_id);
    try {
      $this::setCurrentStepId($step_id);
    } catch (TempStoreException $e) {
    }
  }

  /**
   * Get step id from form id.
   *
   * @param $form_id
   *   The form id.
   *
   * @return integer
   *   The step id.
   */
  protected function cfp_user_register_get_form_step_id($form_id) {
    if (isset($this->matching_step_id_for_form_id[$form_id])) {
      return $this->matching_step_id_for_form_id[$form_id];
    } else {
      return NULL;
    }
  }

  /**
   * Get step id from form id.
   *
   * @param $form_id
   *   The form id.
   *
   * @return integer\null
   *   The step id.
   */
  protected function cfp_user_register_get_step_form_id($step_id) {
    if (isset($this->steps_form[$step_id])) {
      return $this->steps_form[$step_id]['form_id'];
    } else {
      return NULL;
    }
  }
}
