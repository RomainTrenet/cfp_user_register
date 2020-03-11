<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepCommerceRegister.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;

class MultistepCommerceRegister extends MultistepFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'multistep_user_register_commerce';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Ensure step id, get the step id from form id and store it.
    parent::ensureStoreStepId('information_commerce');
    $step_id = parent::getCurrentStepId();

    // Build parent form at the beginning to get form_state from parent.
    $form = parent::buildForm($form, $form_state);

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
