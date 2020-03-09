<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Form\Multistep\MultistepCommerceRegister.
 */

namespace Drupal\cfp_user_register\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    // Build parent form at the beginning to get form_state from parent.
    $form = parent::buildForm($form, $form_state);

    // Ensure step id, get the step id from form id and store it.
    parent::ensureStoreStepId('information_commerce');
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

    // Build user form with inner user form.
    $form['#type'] = 'container';
    $form[self::INNER_FORM] = $inner_form;
    $form['#access'] = TRUE;

    drupal_set_message('end of commerce build form');
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
    //$this->store->set('age', $form_state->getValue('age'));
    //$this->store->set('location', $form_state->getValue('location'));

    // Save the data
    //parent::saveData();
    //$form_state->setRedirect('some_route');
  }
}
