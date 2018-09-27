<?php

namespace Drupal\paragraphs\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Configure field settings for paragraphs.
 *
 * @MigrateProcessPlugin(
 *   id = "multifield_field_settings"
 * )
 */
class MultifieldFieldSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($row->getSourceProperty('type') == 'multifield') {
      $value['target_type'] = 'paragraph';
    }
    return $value;
  }

}
