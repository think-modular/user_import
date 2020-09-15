<?php

namespace Drupal\user_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Implements Class UserImportController Controller.
 */
class UserImportController extends ControllerBase {

  	/**
   	* Get All available roles.
   	*/
  	public static function getAllUserRoleTypes() {
    	return user_role_names();
  	}

}
