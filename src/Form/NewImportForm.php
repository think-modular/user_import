<?php

    /**
    * @file
    * Contains \Drupal\user_import\Form\NewImportForm.
    */
    namespace Drupal\user_import\Form;

    use Drupal\user_import\Controller\UserImportController;
    use Drupal\Core\Form\FormBase;
    use Drupal\Core\Form\FormStateInterface;
    use Drupal\Core\Ajax\AjaxResponse;
    use Drupal\Core\Ajax\HtmlCommand;
    use Drupal\Core\Url;

class NewImportForm extends FormBase {
    
    /**
    * {@inheritdoc}
    */
    public function getFormId() {
        return 'new_import_form';
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['file_upload'] = [
          '#type' => 'file',
          '#title' => $this->t('Import CSV File'),
          '#size' => 40,
          '#description' => $this->t('Select the CSV file to be imported. Maximum file size: 64 MB.'),
          '#required' => FALSE,
          '#autoupload' => TRUE,
          '#upload_validators' => ['file_validate_extensions' => ['csv']],
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#button_type' => 'primary',
        ];
        return $form;
    }


    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $this->createFile($_FILES,$form_state);
    }


    /**
    * To import data as Content type nodes.
    */
    public function createFile($filedata,$form_state) {
        drupal_flush_all_caches();
        global $base_url;
        // CSV label row for indexing.
        $fieldNames = ['username', 'email', 'status', 'role', 'pass'];
        // Code for import csv file.
        $mimetype = 1;
        if ($mimetype) {

            $location = $filedata['files']['tmp_name']['file_upload'];
            if (($handle = fopen($location, "r")) !== FALSE) {
                $keyIndex = [];
                while (($data = fgetcsv($handle)) !== FALSE) {
                  // $line is an array of the csv elements.
                  $csv_user_data[] = $data;
                }
                foreach ($csv_user_data as $key => $user_value) {
                  foreach ($user_value as $key => $value) {
                    if ($fieldNames[$key] != $value) {
                      $keyIndex[$fieldNames[$key]] = $value;
                    }
                  }
                  $user_index[] = $keyIndex;
                }
                $user_data = array_filter($user_index);

                $this->newImportcallback($csv_user_data);

                $response = Url::fromUserInput('/mypage/page');
				$form_state->setRedirectUrl($response);
            }
        }
    }

    /**
    * New Import Sample CSV Creation.
    */
    public function newImportcallback($newCsvData) {
        global $base_url;
        $result = '';

        $username = 'username,';
        $username .= 'email,';
        $username .= 'status,';
        $username .= 'role,';
        $username .= 'pass,';
        $userFields = substr($username, 0);
        $result .= '</tr></table>';
        $sampleFile = 'authenticate' . '.csv';
        $handle = fopen("sites/default/files/" . $sampleFile, "w+") or die("There is no permission to create log file. Please give permission for sites/default/file!");
        
        foreach ($newCsvData as $line) {
           fputcsv($handle, $line,',',' ');
        }
        fclose($handle);
        
        return true;
    }

}