<?php

/**
 * @file
 * Contains \Drupal\user_import\Form\Multistep\MultistepTwoForm.
 */

namespace Drupal\user_import\Form\Multistep;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\profile\Entity\Profile;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\node\Entity\Node;


class MultistepTwoForm extends MultistepFormBase {

	/**
   	* {@inheritdoc}.
   	*/
	public function getFormId() {
		return 'multistep_form_two';
  	}

  	/**
   	* {@inheritdoc}.
   	*/
	public function buildForm(array $form, FormStateInterface $form_state) {

	  	$file_data = $this->store->get('file_data');

	  	//echo "<pre>";
	  	//print_r($file_data);die;

	  	$list_of_roles = user_role_names();
	    $csv_column_array = [];

	    foreach ($file_data[0] as $key => $user_value) {
	    	$csv_column_array[] = $user_value;
	    }

	    $form = parent::buildForm($form, $form_state);

	    $moduleHandler = \Drupal::service('module_handler');
		if ($moduleHandler->moduleExists('group')){

			$database = \Drupal::database();
			$query = $database->query("SELECT * FROM groups_field_data");
			$group_result = $query->fetchAll();

			if(count($group_result) > 0){
				$form['group'] = array(
				    '#type' => 'details',
				    '#title' => $this->t('GROUPS'),
				    '#description' => $this->t("Select any group to add users."),
				    '#open' => TRUE
			    );

				$group_listing = [];

				foreach ($group_result as $key => $group) {
					$current_user   = \Drupal::currentUser();
					$group_load          = \Drupal\group\Entity\Group::load($group->id);
			  		if ($group_load->getMember($current_user)) {
			  			$group_listing[] = [
					        'value' => $group->id,
					        'label' => $group->label
					    ];
			  		}
				}

				$form['group']['add_group'] = [
			      '#type' => 'textfield',
			      '#title' => $this->t('Enter group name'),
			      '#autocomplete_route_name' => 'user_import.autocomplete',
			      '#size' => 20,
				  	'#ajax' => [
				        'event' => 'autocompleteclose',
				        'callback' => '::userImportcallback',
				        'wrapper' => 'user_import_fields_change_wrapper',
				        'progress' => [
				          'type' => 'throbber',
				          'message' => $this->t('Verifying permission...'),
				        ],
			    	],
			    ];

		        $form['group']['add_role'] = array(
					'#type' => 'select',
					'#title' => $this->t('Select role/roles'),
					'#prefix' => '<div id="first">',
					'#suffix' => '</div>',
					'#default_value' => 0,
					'#validated' => True,
					'#options' => array( 0 => '------------------'),
				);

		        $form['group']['import_ct_markup'] = [
			    	'#suffix' => '<div id="user_import_fields_change_wrapper"></div>',
			    ];
			}
		}


		if ($moduleHandler->moduleExists('profile')){

			$database = \Drupal::database();
			$profile_type_query = $database->query("SELECT value FROM key_value WHERE collection LIKE '%profile%'");
			$profile_type_result = $profile_type_query->fetchAll();

			$profile_type = array();

			foreach ($profile_type_result as $key => $type) {
				$profile_type_value = unserialize($profile_type_result[$key]->value);
				$explode_dot_profile = explode('.', $profile_type_value[0]);
				$profile_type[0] = $this->t('---------------');
				$profile_type[$explode_dot_profile[2]] = $explode_dot_profile[2];
			}

			$profile_field_count = [];

			foreach ($file_data[0] as $key => $value) {
		    	if (strpos($value, 'field_') !== false) {
		    		$profile_field_count[] = $value;
		    	}
		    }

		    if(count($profile_field_count) > 0){
				$form['profile_type_field'] = array(
				    '#type' => 'details',
				    '#title' => $this->t('PROFILE TYPES'),
				    '#description' => $this->t('When you mapping profile fields, it must be within the profile type.'),
				    '#open' => TRUE
			    );


				// Add the headers.
			    $form['profile_type_field']['profile_csv_user_column'] = array(
			        '#type' => 'table',
			        '#title' => 'Sample Table',
			        '#header' => array('CSV COLUMN', 'PROFILE TYPE'),
			    );

			    foreach ($file_data[0] as $key => $user_value) {
			    	if(!empty($user_value)){
				    	if (strpos($user_value, 'user__') !== false) {

				    	} else if (strpos($user_value, 'field_') !== false) {

						    $form['profile_type_field']['profile_csv_user_column'][$key]['profile_csv_column'] = array(
					            '#type' => 'textfield',
					            '#title' => t('CSV COLUMN'),
					            '#title_display' => 'invisible',
					            '#default_value' => $user_value,
					            '#size' => 20,
					            '#attributes' => array('disabled' => 'disabled', 'class' => array('csv_column_text') ),
					        );

					        $form['profile_type_field']['profile_csv_user_column'][$key]['profile_field'] = array(
					            '#type' => 'select',
					            '#title' => t('PROFILE TYPE'),
					            '#title_display' => 'invisible',
					            '#default_value' => 0,
					            '#options' => $profile_type
					        );
						}
					}   
			    }
			} 
		}

	    $form['csv_file_data'] = array(
	        '#type' => 'hidden',
	        '#title' => 'Sample Column',
	        '#title_display' => 'invisible',
	        '#value' => array('csv_file_data' => $file_data),
	    );

	    $form['options'] = array(
	    	'#type' => 'details',
	    	'#title' => $this->t('OPTIONS'),
	    	'#open' => TRUE
	    );

	    $form['options']['send_email'] = array(
		    '#type' => 'checkbox',
		    '#title' => $this->t('Send Email'),
		    '#description' => $this->t("Send email to users when their account is created."),
		    '#default_value' => $this->store->get('send_email') ? $this->store->get('send_email') : ''
	    );

	    $form['role_assign'] = array(
		    '#type' => 'details',
		    '#title' => $this->t('ROLE ASSIGN'),
		    '#open' => TRUE
	    );

	    $form['role_assign']['override_role'] = [
		    '#type' => 'radios',
		    '#options' => [
		        '1' => 'True',
		        '0' => 'False',
		    ],
		    '#default_value' => '0',
		    '#title' => $this->t('Override Role From CSV'),
		    '#size' => 40,
		    '#description' => $this->t('Override role from CSV and create role if not exist in Durpal.'),
	    ];

	    $form['email_message'] = array(
		    '#type' => 'details',
		    '#title' => $this->t('EMAIL MESSAGE'),
		    '#open' => TRUE
	    );

	    $form['email_message']['message_subject'] = array(
		    '#type' => 'textfield',
		    '#title' => $this->t('Message Subject'),
		    '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:mail], [site:login-url], [user:one-time-login-url]"),
		    '#default_value' => $this->store->get('message_subject') ? $this->store->get('message_subject') : ''
	    );

	    $form['email_message']['message'] = array(
		    '#type' => 'text_format',
		    '#title' => $this->t('Message'),
		    '#format' => 'full_html',
		    '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:mail], [site:login-url], [user:one-time-login-url],[user:password]"),
		    '#default_value' => $this->store->get('message') ? $this->store->get('message') : ''
	    );

	    $form['second_email_message'] = array(
		    '#type' => 'details',
		    '#title' => $this->t('EMAIL MESSAGE FOR EXISTING ACCOUNT'),
		    '#open' => TRUE
	    );

	    $form['second_email_message']['second_message_subject'] = array(
		    '#type' => 'textfield',
		    '#title' => $this->t('Message Subject'),
		    '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:mail], [site:login-url], [user:one-time-login-url]"),
		    '#default_value' => $this->store->get('message_subject') ? $this->store->get('message_subject') : ''
	    );

	    $form['second_email_message']['second_message'] = array(
		    '#type' => 'text_format',
		    '#title' => $this->t('Message'),
		    '#format' => 'full_html',
		    '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:mail], [site:login-url], [user:one-time-login-url],[user:password]"),
		    '#default_value' => $this->store->get('message') ? $this->store->get('message') : ''
	    );

	    $form['actions']['previous'] = array(
		    '#type' => 'link',
		    '#title' => $this->t('Previous'),
		    '#attributes' => array(
		        'class' => array('button'),
		    ),
		    '#weight' => 0,
		    '#url' => Url::fromRoute('user_import.multistep_one'),
	    );

	    return $form;
  	}


  	/**
   	* User Import Sample CSV Creation.
   	*/
  	public function userImportcallback(array &$form, FormStateInterface $form_state) {
	    global $base_url;

	    $result = '';
	    $ajax_response = new AjaxResponse();
	    $group_explode = explode('-', $form_state->getValue('add_group'));
	    $group_id = $group_explode[1];

  		$current_user   = \Drupal::currentUser();
  		$group          = \Drupal\group\Entity\Group::load($group_id);
  		$group_type     = $group->bundle();

  		if ($group->getMember($current_user)) {
			// User is a member...
			$group_roles = \Drupal::entityTypeManager()->getStorage('group_role')->loadByProperties([
		      'group_type' => $group_type,
		      'internal' => FALSE,
		    ]);

			$role_names = [];

		    foreach ($group_roles as $role_id => $group_role) {
			    if(!$group_role->isInternal()) { // checks if you created this role.
			    	$role_names[0] = '------------------';
				    $role_names[$role_id] = $group_role->label();
			    }
		    }
		    
	    	if(count($role_names) > 0){
	    		$form['group']['add_role']['#options'] = $role_names;
	    	}
			
			$form_state->setRebuild(TRUE);


			$ajax_response->addCommand(new HtmlCommand("#user_import_fields_change_wrapper", ''));
			$ajax_response->addCommand(new HtmlCommand("#first", ($form['group']['add_role'])));
			return $ajax_response;
		}else{
			$role = 'You do not have permission to add member in this group.';

			$form['group']['add_role']['#options'] = array(0 => '------------------');

			$ajax_response->addCommand(new HtmlCommand("#first", ($form['group']['add_role'])));
		    $ajax_response->addCommand(new HtmlCommand('#user_import_fields_change_wrapper', $role));
		    return $ajax_response;
		}
    	
    	
  	}


  	/**
   	* {@inheritdoc}
   	*/
  	public function submitForm(array &$form, FormStateInterface $form_state) {
        global $base_url;

        $values = $form_state->getValues();
        $message = $values['message']['value'];
        $email_format = 0;
        $message_subject = $values['message_subject'];
	    $add_role = $values['add_role'];

	    // Second email message for existing account
	    $second_message = $values['second_message']['value'];
        $second_email_format = 0;
        $second_message_subject = $values['second_message_subject'];

	    $send_email = $values['send_email'];
	    $override_role = $values['override_role'];

	    $csv_file_data = $values['csv_file_data']['csv_file_data'];
	    $extra_account_fields = [];

	    if ( (!in_array("email", $csv_file_data[0])) && (!in_array("name", $csv_file_data[0]))){
	    	
			drupal_set_message($this->t('The file is not compatible - please ensure name or email columns are available in the CSV file.'),'error');
			    return;
		    
	    }

	    foreach ($csv_file_data[0] as $key => $value) {
	    	if (strpos($value, 'user__') !== false) {
	    		$extra_account_fields[] = $value;
	    	}
	    }

	    $fieldNames = [];
	    $keyIndex = [];
	    $user_data = [];

	    
	    foreach ($csv_file_data[0] as $key => $csv_header) {
	    	$fieldNames[] = $csv_header;
	    }
	    foreach ($csv_file_data as $u_key => $user_value) {
		    foreach ($user_value as $key => $value) {
			   	if ($fieldNames[$key] != $value) {
			    	$keyIndex[$fieldNames[$key]] = $value;
			    }
		    }
		    $user_index[] = $keyIndex;
	    }
	    $user_data = array_filter($user_index);

	    $profile_csv_user_column= [];

	    if(!empty($values['profile_csv_user_column'])){
	    	$profile_csv_user_column = $values['profile_csv_user_column'];
	    }


	    $batch = array(
		    'title' => t('Verifying Emails...'),
		    'operations' => [],
		    'init_message'     => t('Commencing'),
		    'progress_message' => t('Processed @current out of @total.'),
		    'error_message'    => t('An error occurred during processing'),
		    'finished' => '\Drupal\user_import\Form\Multistep\MultistepTwoForm::callBackFinished',
		    'progressive' => true,
	    );


	    foreach ($user_data as $value) {

	      $batch['operations'][] = ['\Drupal\user_import\Form\Multistep\MultistepTwoForm::importUsers',[$values]];
	    }

	    batch_set($batch);
  	}


  	public static function send_template_email($value, $message_subject, $message, $email_format)
  	{
  		global $base_url;

  		$site_name = \Drupal::config('system.site')->get('name');
	  	$mailManager = \Drupal::service('plugin.manager.mail');
		$module = 'user_import';
		$key = 'create_user'; // Replace with Your key
	    $to = $value['email'];
	    $email = $value['email'];

	    $new_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $value['email']]);
		$new_user = reset($new_user);

	    $user_display_name = $new_user->getDisplayName();
	    $user_name = $value['name'];

	    if (strpos($message_subject, '[site:name]') !== false) {
	    	$message_subject = str_replace("[site:name]",$site_name,$message_subject);
	    }

	    if (strpos($message_subject, '[user:mail]') !== false) {
	    	$message_subject = str_replace("[user:mail]",$email,$message_subject);
	    }

	    if (strpos($message, '[user:display-name]') !== false) {
	    	$message = str_replace("[user:display-name]",$user_display_name,$message); 
	    }

	    if (strpos($message, '[user:name]') !== false) {
	    	$message = str_replace("[user:name]",$user_name,$message); 
	    }

	    if (strpos($message, '[site:name]') !== false) {
	    	$message = str_replace("[site:name]",$site_name,$message); 
	    }

	    if (strpos($message, '[user:mail]') !== false) {
	    	$message = str_replace("[user:mail]",$email,$message); 
	    }

		$uid = $new_user->id();

		// Create a timestamp.
		$timestamp = \Drupal::time()->getRequestTime();
		// Set the redirect location after the user of the one time login.
		$path = '' ;

	    // Create login link from route (Copy pasted this from the drush package)
		$one_time_login_url = Url::fromRoute(
		    'user.reset.login',
		    [
		      'uid' => $uid,
		      'timestamp' => $timestamp,
		      'hash' => user_pass_rehash($new_user, $timestamp),
		    ],
		    [
		      'absolute' => true,
		      'query' => $path ? ['destination' => $path] : [],
		      'language' => \Drupal::languageManager()->getLanguage($new_user->getPreferredLangcode()),
		    ]
		)->toString();

		if (strpos($message, '[user:one-time-login-url]') !== false) {
	    	$message = str_replace("[user:one-time-login-url]",$one_time_login_url,$message); 
	    }

	    $site_login_url = $base_url.'/user';

	    if (strpos($message, '[site:login-url]') !== false) {
	    	$message = str_replace("[site:login-url]",$site_login_url,$message); 
	    }

	    $user_password = $value['pass'];

	    if (strpos($message, '[user:password]') !== false) {
	    	$message = str_replace("[user:password]",$user_password,$message); 
	    }

		$params['message_subject'] = $message_subject;
		$params['message'] = $message;
		$params['email_format'] = $email_format;
		$params['title'] = $site_name;
		$params['[site:name]'] = $site_name;

		$langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
		$send = true;

		$result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
		if ($result['result'] != true) {
		    $err_message = t('There was a problem sending your email notification to @email.', array('@email' => $to));
		    \Drupal::logger('mail-log')->error($err_message);
		}

		$success_message = t('An email notification has been sent to @email ', array('@email' => $to));
		\Drupal::logger('mail-log')->notice($success_message);

		return true;
  	}

  	public static function add_member($roles,$group_id,$user_email)
  	{
  		// Add Member
  		$values = ['gid' => $group_id];
		$group = \Drupal\group\Entity\Group::load($group_id);
		$group_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user_email]);
		$group_user_account = reset($group_user);
		$group->addMember($group_user_account, $values);


		// Add roles to user
		$member = $group->getMember($group_user_account);
		$membership = $member->getGroupContent();

		// Get the roles of the owner
		$member_roles = $member->getRoles();

		foreach ($roles as $value) {
			if(!$member_roles[$value]){
				$membership->group_roles[] = $value;
				$membership->save();
			}
		}
  	}

  	public static function add_member_without_role($group_id,$user_email)
  	{
  		// Add Member
  		$values = ['gid' => $group_id];
		$group = \Drupal\group\Entity\Group::load($group_id);
		$group_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user_email]);
		$group_user_account = reset($group_user);
		$group->addMember($group_user_account, $values);
  	}


  	public static function convert_unicode($string) {
		//return mb_convert_encoding($string, 'UTF-8', 'HTML-ENTITIES');

		//return \Drupal\Component\Utility\Unicode::convertToUtf8($string,'ISO-8859-1');
		return utf8_encode($string);
	}

	/**
   	* @param $entity
   	* Deletes an entity
   	*/
	public static function importUsers($values, &$context) {
		$language = \Drupal::languageManager()->getCurrentLanguage()->getId();

	    $entity = Node::create([
	        'type' => 'page',
	        'langcode' => 'und',
	        'title' => t('Users Import'),
	      ]
	    );
	    $entity->save();
	    $context['results'][] = t('Users Import');
	    $context['message'] = t('Created @title', array('@title' => 'Users Import'));

	    // Get form values
	    $message = $values['message']['value'];
        $email_format = 0;
        $message_subject = $values['message_subject'];
	    $add_role = $values['add_role'];

	    // Second email message for existing account
	    $second_message = $values['second_message']['value'];
        $second_email_format = 0;
        $second_message_subject = $values['second_message_subject'];

	    $send_email = $values['send_email'];
	    $override_role = $values['override_role'];

	    $csv_file_data = $values['csv_file_data']['csv_file_data'];
	    $extra_account_fields = [];

	    foreach ($csv_file_data[0] as $key => $value) {
	    	if (strpos($value, 'user__') !== false) {
	    		$extra_account_fields[] = $value;
	    	}
	    }

	    $fieldNames = [];
	    $keyIndex = [];
	    $user_data = [];

	    
	    foreach ($csv_file_data[0] as $key => $csv_header) {
	    	$fieldNames[] = $csv_header;
	    }
	    foreach ($csv_file_data as $u_key => $user_value) {
		    foreach ($user_value as $key => $value) {
			   	if ($fieldNames[$key] != $value) {
			    	$keyIndex[$fieldNames[$key]] = $value;
			    }
		    }
		    $user_index[] = $keyIndex;
	    }
	    $user_data = array_filter($user_index);

	    $profile_csv_user_column= [];

	    if(!empty($values['profile_csv_user_column'])){
	    	$profile_csv_user_column = $values['profile_csv_user_column'];
	    }

	    foreach ($user_data as $value) {
	    	
	    	if (filter_var($value['email'], FILTER_VALIDATE_EMAIL)) {
		    	$user_status = user_load_by_mail($value['email']);
		    	if (empty($user_status)) {
		    		// Create new user
		    		$user = User::create();
			        $user->uid = '';
			        $name = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['name']);
			        $user->setUsername($name);

			        $email = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['email']);
			        $user->setEmail($email);
			        $user->set("init", $email);

			        $pass = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['pass']);
			        $user->setPassword($pass);
			        $user->enforceIsNew();

			        if( ($value['status'] == '1') || ($value['status'] == '0') ){
			        	$user->set("status", $value['status']);
			        }else{
			        	drupal_set_message(t('Please input correct status value. It should be 0 or 1'),'error');
			        }

			        if(!empty($value['langcode'])){
			        	$langcode = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['langcode']);

			        	$user->set("langcode", $langcode);
				        $user->set("preferred_langcode", $langcode);
				        $user->set("preferred_admin_langcode", $langcode);
			        } else {
			        	$user->set("langcode", $language);
				        $user->set("preferred_langcode", $language);
				        $user->set("preferred_admin_langcode", $language);
			        }
			        
			        if(!empty($value['timezone'])){
				        //- Set Time Zone to London
				        $timezone = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['timezone']);
						$user->set('timezone',$timezone);
					} else{
						$system_site_config = \Drupal::config('system.date');
	      				$default_timezone = $system_site_config->get('timezone')['default'];
						$user->set('timezone',$default_timezone);
					}
			        

		        	
		        	if (((int) $override_role == 1)) {

		        		$role_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['role']);

		        		// List out all roles.
			            $list_of_roles = user_role_names();
			            $user_role = str_replace(' ', '_', $role_convert);
			            if (!in_array($user_role, $list_of_roles) && (array_key_exists($user_role, $list_of_roles) === FALSE)) {

			            	if (strpos($role_convert, ',') !== false) {
			            		$comma_separated_role = explode(',',$role_convert);
			            		foreach ($comma_separated_role as $urole) {
			            			if(array_key_exists($urole, $list_of_roles)){
			            				$user->addRole($urole);
			            			}else{
			            				// Create Role.
						              	$role = Role::create([
						                	'id' => str_replace(' ', '_', strtolower($urole)),
						                	'label' => str_replace('_', ' ', ucwords($urole)),
						              	]);
						              	$role->save();
						              	// New role created and assign to user.
						              	$user->addRole($role->id());
			            			}
			            		}
			            	}else{
			            		// Create Role.
				              	$role = Role::create([
				                	'id' => str_replace(' ', '_', strtolower($user_role)),
				                	'label' => str_replace('_', ' ', ucwords($role_convert)),
				              	]);
				              	$role->save();
				              	// New role created and assign to user.
				              	$user->addRole($role->id());
			            	}
			              	
			            }
			            else {
			              	// If role is exist in drupal.
			              	$user->addRole($user_role);
			            }
			        }else{
	                    // Assign role to user for anonymous and authenticate.
	                    $user->addRole('authenticate');
	                }
	                $user->save();

	                // Account extra fields
	                if(count($extra_account_fields) > 0){
			        	$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $value['email']]);
			    		$user = reset($users);
			    		if ($user) {
			    			$uid = $user->id();
				        	foreach ($extra_account_fields as $account_key => $account_value) {
				        		$trimmed = '';
				        		$subject = $account_value;
								$search = 'user__' ;
								$trimmed = str_replace($search, '', $subject);
								$url = $value[$account_value];

				        		if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

									// Get the image name from url
									$link_array = explode('/',$url);
									$image_name = end($link_array);

									$path = 'public://account_images/'; // Directory to file save.
									file_prepare_directory($path, FILE_CREATE_DIRECTORY);

									// Create file entity.
									$file = File::create([
									  'uid' => $uid,
									  'filename' => $image_name,
									  'uri' => $path.$image_name,
									  'status' => 1,
									]);
									file_put_contents($file->getFileUri(), file_get_contents($url));
									$file->save();

					        		$user->set($trimmed, $file);
								} else {
									$account_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$account_value]);
					        		$user->set($trimmed, $account_value_convert);
								}
				        	}
				        }
				        $user->save();
			        }

	                // Send template email.
	                if(!empty($message_subject) && !empty($message)){
	                	\Drupal\user_import\Form\Multistep\MultistepTwoForm::send_template_email($value,$message_subject,$message,$email_format);
	                }

	            	// Add members in a group
	            	$moduleHandler = \Drupal::service('module_handler');
					if ($moduleHandler->moduleExists('group')){

						if(!empty($values['add_group'])){
							$group_explode = explode('-', $values['add_group']);
			    			$group_id = $group_explode[1];
							$group_roles = $values['add_role'];

							if(!empty($group_id) && !empty($group_roles)){
								if( ($group_id != 0) && ($group_roles != '')){
									// Insert the record to table.
									$roles = array($group_roles);
									
									\Drupal\user_import\Form\Multistep\MultistepTwoForm::add_member($roles,$group_id,$value['email']);
								}
							} elseif(!empty($group_id)) {
								\Drupal\user_import\Form\Multistep\MultistepTwoForm::add_member_without_role($group_id,$value['email']);
							}
						}
					}


					// Add profile data
					if ($moduleHandler->moduleExists('profile')){
						$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $value['email']]);
			    		$user = reset($users);
			    		if ($user) {
			    			$uid = $user->id();

			    			if(count($profile_csv_user_column) > 0){
			    				foreach ($profile_csv_user_column as $profile_key => $profile_value) {

									$field_name = $profile_csv_user_column[$profile_key]['profile_csv_column'];
									$profile_type = $profile_csv_user_column[$profile_key]['profile_field'];
									$url = $value[$profile_csv_user_column[$profile_key]['profile_csv_column']];

									$database = \Drupal::database();
									$profile_type_query = $database->query("SELECT name FROM config WHERE name LIKE '%field.field.profile.".$profile_type.".".$field_name."%'");
									$profile_type_result = $profile_type_query->fetchAll();

									if(count($profile_type_result) > 0){
										$lists = [];
										$lists = \Drupal::entityTypeManager()
												  ->getStorage('profile')
												  ->loadByProperties([
												    'uid' => $uid,
												    'type' => $profile_type,
												  ]);

										if(count($lists) > 0){
										    foreach ($lists as $lkey => $list) {
												$profile_id = $list->id();

												if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

													// Get the image name from url
													$link_array = explode('/',$url);
													$image_name = end($link_array);

													$path = 'public://profile_images/'; // Directory to file save.
													file_prepare_directory($path, FILE_CREATE_DIRECTORY);

													// Create file entity.
													$file = File::create([
													  'uid' => $uid,
													  'filename' => $image_name,
													  'uri' => $path.$image_name,
													  'status' => 1,
													]);
													file_put_contents($file->getFileUri(), file_get_contents($url));
													$file->save();

													$profile = Profile::load($profile_id);
													$profile->setDefault(TRUE);
													$profile->set($field_name,$file);
													$profile->save();
												}else{
													$field_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);

													$profile = Profile::load($profile_id);
													$profile->setDefault(TRUE);
													$profile->set($field_name,$field_value_convert);
													$profile->save();
												}
											}	  
										}else{
											if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

												// Get the image name from url
												$link_array = explode('/',$url);
												$image_name = end($link_array);

												$path = 'public://profile_images/'; // Directory to file save.
												file_prepare_directory($path, FILE_CREATE_DIRECTORY);

												// Create file entity.
												$file = File::create([
												  'uid' => $uid,
												  'filename' => $image_name,
												  'uri' => $path.$image_name,
												  'status' => 1,
												]);
												file_put_contents($file->getFileUri(), file_get_contents($url));
												$file->save();

												$profile = Profile::create([
												    'type' => $profile_csv_user_column[$profile_key]['profile_field'],
												    'uid' => $uid,
												    $profile_csv_user_column[$profile_key]['profile_csv_column'] => $file,
												]);
												$profile->setDefault(TRUE);
												$profile->save();
											}else{
												$field_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);

												$profile = Profile::create([
												    'type' => $profile_csv_user_column[$profile_key]['profile_field'],
												    'uid' => $uid,
												    $profile_csv_user_column[$profile_key]['profile_csv_column'] => $field_value_convert,
												]);
												$profile->setDefault(TRUE);
												$profile->save();
											}
										}
									}
								}
			    			}
			    		}
		    		}
		    	}else{
		    		// Update user if already exist.
		    		$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $value['email']]);
		    		$user = reset($users);
		    		if ($user) {
		    			$uid = $user->id();
		    			$name = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['name']);
			  			$user->setUsername($name);

			  			$email = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['email']);
		    			$user->setEmail($email);

				        if( ($value['status'] == '1') || ($value['status'] == '0') ){
				        	$user->set("status", $value['status']);
				        }else{
				        	drupal_set_message(t('Please input correct status value. It should be 0 or 1'),'error');
				        }

				        if(!empty($value['langcode'])){
				        	$langcode = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['langcode']);

				        	$user->set("langcode", $langcode);
					        $user->set("preferred_langcode", $langcode);
					        $user->set("preferred_admin_langcode", $langcode);
				        }
				        
				        if(!empty($value['timezone'])){
					        //- Set Time Zone to London
					        $timezone = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['timezone']); 
							$user->set('timezone',$timezone);
						}

					    if (((int) $override_role == 1)) {

					    	$role_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value['role']);
			        		// List out all roles.
				            $list_of_roles = user_role_names();
				            $user_role = str_replace(' ', '_', $role_convert);
				            if (!in_array($user_role, $list_of_roles) && (array_key_exists($user_role, $list_of_roles) === FALSE)) {

				            	if (strpos($role_convert, ',') !== false) {
				            		$comma_separated_role = explode(',',$role_convert);
				            		foreach ($comma_separated_role as $urole) {
				            			if(array_key_exists($urole, $list_of_roles)){
				            				$user->addRole($urole);
				            			}else{
				            				// Create Role.
							              	$role = Role::create([
							                	'id' => str_replace(' ', '_', strtolower($urole)),
							                	'label' => str_replace('_', ' ', ucwords($urole)),
							              	]);
							              	$role->save();
							              	// New role created and assign to user.
							              	$user->addRole($role->id());
				            			}
				            		}
				            	}else{
				            		// Create Role.
					              	$role = Role::create([
					                	'id' => str_replace(' ', '_', strtolower($user_role)),
					                	'label' => str_replace('_', ' ', ucwords($role_convert)),
					              	]);
					              	$role->save();
					              	// New role created and assign to user.
					              	$user->addRole($role->id());
				            	}
				              	
				            }
				            else {
				              	// If role is exist in drupal.
				              	$user->addRole($user_role);
				            }
				        }else{
		                    // Assign role to user for anonymous and authenticate.
		                    $user->addRole('authenticate');
		                }

		                // Update account fields
		                if(count($extra_account_fields) > 0){
			                foreach ($extra_account_fields as $account_key => $account_value) {
				        		$trimmed = '';
				        		$subject = $account_value;
								$search = 'user__' ;
								$trimmed = str_replace($search, '', $subject);
								$url = $value[$account_value];

				        		if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

									// Get the image name from url
									$link_array = explode('/',$url);
									$image_name = end($link_array);

									$path = 'public://account_images/'; // Directory to file save.
									file_prepare_directory($path, FILE_CREATE_DIRECTORY);

									// Create file entity.
									$file = File::create([
									  'uid' => $uid,
									  'filename' => $image_name,
									  'uri' => $path.$image_name,
									  'status' => 1,
									]);
									file_put_contents($file->getFileUri(), file_get_contents($url));
									$file->save();

					        		$user->set($trimmed, $file);
								} else {
									$account_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$account_value]);
					        		$user->set($trimmed, $account_value_convert);
								}
				        	}
			        	}
		                $user->save();

		                // Send template email.
		                if(!empty($second_message_subject) && !empty($second_message)){
		                	\Drupal\user_import\Form\Multistep\MultistepTwoForm::send_template_email($value,$second_message_subject,$second_message,$second_email_format);
		                }

		                $moduleHandler = \Drupal::service('module_handler');
						if ($moduleHandler->moduleExists('group')){

							if(!empty($values['add_group'])){
								$group_explode = explode('-', $values['add_group']);
			    				$group_id = $group_explode[1];
								$group_roles = $values['add_role'];

								if(!empty($group_id) && !empty($group_roles)){
									if( ($group_id != 0) && ($group_roles != '')){
										// Insert the record to table.
										$roles = array($group_roles);
										
										\Drupal\user_import\Form\Multistep\MultistepTwoForm::add_member($roles,$group_id,$value['email']);
									}
								} elseif(!empty($group_id)) {
									\Drupal\user_import\Form\Multistep\MultistepTwoForm::add_member_without_role($group_id,$value['email']);
								}
							}
						}
						
						// Update profile fields
						if ($moduleHandler->moduleExists('profile')){

							if(count($profile_csv_user_column) > 0){
			    				foreach ($profile_csv_user_column as $profile_key => $profile_value) {

									$field_name = $profile_csv_user_column[$profile_key]['profile_csv_column'];
									$profile_type = $profile_csv_user_column[$profile_key]['profile_field'];
									$url = $value[$profile_csv_user_column[$profile_key]['profile_csv_column']];


									$database = \Drupal::database();
									$profile_type_query = $database->query("SELECT name FROM config WHERE name LIKE '%field.field.profile.".$profile_type.".".$field_name."%'");
									$profile_type_result = $profile_type_query->fetchAll();

									if(count($profile_type_result) > 0){
										$lists = [];
										$lists = \Drupal::entityTypeManager()
												  ->getStorage('profile')
												  ->loadByProperties([
												    'uid' => $uid,
												    'type' => $profile_type,
												  ]);

										if(count($lists) > 0){
											foreach ($lists as $lkey => $list) {
												$profile_id = $list->id();

												if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

													// Get the image name from url
													$link_array = explode('/',$url);
													$image_name = end($link_array);

													$path = 'public://profile_images/'; // Directory to file save.
													file_prepare_directory($path, FILE_CREATE_DIRECTORY);

													// Create file entity.
													$file = File::create([
													  'uid' => $uid,
													  'filename' => $image_name,
													  'uri' => $path.$image_name,
													  'status' => 1,
													]);
													file_put_contents($file->getFileUri(), file_get_contents($url));
													$file->save();

													$profile = Profile::load($profile_id);
													$profile->setDefault(TRUE);
													$profile->set($field_name,$file);
													$profile->save();
												}else{
													$field_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);

													$profile = Profile::load($profile_id);
													$profile->setDefault(TRUE);
													$profile->set($field_name,$field_value_convert);
													$profile->save();
												}
											}	  
										}else{
											if(preg_match( '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$url)){

												// Get the image name from url
												$link_array = explode('/',$url);
												$image_name = end($link_array);

												$path = 'public://profile_images/'; // Directory to file save.
												file_prepare_directory($path, FILE_CREATE_DIRECTORY);

												// Create file entity.
												$file = File::create([
												  'uid' => $uid,
												  'filename' => $image_name,
												  'uri' => $path.$image_name,
												  'status' => 1,
												]);
												file_put_contents($file->getFileUri(), file_get_contents($url));
												$file->save();

												$profile = Profile::create([
												    'type' => $profile_csv_user_column[$profile_key]['profile_field'],
												    'uid' => $uid,
												    $profile_csv_user_column[$profile_key]['profile_csv_column'] => $file,
												]);
												$profile->setDefault(TRUE);
												$profile->save();
											}else{
												$field_value_convert = \Drupal\user_import\Form\Multistep\MultistepTwoForm::convert_unicode($value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);

												$profile = Profile::create([
												    'type' => $profile_csv_user_column[$profile_key]['profile_field'],
												    'uid' => $uid,
												    $profile_csv_user_column[$profile_key]['profile_csv_column'] => $field_value_convert,
												]);
												$profile->setDefault(TRUE);
												$profile->save();
											}
										}
									}
								}
			    			}
						}
		    		}
		    	}
		    } else {
		    	drupal_set_message(t('Please input correct email address "'.$value['email'].'"'),'error');
		    }
	    }
	}

	public static function callBackFinished($success, $results, $operations) {
	  	global $base_url;

	  	if ($success) {
		    // Here we do something meaningful with the results.
		    $message = t("CSV imported successfully with @count users.", array(
		      '@count' => count($results),
		    ));
		    drupal_set_message($message);
	  	}

	  	$current_batch = &batch_get();
	  	// Clean up the batch table and unset the static $batch variable.
		if ($current_batch['progressive']) {
		    \Drupal::service('batch.storage')->delete($current_batch['id']);
		    foreach ($current_batch['sets'] as $batch_set) {
		      	if ($queue = _batch_queue($batch_set)) {
		        	$queue->deleteQueue();
		      	}
		    }

		    // Clean-up the session. Not needed for CLI updates.
		    if (isset($_SESSION)) {
		      	unset($_SESSION['batches'][$current_batch['id']]);
		      	if (empty($_SESSION['batches'])) {
		        	unset($_SESSION['batches']);
		      	}
		    }
		}

	    
	  	// Load the current user.
        $logged_in_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

        $uid = $logged_in_user->get('uid')->value;
        $get_logged_in_roles = $logged_in_user->getRoles();
        $url = '';
        if(in_array('administrator', $get_logged_in_roles)){
            $url = $base_url . "/admin/people";
        }else{
            $url = $base_url;
        }
	    
	    header('Location:' . $url);
	    exit;
	}

}