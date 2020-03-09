<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepUserRegister.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class MultistepUserRegister extends MultistepFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'multistep_user_register_user';
  }

  /**
   * {@inheritdoc}.
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Ensure step id, get the step id from form id.
    parent::ensureStoreStepId('user');
    $step_id = parent::getCurrentStepId();

    // Get inner form.
    $inner_form = parent::getStepFormObject($step_id)->buildForm([], $form_state);
    //@todo : record inner form in the store, so that validate can use it.

    // Remove original entity builders and actions.
    if(isset($inner_form['#entity_builders'])) {
      unset($inner_form['#entity_builders']);
    }
    if(isset($inner_form['actions'])) {
      unset($inner_form['actions']);
    }

    // Build form with inner user form.
    $form = [
      '#type' => 'container',
      'form' => $inner_form,
      '#access' => TRUE,
    ];

    // Build parent form at the end, to have buttons at the end.
    $form = parent::buildForm($form, $form_state);

    drupal_set_message('end of user build form');
    return $form;
  }

  // Needed by entityTypeManager.
  public function processForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('email', $form_state->getValue('email'));
    $this->store->set('name', $form_state->getValue('name'));
    $form_state->setRedirect('cfp_user_register.user_register_commerce');
    drupal_set_message('user register commerce submit');
  }
}
