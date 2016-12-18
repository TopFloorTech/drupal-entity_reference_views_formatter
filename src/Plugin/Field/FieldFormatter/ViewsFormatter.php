<?php

namespace Drupal\entity_reference_views_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'slug' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_views",
 *   module = "entity_reference_views_formatter",
 *   label = @Translation("Views"),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class ViewsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $viewSetting = $this->getSetting('view');
    $minValues = $this->getSetting('min_values');
    $render = ($minValues == 0 || count($items) >= $minValues);

    if (!empty($items[0]) && !empty($viewSetting) && $render) {
      /** @var ContentEntityInterface $entity */
      $entity = $items[0]->getEntity();
      list($view_name, $view_display) = explode('|', $viewSetting);
      $view = Views::getView($view_name);

      $element[0] = [
        '#type' => 'viewfield',
        '#view' => $view,
        '#access' => $view && $view->access($view_display),
        '#view_name' => $view_name,
        '#view_display' => $view_display,
        '#view_arguments' => $this->getArguments($items, $entity),
        '#entity_type' => $entity->getEntityTypeId(),
        '#entity_id' => $entity->id(),
        '#entity' => $entity,
        '#theme' => 'viewfield_formatter_default',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'view' => '',
      'current_entity_argument' => FALSE,
      'min_values' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['view'] = [
      '#title' => $this->t('View'),
      '#description' => $this->t('Select the view to render for this field.'),
      '#type' => 'select',
      '#options' => $this->getViewOptions(),
      '#default_value' => $this->getSetting('view'),
    ];

    $element['current_entity_argument'] = [
      '#title' => $this->t('Use current entity ID as argument'),
      '#description' => $this->t('Passes the current entity ID as the view argument instead of the referenced IDs.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('current_entity_argument'),
    ];

    $element['min_values'] = [
      '#title' => $this->t('Minimum values'),
      '#description' => $this->t('Enter the minimum number of values required to render the view, or set to 0 to always render the view.'),
      '#type' => 'number',
      '#size' => 4,
      '#default_value' => $this->getSetting('min_values'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Renders the field as a View.');

    $view = $this->getSetting('view');

    if ($view) {
      list($viewName, $display) = explode('|', $view);

      $currentEntityArgument = $this->getSetting('current_entity_argument');

      $arguments = $currentEntityArgument ? 'Referenced Entity IDs' : 'Current Entity ID';

      $summary[] = $this->t('View: ' . $viewName);
      $summary[] = $this->t('Display: ' . $display);
      $summary[] = $this->t('Arguments: ' . $arguments);
      $summary[] = $this->t('Min. values: ' . $this->getSetting('min_values'));
    }

    return $summary;
  }

  /**
   * Returns arguments for rendering the view.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items from this field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   *
   * @return string
   *   The view's arguments comma-separated.
   */
  protected function getArguments(FieldItemListInterface $items, EntityInterface $entity) {
    $arguments = [];

    if ($this->getSetting('current_entity_argument')) {
      $arguments[] = $entity->id();
    } else {
      $ids = [];

      /** @var FieldItemInterface $item */
      foreach ($items as $item) {
        $ids[] = $item->get('target_id')->getValue();
      }

      $arguments[] = implode('+', $ids);
    }

    return implode(',', $arguments);
  }

  /**
   * Gets an array of options for available views.
   *
   * @return array
   *   An array of available options.
   */
  protected function getViewOptions() {
    $options = ['' => '-- Select --'];

    $views = Views::getEnabledViews();

    foreach ($views as $view) {
      $displays = $view->get('display');

      foreach ($displays as $display) {
        $options[$view->id() . '|' . $display['id']] = $view->label() . ' - ' . $display['display_title'];
      }
    }

    return $options;
  }
}
