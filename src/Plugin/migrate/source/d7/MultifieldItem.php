<?php

namespace Drupal\paragraphs\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\field\Multifield;

/**
 * Multifield Item source plugin.
 *
 * Available configuration keys:
 * - field_name: (optional) If supplied, this will only return multifields
 *   of that particular type.
 *
 * @MigrateSource(
 *   id = "d7_multifield_item",
 *   source_module = "multifield",
 * )
 */
class MultifieldItem extends FieldableEntity {

  /**
   * Join string for getting current revisions.
   */
  const JOIN = 'fci.field_id = fc.id';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'field_name' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = 'field_data_' . $this->configuration['field_name'];
    $query = $this->select($table, 't')
      ->fields('t')
      ->condition('deleted', 0);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Adjust the bundle so it matches the Paragraph type.
    $name = $row->getSourceProperty('field_name');
    $bundle = substr($name, Multifield::PREFIX_LENGTH);
    $row->setSourceProperty('bundle', $bundle);

    $fields = $this->getSubfields($this->configuration['field_name']);
    // Field values are structured as
    // parent_field_name + _ + sub_field_name + _ + some_suffix
    // By default we check for
    // parent_field_name + _ + sub_field_name + _ + value and if that is
    // empty, then we use the first index, which usually returns the
    // value such as for target_id in entity reference fields.
    foreach ($fields as $field_name => $field_config) {
      $field_value = $row->getSourceProperty($this->configuration['field_name'] . '_' . $field_name . '_value');
      if (empty($field_value)) {
        $field_value_suffix = key($field_config['data']['indexes']);
        $field_value = $row->getSourceProperty($this->configuration['field_name'] . '_' . $field_name . '_' . $field_value_suffix);
      }
      $row->setSourceProperty($field_name, $field_value);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_type' => $this->t('The entity type'),
      'bundle' => $this->t('The entity bundle'),
      'entity_id' => $this->t('The entity identifier'),
      'revision_id' => $this->t('The entity revision identifier'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = [
      'entity_type' => [
        'type' => 'string',
      ],
      'entity_id' => [
        'type' => 'integer',
      ],
      'deleted' => [
        'type' => 'integer',
      ],
      'delta' => [
        'type' => 'integer',
      ],
      'language' => [
        'type' => 'string',
      ],
    ];

    return $ids;
  }

  /**
   * Returns the subfields for a multifield.
   *
   * @param string $field_name
   *   The multifield's field name.
   *
   * @return array
   *   An associative array with the subfields configuration.
   */
  protected function getSubfields($field_name) {
    $query = $this->select('field_config_instance', 'fci')
      ->fields('fci', ['field_id', 'field_name'])
      ->fields('fc', ['data'])
      ->condition('fci.entity_type', 'multifield')
      ->condition('fci.bundle', $field_name);
    $query->innerJoin('field_config', 'fc', static::JOIN);
    $fields = $query->condition('fci.deleted', 0)
      ->execute()
      ->fetchAllAssoc('field_name');

    foreach ($fields as $field_name => $field_data) {
      $fields[$field_name]['data'] = unserialize($field_data['data']);
    }

    return $fields;
  }

}
