<?php

namespace Drupal\paragraphs\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\field\Multifield;

/**
 * Configure field instance settings for multifields.
 *
 * @MigrateProcessPlugin(
 *   id = "multifield_field_instance_settings"
 * )
 */
class MultifieldFieldInstanceSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $type = $row->getSourceProperty('type');

    if ($type == 'multifield') {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo('paragraph');

      $target_bundle = $row->getSourceProperty('field_name');
      // Remove field_ prefix for new bundle.
      $target_bundle = substr($target_bundle, Multifield::PREFIX_LENGTH);

      if (!isset($bundles[$target_bundle])) {
        throw new MigrateSkipRowException('No target paragraph bundle found for multifield');
      }

      // Enable only this paragraph type for this field.
      $weight = 0;
      $value['handler_settings']['negate'] = 0;
      $value['handler_settings']['target_bundles'] = [$target_bundle => $target_bundle];
      $value['handler_settings']['target_bundles_drag_drop'][$target_bundle] = [
        'enabled' => TRUE,
        'weight' => ++$weight,
      ];
      unset($bundles[$target_bundle]);

      foreach ($bundles as $bundle_name => $bundle) {
        $value['handler_settings']['target_bundles_drag_drop'][$bundle_name] = [
          'enabled' => FALSE,
          'weight' => ++$weight,
        ];
        unset($bundles[$bundle_name]);
      }
    }
    return $value;
  }

}
