<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepFormBase.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

// @todo : delete, this is an example with custom entity.
//use Drupal\cfp_information_commerce\Entity\InformationCommerceEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\user\Entity\User;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
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
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
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
   * @var array
   */
  protected $steps_access_settings;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a \Drupal\cfp_user_register\Form\Multistep\MultistepFormBase.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
        'title' => t('User information\'s'),
        'form_id' => 'user',
        'form_route' => 'cfp_user_register.user_register_user',
        'form_object' => $this->entityTypeManager
          ->getFormObject('user', 'default')
          ->setEntity(User::create()),
      ],
      2 => [
        'title' => t('Commerce information\'s'),
        'form_id' => 'information_commerce',
        'form_route' => 'cfp_user_register.user_register_commerce',
        'form_object' => $this->entityTypeManager
          ->getFormObject('node', 'default')
          ->setEntity(
            $this->entityTypeManager
              ->getStorage('node')
              ->create(['type' => 'choucroute'])
          ),
        /*
        @todo : delete, this is an example with custom entity.
        'form_object' => $this->entityTypeManager
          ->getFormObject('information_commerce', 'default')
          ->setEntity(InformationCommerceEntity::create([
            'type' => 'information_commerce',
          ])),
        */
      ],
    ];

    // Steps, stored to use for access checking.
    $this->steps_access_settings = [];
    foreach ($this->steps_form as $step_id => $form_settings) {
      // Create array on route key.
      $this->steps_access_settings[$form_settings['form_route']] = [
        // Register status "passed" and id.
        'step_id' => $step_id,
        'passed' => FALSE,
      ];
    }

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

    // Instantiate store access settings.
    if(!$this->store->get('steps_access_settings')) {
      $this->cfp_user_register_store_steps_access_settings();
    }

    // Start a manual session for anonymous users.
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['multistep_form_holds_session'])) {
      $_SESSION['multistep_form_holds_session'] = true;
      $this->sessionManager->start();
    }

    // Breadcrumb form element.
    $form['breadcrumb'] = $this->cfp_user_register_get_breadcrumb();

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
   * Submit handler for each steps.
   *
   * @param array $form
   *   The form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function cfp_user_register_step_form_submit(array &$form, FormStateInterface $form_state) {
    // Get the current page that was submitted, store a copy.
    $step_id = $this->getCurrentStepId();
    $former_step_id = $step_id;

    // Record form state in the storage.
    $this::setStoreStepFormState($step_id, $form_state);

    // Then record status of step to manage rights access.
    $this->cfp_user_register_set_steps_access_settings_passed($former_step_id, TRUE);
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

  /**
   * Final submit function.
   *
   * @param array $form
   *   The form array.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function cfp_user_register_final_form_submit(array &$form, FormStateInterface $form_state) {
    // Do user entity register form submission.
    $user_form_state = '';
    $user_step_id = $this::cfp_user_register_get_form_step_id('user');
    if($user_step_id) {
      $user_form_state = $this->getStoreStepFormState($user_step_id);
      if(!empty($user_form_state)) {
        $this->steps_form[$user_step_id]['form_object']->submitForm($form['form'], $user_form_state);
        $this->steps_form[$user_step_id]['form_object']->save($form['form'], $user_form_state);
      }
    }

    // Do commerce entity
    $commerce_form_state = '';
    $commerce_step_id = $this::cfp_user_register_get_form_step_id('information_commerce');
    if($commerce_step_id) {
      $commerce_form_state = $this->getStoreStepFormState($commerce_step_id);
      if(!empty($commerce_form_state)) {
        $this->steps_form[$commerce_step_id]['form_object']->submitForm($form['form'], $commerce_form_state);
        $this->steps_form[$commerce_step_id]['form_object']->save($form['form'], $commerce_form_state);
      }
    }

    // Then, record user id to the commerce.
    // @todo : adapt 'nid' if needed, with the machine name of your commerce node id key.
    if ($user_form_state->getValue('uid') && $commerce_form_state->getValue('nid')) {
      $new_uid = $user_form_state->getValue('uid');
      // @todo : adapt 'nid' with the machine name of your commerce node id key.
      $cfp_commerce_id = $commerce_form_state->getValue('nid');

      // Load our custom commerce entity
      // Set the user id as reference and as author.
      // @todo : adapt 'field_uid' with the machine name value of your user's reference field id.
      $this->entityTypeManager
        ->getStorage('node')
        ->load($cfp_commerce_id)
        ->set('field_uid', $new_uid)
        ->set('uid', $new_uid)
        ->save();
      // @todo : delete, this is an example with custom entity.
      /*InformationCommerceEntity::load($cfp_commerce_id)
        ->set('uid_test', $new_uid)
        ->save();*/

      // Redirect to first step.
      // @todo : adapt if you want to go somewhere else.
      $redirect_route = $this->steps_form[1]['form_route'];
      $form_state->setRedirect($redirect_route);

      // Deleting store form state values from the private store.
      $this->deleteAllStoreStepFormState();

      // Deleting step access settings values from the private store.
      $this->store->delete('steps_access_settings');
    }

  }

  /**
   * Validate form.
   *
   * As the form is splited in steps, validate only the current step form.
   * {@inheritDoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function customValidateForm(array &$form, FormStateInterface $form_state) {
    $step_id = $this->getCurrentStepId();
    $step_form_object = $this->getStepFormObject($step_id);

    // Validate form.
    $step_form_object->validateForm($form[static::INNER_FORM], $form_state);

    // Unvalidated stored settings if error.
    if ($form_state->hasAnyErrors()) {
      $this->cfp_user_register_set_steps_access_settings_passed($step_id, FALSE);
    }
  }

  /**
   * Construct form state stored variable name.
   *
   * @param $step_id
   *   The step id concerned.
   *
   * @return string
   *   The form state variable name.
   */
  private function getFormStateStoredName($step_id) {
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
  protected function setStoreStepFormState($step_id, FormStateInterface $form_state) {
    // Get name of the store variable.
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    try {
      $this->store->set($fs_stored_name, $form_state);
    } catch (TempStoreException $e) {
    }
  }

  /**
   * Delete form_state stored data.
   *
   * @param $step_id
   *   The step to which delete data.
   */
  protected function deleteStoreStepFormState($step_id) {
    // Get name of the store variable.
    $fs_stored_name = $this->getFormStateStoredName($step_id);
    try {
      $this->store->delete($fs_stored_name);
    } catch (TempStoreException $e) {
    }
  }

  /**
   * Delete all form_state stored data.
   */
  protected function deleteAllStoreStepFormState() {
    foreach ($this->steps_form as $step_id => $step_form) {
      $this->deleteStoreStepFormState($step_id);
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

  /**
   * Get the breadcrumb from steps.
   *
   * @return array
   *   The data to render.
   */
  private function cfp_user_register_get_breadcrumb() {
    $steps = [];
    $current_step_id = $this->getCurrentStepId();

    // Construct steps informations.
    foreach ($this->steps_form as $step_id => $step_form) {
      $steps[$step_id] = [
        'title' => $step_form['title'],
        'current' => $step_id == $current_step_id,
      ];
    }

    // Return data to render.
    return array(
      '#theme' => 'user_register_breadcrumb',
      '#steps' => $steps,
      '#weight' => '-100',
    );
  }

  /**
   * Stores steps access settings in private store.
   *
   * Used for access validation.
   */
  private function cfp_user_register_store_steps_access_settings() {
    if(isset($this->steps_access_settings)) {
      try {
        $this->store->set('steps_access_settings', $this->steps_access_settings);
      } catch (TempStoreException $e) {
      }
    }
  }

  /**
   * Set access setting "passed" for a step, to manage rights access.
   *
   * @param integer $step_id
   *   The step id.
   * @param boolean $status
   *   If is passed
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  private function cfp_user_register_set_steps_access_settings_passed($step_id, $status) {
    if (isset($this->steps_form[$step_id])) {
      // Get route from step id.
      $route = $this->steps_form[$step_id]['form_route'];

      // We have to take entire value.
      $steps_access_settings = $this->store->get('steps_access_settings');
      if(isset($steps_access_settings[$route])) {
        $steps_access_settings[$route]['passed'] = $status;
      }

      // Finally record.
      $this->store->set('steps_access_settings', $steps_access_settings);
    }
  }
}
