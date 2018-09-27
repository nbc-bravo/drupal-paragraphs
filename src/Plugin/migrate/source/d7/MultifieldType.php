<?php

namespace Drupal\paragraphs\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\field\Multifield;
use Drupal\paragraphs\Plugin\migrate\source\DrupalSqlBase;

/**
 * Multifield Type source plugin.
 *
 * @MigrateSource(
 *   id = "d7_multifield_type",
 *   source_module = "multifield"
 * )
 */
class MultifieldType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('field_config', 'fc')
      ->fields('fc');
    $query->condition('fc.type', 'multifield');
    $query->condition('fc.active', TRUE);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $name = $row->getSourceProperty('field_name');

    // Remove field_ prefix for new bundle.
    $bundle = substr($name, Multifield::PREFIX_LENGTH);
    $row->setSourceProperty('bundle', $bundle);

    // Field collections don't have descriptions, optionally add one.
    if ($this->configuration['add_description']) {
      $row->setSourceProperty('description',
        'Migrated from multifield ' . $name);
    }
    else {
      $row->setSourceProperty('description', '');
    }

    // Set label from bundle because we don't have a label in D7 multifields.
    $row->setSourceProperty('name',
      ucfirst(preg_replace('/_/', ' ', $bundle)));

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'field_name' => $this->t('Original multifield bundle/field_name'),
      'bundle' => $this->t('Paragraph type machine name'),
      'name' => $this->t('Paragraph type label'),
      'description' => $this->t('Paragraph type description'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['field_name']['type'] = 'string';

    return $ids;
  }

}
