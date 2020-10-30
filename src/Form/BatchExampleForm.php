<?php

namespace Drupal\user_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a Batch example Form.
 */
class BatchExampleForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'batchexampleform';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
    $form['emailids'] = [
      '#type' => 'textarea', 
      '#title' => 'Email Ids',
      '#size' => 1000,
      '#description' => t('Enter the line separated email ids'),
      '#required' => TRUE,  
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Batch'),
    ]; 

    return $form;
  }
   

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $emailids = $form_state->getValue('emailids');
    $emails = [];
    $emails = explode("\n",$emailids);
    
    $batch = array(
      'title' => t('Verifying Emails...'),
      'operations' => [],
      'init_message'     => t('Commencing'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message'    => t('An error occurred during processing'),
      'finished' => '\Drupal\user_import\DeleteNode::ExampleFinishedCallback',
    );
    foreach ($emails as $key => $value) {
      $email = trim($value);
      $batch['operations'][] = ['\Drupal\user_import\EmailCheck::checkEmailExample',[$email]];
    }

    batch_set($batch);

  }
  
}
