<?php

/**
 * @file
 * Contains \Drupal\user_import\Form\Multistep\MultistepOneForm.
 */

namespace Drupal\user_import\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

class MultistepOneForm extends MultistepFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'multistep_form_one';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    $form['browser_upload'] = array(
      '#type' => 'details',
      '#title' => $this->t('BROWSER UPLOAD'),
      '#description' => $this->t('Upload a CSV file.'),
      '#open' => TRUE
    );

    $form['browser_upload']['file_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#size' => 40,
      '#description' => $this->t('Select the CSV file to be imported.'),
      '#required' => TRUE,
      '#autoupload' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
    ];

    $form['file_settings'] = array(
      '#type' => 'details',
      '#title' => $this->t('FILE SETTINGS'),
      '#description' => $this->t('File column delimiter'),
      '#open' => TRUE
    );


    $form['file_settings']['file_upload'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#default_value' => ',',
      '#size' => 40,
      '#description' => $this->t("The column delimiter for the file. Use '/t' for Tab."),
      '#required' => TRUE,
    ];

    $form['actions']['submit']['#value'] = $this->t('Next');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

        $csv_user_data = [];

        $delimiter = $form_state->getValue('file_upload');

        $delimiters = ["/t", ";", "|", ","];

        if(in_array($delimiter, $delimiters)){

          $location = $_FILES['files']['tmp_name']['file_upload'];
          if (($handle = fopen($location, "r")) !== FALSE) {
              while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                // $line is an array of the csv elements.
                $csv_user_data[] = $data;
              }  
          }
          
        } else {
          drupal_set_message($this->t('The CSV column delimiter could not been found. Should be semicolon, comma or pipe!'),'error');
          return;
        }
        

        $this->store->set('file_data', $csv_user_data);


    $form_state->setRedirect('user_import.multistep_two');
  }
}