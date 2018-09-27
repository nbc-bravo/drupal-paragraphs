<?php

namespace Drupal\paragraphs\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MultifieldLookup
 *
 * @MigrateProcessPlugin(
 *   id = "multifield_lookup"
 * )
 */
class MultifieldLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * MultifieldLookup constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param $storage
   *   The entity storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityStorageInterface $storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('paragraph')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // @TODO Notice that this is not using migrate map tables. Is this ok?
    $paragraph_ids = $this->storage->getQuery()
      ->condition('parent_id', $row->getSourceProperty($this->configuration['entity_id_name']))
      ->condition('parent_field_name', $this->configuration['field_name'])
      ->execute();

    foreach ($paragraph_ids as $paragraph_id) {
      $value[] = [
        'target_id' => $paragraph_id,
        'target_revision_id' => $paragraph_id,
      ];
    }

    return $value;
  }

}
