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
    // Ensure step id, get the step id from form id and store it.
    parent::ensureStoreStepId('user');

    drupal_set_message('user !!!');
    drupal_set_message(parent::getCurrentStepId());

    $form = parent::buildForm($form, $form_state);

    // @todo : get user form and remove user action.
    // @todo add user form to form.

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#default_value' => $this->store->get('name') ? $this->store->get('name') : '',
    );

    $form['email'] = array(
      '#type' => 'email',
      '#title' => $this->t('Your email address'),
      '#default_value' => $this->store->get('email') ? $this->store->get('email') : '',
    );

    //$form['actions']['submit']['#value'] = $this->t('Next');

    drupal_set_message('end of user build form');
    return $form;
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
