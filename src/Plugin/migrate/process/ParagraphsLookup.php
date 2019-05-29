<?php

namespace Drupal\paragraphs\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ParagraphsLookup.
 *
 * @MigrateProcessPlugin(
 *   id = "paragraphs_lookup"
 * )
 */
class ParagraphsLookup extends MigrationLookup {

  /**
   * The process plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigratePluginManager
   */
  protected $processPluginManager;

  /**
   * Constructs a MigrationLookup object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The Migration the plugin is being used in.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The Migration Plugin Manager Interface.
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $process_plugin_manager
   *   The process migration plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, MigrationPluginManagerInterface $migration_plugin_manager, MigratePluginManagerInterface $process_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migration_plugin_manager);
    $this->processPluginManager = $process_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.migrate.process')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $source_id_values = [];
    $destination_ids = NULL;
    if (isset($this->configuration['tags'])) {
      $tags = $this->configuration['tags'];
      if (!is_array($tags)) {
        $tags = [$tags];
      }
      foreach ($tags as $tag) {
        /** @var \Drupal\migrate\Plugin\MigrationInterface[] $migrations */
        $migrations = $this->migrationPluginManager->createInstancesByTag($tag);
        if (isset($this->configuration['tag_ids'][$tag])) {
          $configuration = ['source' => $this->configuration['tag_ids'][$tag]];
          $value = $this->processPluginManager
            ->createInstance('get', $configuration, $this->migration)
            ->transform(NULL, $migrate_executable, $row, $destination_property);
        }
        foreach ($migrations as $migration_id => $migration) {
          $source_id_values[$migration_id] = is_array($value) ? $value : [$value];
          $destination_ids = $this->lookupDestination($migration, $value, $migrate_executable, $row, $destination_property);
          if ($destination_ids) {
            break 2;
          }
        }
      }
    }
    elseif (isset($this->configuration['migration'])) {
      $migration_ids = $this->configuration['migration'];
      if (!is_array($migration_ids)) {
        $migration_ids = [$migration_ids];
      }
      /** @var \Drupal\migrate\Plugin\MigrationInterface[] $migrations */
      $migrations = $this->migrationPluginManager->createInstances($migration_ids);
      foreach ($migrations as $migration_id => $migration) {
        if (isset($this->configuration['source_ids'][$migration_id])) {
          $configuration = ['source' => $this->configuration['source_ids'][$migration_id]];
          $value = $this->processPluginManager
            ->createInstance('get', $configuration, $this->migration)
            ->transform(NULL, $migrate_executable, $row, $destination_property);
        }
        $source_id_values[$migration_id] = is_array($value) ? $value : [$value];
        $destination_ids = $this->lookupDestination($migration, $value, $migrate_executable, $row, $destination_property);
        if ($destination_ids) {
          break;
        }
      }
    }
    else {
      throw new MigrateException("Either Migration or Tags must be defined.");
    }

    if (!$destination_ids && !empty($this->configuration['no_stub'])) {
      return NULL;
    }

    if (!$destination_ids) {
      // If the lookup didn't succeed, figure out which migration will do the
      // stubbing.
      if (isset($this->configuration['stub_id'])) {
        $migration = $this->migrationPluginManager->createInstance($this->configuration['stub_id']);
      }
      else {
        $migration = reset($migrations);
      }
      $destination_plugin = $migration->getDestinationPlugin(TRUE);
      // Only keep the process necessary to produce the destination ID.
      $process = $migration->getProcess();

      // We already have the source ID values but need to key them for the Row
      // constructor.
      $source_ids = $migration->getSourcePlugin()->getIds();
      $values = [];
      foreach (array_keys($source_ids) as $index => $source_id) {
        $values[$source_id] = $source_id_values[$migration->id()][$index];
      }

      $stub_row = $this->createStubRow($values + $migration->getSourceConfiguration(), $source_ids);

      // Do a normal migration with the stub row.
      $migrate_executable->processRow($stub_row, $process);
      $destination_ids = [];
      $id_map = $migration->getIdMap();
      try {
        $destination_ids = $destination_plugin->import($stub_row);
      }
      catch (\Exception $e) {
        $id_map->saveMessage($stub_row->getSourceIdValues(), $e->getMessage());
      }

      if ($destination_ids) {
        $id_map->saveIdMapping($stub_row, $destination_ids, MigrateIdMapInterface::STATUS_NEEDS_UPDATE);
      }
    }
    if ($destination_ids) {
      if (count($destination_ids) == 1) {
        return reset($destination_ids);
      }
      else {
        return $destination_ids;
      }
    }
  }

  /**
   * Look for destination records.
   */
  protected function lookupDestination(MigrationInterface $migration, $value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      $value = [$value];
    }
    // TODO: remove after 8.6 support goes away.
    if (method_exists($this, 'skipOnEmpty')) {
      $this->skipOnEmpty($value);
    }
    else {
      $this->skipInvalid($value);
    }
    // Break out of the loop as soon as a destination ID is found.
    if ($destination_ids = $migration->getIdMap()->lookupDestinationIds($value)) {
      $destination_ids = array_combine(array_keys($migration->getDestinationPlugin()->getIds()), reset($destination_ids));
      return $destination_ids;
    }
    return FALSE;
  }

}
