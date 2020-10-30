<?php

namespace Drupal\user_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Defines a route controller for watches autocomplete form elements.
 */
class AutoCompleteController extends ControllerBase {

  /**
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    // Get the typed string from the URL, if it exists.
    if (!$input) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    $database = \Drupal::database();
    $query = $database->query("SELECT * FROM groups_field_data where label like '%".$input."%'");
    $group_result = $query->fetchAll();

    foreach ($group_result as $key => $group) {
      $current_user   = \Drupal::currentUser();
      $group_load          = \Drupal\group\Entity\Group::load($group->id);
        if ($group_load->getMember($current_user)) {

          $results[] = [
            'value' => $group->label.'-'.$group->id,
            'label' => $group->label,
          ];
        }
    }

    return new JsonResponse($results);
  }
}