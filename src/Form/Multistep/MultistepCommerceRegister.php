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
    // Ensure step id, get the step id from form id and store it.
    parent::ensureStoreStepId('information_commerce');

    drupal_set_message('information_commerce !!!');
    drupal_set_message(parent::getCurrentStepId());

    $form = parent::buildForm($form, $form_state);

    $form['age'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Your age'),
      '#default_value' => $this->store->get('age') ? $this->store->get('age') : '',
    );

    $form['location'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Your location'),
      '#default_value' => $this->store->get('location') ? $this->store->get('location') : '',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('age', $form_state->getValue('age'));
    $this->store->set('location', $form_state->getValue('location'));

    // Save the data
    parent::saveData();
    $form_state->setRedirect('some_route');
  }
}
