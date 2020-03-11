<?php

/**
 * @file
 * Contains \Drupal\cfp_user_register\Controller\MultistepUserRegisterAccess.
 */

namespace Drupal\cfp_user_register\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;

class MultistepUserRegisterAccess extends ControllerBase {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * The steps access settings, populate from store.
   *
   * @var array
   */
  private $stepsAccessSettings;

  /**
   * The current step id.
   *
   * @var int
   */
  private $currentStepId;

  /**
   * The current route.
   *
   * @var string|null
   */
  private $currentRouteName;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('current_route_match')
    );
  }

  /**
   * MultistepUserRegisterAccess constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    RouteMatchInterface $route_match
  ) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentRouteName = $route_match->getRouteName();
    $this->store = $this->tempStoreFactory->get('multistep_data');
    $this->currentStepId = $this->store->get('current_step_id');
    $this->stepsAccessSettings = $this->store->get('steps_access_settings');
  }

  /**
   * Limit access function.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   */
  public function checkAccess(AccountInterface $account) {
    // If store access setting is defined, at less one of the step is validated.
    if ($this->stepsAccessSettings) {
      $current_step = $this->stepsAccessSettings[$this->currentRouteName];

      // If current route is already passed or current step.
      // @todo : add condition : if prev step is passed.
      if ($current_step['passed'] == TRUE || $current_step['step_id'] == $this->currentStepId) {
        return AccessResult::allowed();
      } else {
        return AccessResult::forbidden();
      }

    } else {
      /*
       * No store : redirect to first step.
       *
       * If you want to add this access check to the first step, get the default
       * route name from defaut steps values, check if current route name == first default route.
       */
      return AccessResult::forbidden();
    }

    // Security.
    return AccessResult::forbidden();
  }

  /**
   * Helper to get the last step passed.
   *
   * @return int|string
   */
  private function get_last_passed_step() {
    $last = 1;
    foreach ($this->stepsAccessSettings as $step_id => $stepAccessSettings) {
      if($stepAccessSettings['passed']) {
        $last = $step_id;
      }
    }
    return $last;
  }

}
