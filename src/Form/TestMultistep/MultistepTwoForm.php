<?php
/**
 * @file
 * Contains \Drupal\user_import\Form\TestMultistep\MultistepTwoForm.
 */

namespace Drupal\user_import\Form\TestMultistep;

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

class MultistepTwoForm extends MultistepFormBase
{
    /**
     * {@inheritdoc}.
     */
    public function getFormId()
    {
        return 'multistep_form_two';
    }

    /**
     * {@inheritdoc}.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $file_data = $this
            ->store
            ->get('file_data');
        $list_of_roles = user_role_names();
        $csv_column_array = [];

        foreach ($file_data[0] as $key => $user_value) {
            $csv_column_array[] = $user_value;
        }

        $csv_column_count = count($csv_column_array);
        $form = parent::buildForm($form, $form_state);

        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists('group')) {
            $database = \Drupal::database();
            $query = $database->query("SELECT * FROM groups_field_data");
            $group_result = $query->fetchAll();
            if (count($group_result) > 0) {
                $form['group'] = array(
                    '#type' => 'details',
                    '#title' => $this->t('GROUPS') ,
                    '#description' => $this->t("Select any group to add users.") ,
                    '#open' => true
                );

                $group_listing = [];

                foreach ($group_result as $key => $group) {
                    $group_listing[0] = t('------------------');
                    $current_user = \Drupal::currentUser();
                    $group_load = \Drupal\group\Entity\Group::load($group->id);
                    if ($group_load->getMember($current_user)) {
                        $group_listing[$group->id] = $group->label;
                    }
                }

                $form['group']['add_group'] = array(
                    '#type' => 'select',
                    '#title' => t('Select group') ,
                    '#default_value' => 0,
                    '#options' => $group_listing,
                    '#ajax' => ['event' => 'change',
                    'callback' => '::userImportcallback',
                    'wrapper' => 'user_import_fields_change_wrapper',
                    'progress' => ['type' => 'throbber',
                    'message' => $this->t('Verifying permission...') ,
                    ],
                    ],
                );
                $form['group']['add_role'] = array(
                    '#type' => 'select',
                    '#title' => $this->t('Select role/roles') ,
                    '#prefix' => '<div id="first">',
                    '#suffix' => '</div>',
                    '#default_value' => 0,
                    '#validated' => true,
                    '#options' => array(
                        0 => '------------------'
                    ) ,
                );
                $form['group']['import_ct_markup'] = ['#suffix' => '<div id="user_import_fields_change_wrapper"></div>', ];
            }
        }

        if ($moduleHandler->moduleExists('profile')) {
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

            $form['profile_type_field'] = array(
                '#type' => 'details',
                '#title' => $this->t('PROFILE TYPES') ,
                '#description' => $this->t('When you mapping profile fields, it must be within the profile type.') ,
                '#open' => true
            );
            // Add the headers.
            $form['profile_type_field']['profile_csv_user_column'] = array(
                '#type' => 'table',
                '#title' => 'Sample Table',
                '#header' => array(
                    'CSV COLUMN',
                    'PROFILE TYPE'
                ) ,
            );

            foreach ($file_data[0] as $key => $user_value) {
                if (strpos($user_value, 'field_') !== false) {
                    $form['profile_type_field']['profile_csv_user_column'][$key]['profile_csv_column'] = array(
                        '#type' => 'textfield',
                        '#title' => t('CSV COLUMN'),
                        '#title_display' => 'invisible',
                        '#default_value' => $user_value,
                        '#size' => 20,
                        '#attributes' => array(
                            'disabled' => 'disabled',
                            'class' => array(
                                'csv_column_text'
                            )
                        ) ,
                    );
                    $form['profile_type_field']['profile_csv_user_column'][$key]['profile_field'] = array(
                        '#type' => 'select',
                        '#title' => t('PROFILE TYPE') ,
                        '#title_display' => 'invisible',
                        '#default_value' => 0,
                        '#options' => $profile_type
                    );
                }
            }
        }
        $form['field_match'] = array(
            '#type' => 'details',
            '#title' => $this->t('FIELD MATCH') ,
            '#description' => $this->t("<ul><li><b>Drupal fields</b>: Match columns in CSV file to drupal user fields, leave as '----' to ignore the column.</li><li><b>Username</b>: If username is selected for multiple fields, the username will be built in the order selected. Otherwise, the name will be username.</li><li><b>Abbreviate</b>: Use the first letter of a field in uppercase for the Username, e.g. 'john' -> 'J'.</li><ul>") ,
            '#open' => true
        );
        $form['field_match']['csv_column_count'] = array(
            '#type' => 'hidden',
            '#title' => 'Sample Column',
            '#title_display' => 'invisible',
            '#default_value' => $csv_column_count,
        );
        $form['field_match']['csv_file_data'] = array(
            '#type' => 'hidden',
            '#title' => 'Sample Column',
            '#title_display' => 'invisible',
            '#value' => array(
                'csv_file_data' => $file_data
            ) ,
        );
        // Add the headers.
        $form['field_match']['csv_user_column'] = array(
            '#type' => 'table',
            '#title' => 'Sample Table',
            '#header' => array(
                'CSV COLUMN',
                'DRUPAL FIELDS',
                'USERNAME',
                'ABBREVIATE'
            ) ,
        );

        // Add input fields in table cells.
        foreach ($file_data[0] as $key => $user_value) {
            if (strpos($user_value, 'field_') !== false) {
            } else {
                $form['field_match']['csv_user_column'][$key]['csv_column'] = array(
                    '#type' => 'textfield',
                    '#title' => t('CSV COLUMN') ,
                    '#title_display' => 'invisible',
                    '#default_value' => $user_value,
                    '#size' => 20,
                    '#attributes' => array(
                        'disabled' => 'disabled',
                        'class' => array(
                            'csv_column_text'
                        )
                    ) ,
                );
                $form['field_match']['csv_user_column'][$key]['drupal_fields'] = array(
                    '#type' => 'select',
                    '#title' => t('DRUPAL FIELDS') ,
                    '#title_display' => 'invisible',
                    '#default_value' => 0,
                    '#options' => array(
                        0 => t('-------------------') ,
                        1 => t('Account Creation Date') ,
                        2 => t('Email Address*') ,
                        3 => t('Password') ,
                        4 => t('Roles')
                    )
                );
                $form['field_match']['csv_user_column'][$key]['username'] = array(
                    '#type' => 'select',
                    '#title' => t('USERNAME') ,
                    '#title_display' => 'invisible',
                    '#default_value' => 0,
                    '#options' => array(
                        0 => t('--') ,
                        1 => t('1') ,
                        2 => t('2') ,
                        3 => t('3') ,
                        4 => t('4')
                    )
                );
                $form['field_match']['csv_user_column'][$key]['abbreviate'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0
                );
            }
        }
        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('OPTIONS') ,
            '#open' => true
        );
        $form['options']['send_email'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Send Email') ,
            '#description' => $this->t("Send email to users when their account is created.") ,
            '#default_value' => $this
                ->store
                ->get('send_email') ? $this
                ->store
                ->get('send_email') : ''
        );
        $form['options']['username_space'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Username Space') ,
            '#description' => $this->t("Include spaces in usernames, e.g. 'John' + 'Smith' => 'John Smith'.") ,
            '#default_value' => $this
                ->store
                ->get('username_space') ? $this
                ->store
                ->get('username_space') : ''
        );
        $form['options']['activate_accounts'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Activate Accounts') ,
            '#description' => $this->t("User accounts will not be visible to other users until their owner logs in. Select this option to make all imported user accounts visible. Note - one time login links in welcome emails will no longer work if this option is enabled.") ,
            '#default_value' => $this
                ->store
                ->get('activate_accounts') ? $this
                ->store
                ->get('activate_accounts') : ''
        );
        $form['role_assign'] = array(
            '#type' => 'details',
            '#title' => $this->t('ROLE ASSIGN') ,
            '#open' => true
        );
        $form['role_assign']['override_role'] = ['#type' => 'radios', '#options' => ['1' => 'True', '0' => 'False', ], '#default_value' => '0', '#title' => $this->t('Override Role From CSV') , '#size' => 40, '#description' => $this->t('Override role from CSV and create role if not exist in Durpal.') , ];
        $form['email_message'] = array(
            '#type' => 'details',
            '#title' => $this->t('EMAIL MESSAGE') ,
            '#open' => true
        );
        $form['email_message']['message_subject'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Message Subject') ,
            '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail], [site:login-url], [user:one-time-login-url]") ,
            '#default_value' => $this
                ->store
                ->get('message_subject') ? $this
                ->store
                ->get('message_subject') : ''
        );
        $form['email_message']['message'] = array(
            '#type' => 'textarea',
            '#title' => $this->t('Message') ,
            '#description' => $this->t("Customize the subject of the welcome e-mail, which is sent to imported members.  Available variables are: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail], [site:login-url], [user:one-time-login-url],[user:password]") ,
            '#default_value' => $this
                ->store
                ->get('message') ? $this
                ->store
                ->get('message') : ''
        );
        $form['email_message']['email_format'] = array(
            '#type' => 'radios',
            '#options' => ['1' => 'Plain Text',
            '0' => 'HTML',
            ],
            '#default_value' => '1',
            '#title' => $this->t('Email Format') ,
            '#size' => 40,
        );
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous') ,
            '#attributes' => array(
                'class' => array(
                    'button'
                ) ,
            ) ,
            '#weight' => 0,
            '#url' => Url::fromRoute('user_import.multistep_one') ,
        );
        return $form;
    }

    /**
     * User Import Sample CSV Creation.
     */
    public function userImportcallback(array &$form, FormStateInterface $form_state)
    {
        global $base_url;

        $result = '';
        $ajax_response = new AjaxResponse();
        $group_id = $form_state->getValue('add_group');
        $current_user = \Drupal::currentUser();
        $group = \Drupal\group\Entity\Group::load($group_id);
        $group_type = $group->bundle();

        if ($group->getMember($current_user)) {
            // User is a member...
            $group_roles = \Drupal::entityTypeManager()->getStorage('group_role')
                ->loadByProperties(['group_type' => $group_type, 'internal' => false, ]);
            $role_names = [];
            foreach ($group_roles as $role_id => $group_role) {
                if (!$group_role->isInternal()) {
                    // checks if you created this role.
                    $role_names[$role_id] = $group_role->label();
                }
            }

            if (count($role_names) > 0) {
                $form['group']['add_role']['#options'] = $role_names;
            } else {
                $form['group']['add_role']['#options'] = array(
                    0 => '------------------'
                );
            }
            $form_state->setRebuild(true);
            $ajax_response->addCommand(new HtmlCommand("#user_import_fields_change_wrapper", ''));
            $ajax_response->addCommand(new HtmlCommand("#first", ($form['group']['add_role'])));
            return $ajax_response;
        } else {
            $role = 'You do not have permission to add member in this group.';
            $form['group']['add_role']['#options'] = array(
                0 => '------------------'
            );
            $ajax_response->addCommand(new HtmlCommand("#first", ($form['group']['add_role'])));
            $ajax_response->addCommand(new HtmlCommand('#user_import_fields_change_wrapper', $role));
            return $ajax_response;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        global $base_url;

        $values = $form_state->getValues();
        $message = $values['message'];
        $email_format = $values['email_format'];
        $message_subject = $values['message_subject'];
        $add_role = $values['add_role'];
        $send_email = $values['send_email'];
        $username_space = $values['username_space'];
        $activate_accounts = $values['activate_accounts'];
        $override_role = $values['override_role'];
        $csv_file_data = $values['csv_file_data']['csv_file_data'];
        $csv_column_count = $values['csv_column_count'];
        $csv_user_column = $values['csv_user_column'];
        $drupal_fields_array = [];

        foreach ($csv_user_column as $key => $value) {
            if ($csv_user_column[$key]['drupal_fields'] == 0) {
                $drupal_fields_array[] = $csv_user_column[$key]['drupal_fields'];
            }
        }

        $drupal_fields_count = count($drupal_fields_array);
        if ($drupal_fields_count == $csv_column_count) {
            drupal_set_message($this->t('One column of the csv file must be set as the email address.'), 'error');
            return;
        }

        $fieldNames = [];
        $keyIndex = [];
        $arrange_username = [];
        $concat_username = [];
        $user_data = [];
        $test = [];

        foreach ($csv_user_column as $key => $value) {
            if ($value['username'] == 0) {
                $csv_user_column_username[] = $value['username'];
            }
        }
        foreach ($csv_file_data[0] as $key => $csv_header) {
            $fieldNames[] = $csv_header;
        }
        foreach ($csv_file_data as $key => $user_value) {
            foreach ($user_value as $key => $value) {
                if ($fieldNames[$key] != $value) {
                    $keyIndex[$fieldNames[$key]] = $value;
                }
            }
            $user_index[] = $keyIndex;
        }
        $user_data = array_filter($user_index);

        foreach ($csv_user_column as $key => $u_value) {
            if ($u_value['username'] != 0) {
                $arrange_username[$u_value['csv_column']] = $u_value['username'];
            }
        }
        foreach ($csv_user_column as $key => $u_value) {
            if ($u_value['abbreviate'] == 1) {
                $abbreviate[] = $u_value['csv_column'];
            }
        }

        $array = $arrange_username;
        $counts = array_count_values($array);
        $filtered = array_filter($array, function ($value) use ($counts) {
            return $counts[$value] > 1;
        });
        $username_fields_count = count($csv_user_column_username);
        if ($username_fields_count == $csv_column_count) {
            if (count($abbreviate) > 0) {
                foreach ($user_data as $u_key => $value) {
                    foreach ($abbreviate as $key => $abbr_value) {
                        $user_data[$u_key][$abbr_value] = ucfirst($value[$abbr_value]);
                    }
                }
            } else {
                foreach ($user_data as $u_key => $value) {
                    if ($username_space == 1) {
                        $user_data[$u_key]['username'] .= ucfirst($value['name']);
                    } else {
                        $string = str_replace(' ', '', $value['name']);
                        $user_data[$u_key]['username'] .= ucfirst($string);
                    }
                }
            }
        } else {
            foreach ($user_data as $u_key => $u_value) {
                if (count($filtered) > 0) {
                    foreach ($arrange_username as $key => $value) {
                        if (in_array($key, $abbreviate)) {
                            $user_data[$u_key]['username'] .= ucfirst($u_value[$key]);
                        } else {
                            $user_data[$u_key]['username'] .= $u_value[$key];
                        }
                    }
                } else {
                    if (count($arrange_username) > 0) {
                        $field1 = array_search(1, $arrange_username);
                        $field2 = array_search(2, $arrange_username);
                        $field3 = array_search(3, $arrange_username);
                        $field4 = array_search(4, $arrange_username);

                        if (in_array(1, $arrange_username)) {
                            if (in_array($field1, $abbreviate)) {
                                if ($username_space == 1) {
                                    if ($field1 == 'name') {
                                        $user_data[$u_key]['username'] .= ucfirst($u_value[$field1]);
                                    } else {
                                        $string = str_replace(' ', '', $u_value[$field1]);
                                        $user_data[$u_key]['username'] .= ucfirst($string);
                                    }
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field1]);
                                    $user_data[$u_key]['username'] .= ucfirst($string);
                                }
                            } else {
                                if ($username_space == 1) {
                                    $user_data[$u_key]['username'] .= $u_value[$field1];
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field1]);
                                    $user_data[$u_key]['username'] .= $string;
                                }
                            }
                        }

                        if (in_array(2, $arrange_username)) {
                            if (in_array($field2, $abbreviate)) {
                                if ($username_space == 1) {
                                    if ($field2 == 'name') {
                                        $user_data[$u_key]['username'] .= ucfirst($u_value[$field2]);
                                    } else {
                                        $string = str_replace(' ', '', $u_value[$field2]);
                                        $user_data[$u_key]['username'] .= ucfirst($string);
                                    }
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field2]);
                                    $user_data[$u_key]['username'] .= ucfirst($string);
                                }
                            } else {
                                if ($username_space == 1) {
                                    $user_data[$u_key]['username'] .= $u_value[$field2];
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field2]);
                                    $user_data[$u_key]['username'] .= $string;
                                }
                            }
                        }

                        if (in_array(3, $arrange_username)) {
                            if (in_array($field3, $abbreviate)) {
                                if ($username_space == 1) {
                                    if ($field3 == 'name') {
                                        $user_data[$u_key]['username'] .= ucfirst($u_value[$field3]);
                                    } else {
                                        $string = str_replace(' ', '', $u_value[$field3]);
                                        $user_data[$u_key]['username'] .= ucfirst($string);
                                    }
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field3]);
                                    $user_data[$u_key]['username'] .= ucfirst($string);
                                }
                            } else {
                                if ($username_space == 1) {
                                    $user_data[$u_key]['username'] .= $u_value[$field3];
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field3]);
                                    $user_data[$u_key]['username'] .= $string;
                                }
                            }
                        }

                        if (in_array(4, $arrange_username)) {
                            if (in_array($field4, $abbreviate)) {
                                if ($username_space == 1) {
                                    if ($field4 == 'name') {
                                        $user_data[$u_key]['username'] .= ucfirst($u_value[$field4]);
                                    } else {
                                        $string = str_replace(' ', '', $u_value[$field4]);
                                        $user_data[$u_key]['username'] .= ucfirst($string);
                                    }
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field4]);
                                    $user_data[$u_key]['username'] .= ucfirst($string);
                                }
                            } else {
                                if ($username_space == 1) {
                                    $user_data[$u_key]['username'] .= $u_value[$field4];
                                } else {
                                    $string = str_replace(' ', '', $u_value[$field4]);
                                    $user_data[$u_key]['username'] .= $string;
                                }
                            }
                        }
                    } else {
                        foreach ($user_data as $u_key => $value) {
                            if ($username_space == 1) {
                                $user_data[$u_key]['username'] = ucfirst($value['name']);
                            } else {
                                $string = str_replace(' ', '', $value['name']);
                                $user_data[$u_key]['username'] = ucfirst($string);
                            }
                        }
                    }
                }
            }
        }

        $profile_csv_user_column = $values['profile_csv_user_column'];

        // Get current user language.
        $language = \Drupal::languageManager()->getCurrentLanguage()
            ->getId();
        foreach ($user_data as $value) {
            $user_status = user_load_by_mail($value['email']);
            if (empty($user_status)) {
                // Create new user
                $user = User::create();
                $user->uid = '';
                $user->setUsername($value['username']);
                $user->setEmail($value['email']);
                $user->set("init", $value['email']);
                $user->set("langcode", $language);
                $user->set("preferred_langcode", $language);
                $user->set("preferred_admin_langcode", $language);
                $user->set("status", $value['status']);
                $user->setPassword($value['pass']);
                $user->enforceIsNew();

                if ($activate_accounts == 1) {
                    $user->activate();
                }

                if (((int)$override_role == 1)) {
                    // List out all roles.
                    $list_of_roles = user_role_names();
                    $user_role = str_replace(' ', '_', $value['role']);
                    if (!in_array($user_role, $list_of_roles) && (array_key_exists($user_role, $list_of_roles) === false)) {
                        if (strpos($value['role'], ',') !== false) {
                            $comma_separated_role = explode(',', $value['role']);
                            foreach ($comma_separated_role as $urole) {
                                if (array_key_exists($urole, $list_of_roles)) {
                                    $user->addRole($urole);
                                } else {
                                    // Create Role.
                                    $role = Role::create(['id' => str_replace(' ', '_', strtolower($urole)) , 'label' => str_replace('_', ' ', ucwords($urole)) , ]);
                                    $role->save();
                                    // New role created and assign to user.
                                    $user->addRole($role->id());
                                }
                            }
                        } else {
                            // Create Role.
                            $role = Role::create(['id' => str_replace(' ', '_', strtolower($user_role)) , 'label' => str_replace('_', ' ', ucwords($value['role'])) , ]);
                            $role->save();
                            // New role created and assign to user.
                            $user->addRole($role->id());
                        }
                    } else {
                        // If role is exist in drupal.
                        $user->addRole($user_role);
                    }
                } else {
                    // Assign role to user for anonymous and authenticate.
                    $user->addRole('authenticate');
                }
                $user->save();

                // Notify to user via mail.
                if (($send_email == 1) || ($activate_accounts == 1)) {
                    _user_mail_notify('register_no_approval_required', $user);
                }

                // Send template email.
                if (!empty($message_subject) && !empty($message)) {
                    $this->sendTemplateEmail($value, $message_subject, $message, $email_format);
                }

                // Add members in a group
                $moduleHandler = \Drupal::service('module_handler');
                if ($moduleHandler->moduleExists('group')) {
                    $group_id = $values['add_group'];
                    $group_roles = $values['add_role'];
                    if (!empty($group_id) && !empty($group_roles)) {
                        if (($group_id != 0) && ($group_roles != '')) {
                            // Insert the record to table.
                            $roles = array(
                                $group_roles
                            );
                            $this->adMember($roles, $group_id, $value['email']);
                        }
                    }
                }

                // Add profile data
                if ($moduleHandler->moduleExists('profile')) {
                    $users = \Drupal::entityTypeManager()->getStorage('user')
                        ->loadByProperties(['mail' => $value['email']]);
                    $user = reset($users);
                    if ($user) {
                        $uid = $user->id();

                        if (count($profile_csv_user_column) > 0) {
                            foreach ($profile_csv_user_column as $profile_key => $profile_value) {
                                $field_name = $profile_csv_user_column[$profile_key]['profile_csv_column'];
                                $profile_type = $profile_csv_user_column[$profile_key]['profile_field'];
                                $url = $value[$profile_csv_user_column[$profile_key]['profile_csv_column']];
                                $list = \Drupal::entityTypeManager()->getStorage('profile')
                                    ->loadByProperties(['uid' => $uid, 'type' => $profile_type, ]);

                                if (count($list) > 0) {
                                    $activeProfile = [];
                                    $activeProfile = \Drupal::getContainer()->get('entity_type.manager')
                                        ->getStorage('profile')
                                        ->loadByUser(User::load($uid), $profile_type);
                                    if (count($activeProfile) > 0) {
                                        $profile_id = $activeProfile->id();

                                        if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                            // Get the image name from url
                                            $link_array = explode('/', $url);
                                            $image_name = end($link_array);

                                            $path = 'public://profile_images/'; // Directory to file save.
                                            file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                            // Create file entity.
                                            $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                            file_put_contents($file->getFileUri(), file_get_contents($url));
                                            $file->save();

                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $file);
                                            $profile->save();
                                        } else {
                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);
                                            $profile->save();
                                        }
                                    } else {
                                        if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                            // Get the image name from url
                                            $link_array = explode('/', $url);
                                            $image_name = end($link_array);

                                            $path = 'public://profile_images/'; // Directory to file save.
                                            file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                            // Create file entity.
                                            $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                            file_put_contents($file->getFileUri(), file_get_contents($url));
                                            $file->save();

                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $file);
                                            $profile->save();
                                        } else {
                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);
                                            $profile->save();
                                        }
                                    }
                                } else {
                                    if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                        // Get the image name from url
                                        $link_array = explode('/', $url);
                                        $image_name = end($link_array);

                                        $path = 'public://profile_images/'; // Directory to file save.
                                        file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                        // Create file entity.
                                        $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                        file_put_contents($file->getFileUri(), file_get_contents($url));
                                        $file->save();

                                        $profile = Profile::create(['type' => $profile_csv_user_column[$profile_key]['profile_field'], 'uid' => $uid, $profile_csv_user_column[$profile_key]['profile_csv_column'] => $file, ]);
                                        $profile->setDefault(true);
                                        $profile->save();
                                    } else {
                                        $profile = Profile::create(['type' => $profile_csv_user_column[$profile_key]['profile_field'], 'uid' => $uid, $profile_csv_user_column[$profile_key]['profile_csv_column'] => $value[$profile_csv_user_column[$profile_key]['profile_csv_column']], ]);
                                        $profile->setDefault(true);
                                        $profile->save();
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Update user if already exist.
                $users = \Drupal::entityTypeManager()->getStorage('user')
                    ->loadByProperties(['mail' => $value['email']]);
                $user = reset($users);
                if ($user) {
                    $uid = $user->id();
                    $user->setUsername($value['username']);
                    $user->setEmail($value['email']);
                    $user->set("langcode", $language);
                    $user->set("preferred_langcode", $language);
                    $user->set("preferred_admin_langcode", $language);
                    $user->set("status", $value['status']);
                    $user->setPassword($value['pass']);

                    if (((int)$override_role == 1)) {
                        // List out all roles.
                        $list_of_roles = user_role_names();
                        $user_role = str_replace(' ', '_', $value['role']);
                        if (!in_array($user_role, $list_of_roles) && (array_key_exists($user_role, $list_of_roles) === false)) {
                            if (strpos($value['role'], ',') !== false) {
                                $comma_separated_role = explode(',', $value['role']);
                                foreach ($comma_separated_role as $urole) {
                                    if (array_key_exists($urole, $list_of_roles)) {
                                        $user->addRole($urole);
                                    } else {
                                        // Create Role.
                                        $role = Role::create(['id' => str_replace(' ', '_', strtolower($urole)) , 'label' => str_replace('_', ' ', ucwords($urole)) , ]);
                                        $role->save();
                                        // New role created and assign to user.
                                        $user->addRole($role->id());
                                    }
                                }
                            } else {
                                // Create Role.
                                $role = Role::create(['id' => str_replace(' ', '_', strtolower($user_role)) , 'label' => str_replace('_', ' ', ucwords($value['role'])) , ]);
                                $role->save();
                                // New role created and assign to user.
                                $user->addRole($role->id());
                            }
                        } else {
                            // If role is exist in drupal.
                            $user->addRole($user_role);
                        }
                    } else {
                        // Assign role to user for anonymous and authenticate.
                        $user->addRole('authenticate');
                    }
                    $user->save();

                    $moduleHandler = \Drupal::service('module_handler');
                    if ($moduleHandler->moduleExists('group')) {
                        $group_id = $values['add_group'];
                        $group_roles = $values['add_role'];

                        if (!empty($group_id) && !empty($group_roles)) {
                            if (($group_id != 0) && ($group_roles != '')) {
                                // Insert the record to table.
                                $roles = array(
                                    $group_roles
                                );

                                $this->adMember($roles, $group_id, $value['email']);
                            }
                        }
                    }

                    // Update profile fields
                    if ($moduleHandler->moduleExists('profile')) {
                        if (count($profile_csv_user_column) > 0) {
                            foreach ($profile_csv_user_column as $profile_key => $profile_value) {
                                $field_name = $profile_csv_user_column[$profile_key]['profile_csv_column'];
                                $profile_type = $profile_csv_user_column[$profile_key]['profile_field'];
                                $url = $value[$profile_csv_user_column[$profile_key]['profile_csv_column']];

                                $list = \Drupal::entityTypeManager()->getStorage('profile')
                                    ->loadByProperties(['uid' => $uid, 'type' => $profile_type, ]);

                                if (count($list) > 0) {
                                    $activeProfile = [];
                                    $activeProfile = \Drupal::getContainer()->get('entity_type.manager')
                                        ->getStorage('profile')
                                        ->loadByUser(User::load($uid), $profile_type);

                                    if (count($activeProfile) > 0) {
                                        $profile_id = $activeProfile->id();
                                        if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                            // Get the image name from url
                                            $link_array = explode('/', $url);
                                            $image_name = end($link_array);

                                            $path = 'public://profile_images/'; // Directory to file save.
                                            file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                            // Create file entity.
                                            $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                            file_put_contents($file->getFileUri(), file_get_contents($url));
                                            $file->save();

                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $file);
                                            $profile->save();
                                        } else {
                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);
                                            $profile->save();
                                        }
                                    } else {
                                        if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                            // Get the image name from url
                                            $link_array = explode('/', $url);
                                            $image_name = end($link_array);

                                            $path = 'public://profile_images/'; // Directory to file save.
                                            file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                            // Create file entity.
                                            $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                            file_put_contents($file->getFileUri(), file_get_contents($url));
                                            $file->save();

                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $file);
                                            $profile->save();
                                        } else {
                                            $profile = Profile::load($profile_id);
                                            $profile->setDefault(true);
                                            $profile->set($field_name, $value[$profile_csv_user_column[$profile_key]['profile_csv_column']]);
                                            $profile->save();
                                        }
                                    }
                                } else {
                                    if (preg_match('/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
                                        // Get the image name from url
                                        $link_array = explode('/', $url);
                                        $image_name = end($link_array);

                                        $path = 'public://profile_images/'; // Directory to file save.
                                        file_prepare_directory($path, FILE_CREATE_DIRECTORY);

                                        // Create file entity.
                                        $file = File::create(['uid' => $uid, 'filename' => $image_name, 'uri' => $path . $image_name, 'status' => 1, ]);
                                        file_put_contents($file->getFileUri(), file_get_contents($url));
                                        $file->save();

                                        $profile = Profile::create(['type' => $profile_csv_user_column[$profile_key]['profile_field'], 'uid' => $uid, $profile_csv_user_column[$profile_key]['profile_csv_column'] => $file, ]);
                                        $profile->setDefault(true);
                                        $profile->save();
                                    } else {
                                        $profile = Profile::create(['type' => $profile_csv_user_column[$profile_key]['profile_field'], 'uid' => $uid, $profile_csv_user_column[$profile_key]['profile_csv_column'] => $value[$profile_csv_user_column[$profile_key]['profile_csv_column']], ]);
                                        $profile->setDefault(true);
                                        $profile->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        drupal_set_message($this->t('CSV users imported successfully.'), 'status');

        // Load the current user.
        $logged_in_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

        $uid = $logged_in_user->get('uid')->value;
        $get_logged_in_roles = $logged_in_user->getRoles();
        $url = '';
        if (in_array('administrator', $get_logged_in_roles)) {
            $url = $base_url . "/admin/people";
        } else {
            $url = $base_url;
        }

        header('Location:' . $url);
        exit;
    }

    public function sendTemplateEmail($value, $message_subject, $message, $email_format)
    {
        global $base_url;

        $site_name = \Drupal::config('system.site')->get('name');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'user_import';
        $key = 'create_user'; // Replace with Your key
        $to = $value['email'];

        $user_display_name = $value['name'];
        $user_name = $value['username'];

        if (strpos($message_subject, '[site:name]') !== false) {
            $new_message_subject = str_replace("[site:name]", $site_name, $message_subject);
        }

        if (strpos($message, '[user:display-name]') !== false) {
            $message = str_replace("[user:display-name]", $user_display_name, $message);
        }

        if (strpos($message, '[user:name]') !== false) {
            $message = str_replace("[user:name]", $user_name, $message);
        }

        if (strpos($message, '[site:name]') !== false) {
            $message = str_replace("[site:name]", $site_name, $message);
        }

        $new_user = \Drupal::entityTypeManager()->getStorage('user')
            ->loadByProperties(['mail' => $value['email']]);
        $new_user = reset($new_user);
        $uid = $new_user->id();

        // Create a timestamp.
        $timestamp = \Drupal::time()->getRequestTime();
        // Set the redirect location after the user of the one time login.
        $path = '';

        // Create login link from route (Copy pasted this from the drush package)
        $one_time_login_url = Url::fromRoute('user.reset.login', ['uid' => $uid, 'timestamp' => $timestamp, 'hash' => user_pass_rehash($new_user, $timestamp) , ], ['absolute' => true, 'query' => $path ? ['destination' => $path] : [], 'language' => \Drupal::languageManager()->getLanguage($new_user->getPreferredLangcode()) , ])
            ->toString();

        if (strpos($message, '[user:one-time-login-url]') !== false) {
            $message = str_replace("[user:one-time-login-url]", $one_time_login_url, $message);
        }

        $site_login_url = $base_url . '/user';

        if (strpos($message, '[site:login-url]') !== false) {
            $message = str_replace("[site:login-url]", $site_login_url, $message);
        }

        $user_password = $value['pass'];

        if (strpos($message, '[user:password]') !== false) {
            $message = str_replace("[user:password]", $user_password, $message);
        }

        $params['message_subject'] = $new_message_subject;
        $params['message'] = $message;
        $params['email_format'] = $email_format;
        $params['title'] = 'Drupal 8';
        $params['[site:name]'] = $site_name;

        $langcode = \Drupal::languageManager()->getCurrentLanguage()
            ->getId();
        $send = true;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, null, $send);
        if ($result['result'] != true) {
            $err_message = t('There was a problem sending your email notification to @email.', array(
                '@email' => $to
            ));
            \Drupal::logger('mail-log')->error($err_message);
        }

        $success_message = t('An email notification has been sent to @email ', array(
            '@email' => $to
        ));
        \Drupal::logger('mail-log')->notice($success_message);

        return true;
    }

    public function adMember($roles, $group_id, $user_email)
    {
        // Add Member
        $values = ['gid' => $group_id];
        $group = \Drupal\group\Entity\Group::load($group_id);
        $group_user = \Drupal::entityTypeManager()->getStorage('user')
            ->loadByProperties(['mail' => $user_email]);
        $group_user_account = reset($group_user);
        $group->addMember($group_user_account, $values);

        // Add roles to user
        $member = $group->getMember($group_user_account);
        $membership = $member->getGroupContent();

        // Get the roles of the owner
        $member_roles = $member->getRoles();

        foreach ($roles as $value) {
            if (!$member_roles[$value]) {
                $membership->group_roles[] = $value;
                $membership->save();
            }
        }
    }
}
