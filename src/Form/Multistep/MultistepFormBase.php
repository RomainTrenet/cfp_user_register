<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepFormBase.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class MultistepFormBase extends FormBase {

  /**
   * This constant is used as a key inside the main form state object to gather
   * all the inner form state objects.
   * @const
   * @see getInnerFormState()
   */
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
  private $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;


  /**
   * @var array
   */
  protected $init_steps_form;

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
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;

    $this->store = $this->tempStoreFactory->get('multistep_data');

    // This are the initial steps, used at the beginning.
    $this->init_steps_form = [
      1 => [
        'form_id' => 'user',
        'form_route' => 'cfp_user_register.user_register_user',
        'form_object' => NULL,
        'form' => array(),
        'form_state' => NULL,
      ],
      2 => [
        'form_id' => 'information_commerce',
        'form_route' => 'cfp_user_register.user_register_commerce',
        'form_object' => NULL,
        'form' => array(),
        'form_state' => NULL,
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
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Start a manual session for anonymous users.
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['multistep_form_holds_session'])) {
      $_SESSION['multistep_form_holds_session'] = true;
      $this->sessionManager->start();
    }

    //@todo remove.
    //$this->setCurrentStepId(1);

    // Instantiate current step id value.
    // By security, each sub-form ensure step id through init_steps_form.
    $current_step_id = $this->getCurrentStepId();
    if(empty($current_step_id)) {
      $this->setCurrentStepId($this->first_step_id);
      $current_step_id = $this->first_step_id;
    }

    // Default action elements.
    $form['form']['actions'] = [
      '#type' => 'actions',
      static::PREVIOUS_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Back'),
        '#submit' => [
          '::cfp_user_register_next_previous_form_submit',
        ],
        '#access' => $current_step_id != $this->first_step_id && $this->first_step_id != $this->last_step_id,
        //'#limit_validation_errors' => [],
        '#weight' => 1,
      ],
      static::NEXT_BUTTON => [
        '#type' => 'submit',
        '#value' => t('Next'),
        //'#validate' => ['::validateForm'], @todo
        '#submit' => [
          '::cfp_user_register_step_form_submit',
          '::cfp_user_register_next_previous_form_submit',
        ],
        //'#limit_validation_errors' => $validation,
        '#access' => $current_step_id != $this->last_step_id && $this->first_step_id != $this->last_step_id,
        '#weight' => 2,
      ],
      static::MAIN_SUBMIT_BUTTON => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        //'#validate' => ['::validateForm'], @todo.
        '#submit' => [
          '::cfp_user_register_step_form_submit',
          '::cfp_user_register_final_form_submit',
        ],
        '#access' => $current_step_id == $this->last_step_id,
        '#weight' => 3,
      ],
    ];

    drupal_set_message('end of build');
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

    drupal_set_message('next / previous BEGIN | Step : ' . $step_id);

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
    if (array_key_exists($step_id, $this->init_steps_form)) {
      $form_state->setRedirect($this->getStepRoute($step_id));
    }

    drupal_set_message('next / previous END | Step : ' . $step_id);
  }

  public function cfp_user_register_step_form_submit(array &$form, FormStateInterface $form_state) {
    drupal_set_message('step form submit');
    /*

    // Get the current page that was submitted.
    $step_id = $this->getCurrentStepId();

    // Set "rebuild" to true, so that doSubmit can be executed.
    // Without this, the form is not considered as executed.
    $form_state->setRebuild(TRUE);

    // Record form state in the storage.
    $this::cfp_user_register_store_step_form_state($step_id, $form_state);
    */
  }

  public function cfp_user_register_final_form_submit(array &$form, FormStateInterface $form_state) {
    $this->setCurrentStepId(1);
    drupal_set_message('hello !');
  }

  /**
   * @todo
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

    /*
    // Get the entire steps_form because we can't set only one child.
    $steps_form = $form_state->get('steps_form');

    // Set new value.
    if (isset($steps_form[$step_id]['form_state'])) {
      $steps_form[$step_id]['form_state'] = $form_state;
    }

    // Finally record.
    $form_state->set('steps_form', $steps_form);*/
  }

  private function getStepRoute($step_id) {
    if (isset($this->init_steps_form[$step_id]['form_route'])) {
      return $this->init_steps_form[$step_id]['form_route'];
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
   * Get step if from form id.
   *
   * @param $form_id
   *   The form id.
   *
   * @return integer\null
   *   The step id.
   */
  protected function cfp_user_register_get_form_step_id($form_id) {
    if (isset($this->matching_step_id_for_form_id[$form_id])) {
      return $this->matching_step_id_for_form_id[$form_id];
    } else {
      return NULL;
    }
  }
}
