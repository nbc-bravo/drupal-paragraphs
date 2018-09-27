<?php

namespace Drupal\paragraphs\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Field Plugin for multifield migrations.
 *
 * @todo Implement ::processFieldValues()
 * @see https://www.drupal.org/project/paragraphs/issues/2911244
 *
 * @MigrateField(
 *   id = "multifield",
 *   core = {7},
 *   type_map = {
 *     "multifield" = "entity_reference_revisions",
 *   },
 *   source_module = "multifield",
 *   destination_module = "paragraphs",
 * )
 */
class Multifield extends FieldPluginBase {

  /*
   * Length of the 'field_' prefix that multifield prepends to bundles.
   */
  const PREFIX_LENGTH = 6;

  /**
   * {@inheritdoc}
   *
   * @TODO The entity_id_name is hardcoded to nid, which only works for nodes.
   */
  public function defineValueProcessPipeline(
    MigrationInterface $migration,
    $field_name,
    $data
  ) {
    $process = [
      'plugin' => 'multifield_lookup',
      'entity_id_name' => 'nid',
      'field_name'=> $field_name,
    ];
    $migration->setProcessOfProperty($field_name, $process);

    // Add the respective multifield migration as a dependency.
    $dependencies = $migration->getMigrationDependencies();
    $migration_dependency = 'd7_multifield:' . substr($field_name, static::PREFIX_LENGTH);
    $dependencies['optional'][] = $migration_dependency;
    $migration->set('migration_dependencies', $dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldFormatterMigration(MigrationInterface $migration) {
    $view_mode = [
      'multifield' => [
        'plugin' => 'paragraphs_process_on_value',
        'source_value' => 'type',
        'expected_value' => 'multifield',
        'process' => [
          'plugin' => 'get',
          'source' => 'formatter/settings/view_mode',
        ],
      ],
    ];
    $migration->mergeProcessOfProperty('options/settings/view_mode', $view_mode);

    parent::alterFieldFormatterMigration($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'multifield_default' => 'entity_reference_revisions_entity_view',
    ] + parent::getFieldFormatterMap();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return ['multifield_default' => 'entity_reference_paragraphs']
      + parent::getFieldWidgetMap();
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldMigration(MigrationInterface $migration) {
    $settings = [
      'multifield' => [
        'plugin' => 'multifield_field_settings',
      ],
    ];
    $migration->mergeProcessOfProperty('settings', $settings);

    parent::alterFieldMigration($migration);
  }

  /**
   * @inheritDoc
   */
  public function alterFieldInstanceMigration(MigrationInterface $migration) {
    $settings = [
      'multifield' => [
        'plugin' => 'multifield_field_instance_settings',
      ],
    ];
    $migration->mergeProcessOfProperty('settings', $settings);

    parent::alterFieldInstanceMigration($migration);
  }

}
