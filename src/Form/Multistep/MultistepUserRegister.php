<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepUserRegister.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;

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
    // Build parent form at the beginning to get form_state from parent.
    $form = parent::buildForm($form, $form_state);

    /*$values = $this->getStoreStepFormStateValues($step_id);
        ksm($values);
        if(isset($values)) {
          $form_state->setValues($values);
        }*/

    // Ensure step id, get the step id from form id.
    parent::ensureStoreStepId('user');
    $step_id = parent::getCurrentStepId();

    // Get inner form.
    $inner_form = parent::getStepFormObject($step_id)->buildForm([], $form_state);

    // Remove original entity builders and actions.
    if(isset($inner_form['#entity_builders'])) {
      unset($inner_form['#entity_builders']);
    }
    if(isset($inner_form['actions'])) {
      unset($inner_form['actions']);
    }

    // Build user form with inner user form.
    $form['#type'] = 'container';
    $form[self::INNER_FORM] = $inner_form;
    $form['#access'] = TRUE;

    return $form;
  }

  /**
   * Needed by entityTypeManager.
   *
   * {@inheritdoc}.
   */
  public function processForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
