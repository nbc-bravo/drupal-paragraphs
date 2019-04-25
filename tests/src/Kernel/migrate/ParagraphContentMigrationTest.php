<?php

namespace Drupal\Tests\paragraphs\Kernel\migrate;

use Drupal\node\Entity\Node;

/**
 * Test Paragraph content migration.
 *
 * @group paragraphs
 * @require entity_reference_revisions
 */
class ParagraphContentMigrationTest extends ParagraphsMigrationTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'comment',
    'datetime',
    'datetime_range',
    'entity_reference_revisions',
    'field',
    'file',
    'image',
    'link',
    'menu_ui',
    'migrate_drupal',
    'node',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'telephone',
    'text',
    'user',
  ];

  /**
   * Test migrating the paragraph content.
   */
  public function testParagraphContentMigration() {
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', [
      'comment_entity_statistics',
    ]);

    $this->executeMigrationWithDependencies('d7_field_collection_revisions');
    $this->executeMigrationWithDependencies('d7_paragraphs_revisions');
    $this->executeMigrationWithDependencies('d7_node:paragraphs_test');

    $this->prepareMigrations([
      'd7_node:article' => [],
      'd7_node:forum' => [],
      'd7_node:test_content_type' => [],
    ]);

    $this->executeMigration('d7_node_revision:paragraphs_test');

    $node_8 = Node::load(8);
    $this->assertEquals('Field Collection Text Data One UND', $node_8->field_field_collection_test->referencedEntities()[0]->field_text->value);
    $this->assertEquals('Paragraph Field Two Bundle One Revision Two UND', $node_8->field_paragraph_one_only->referencedEntities()[0]->field_text->value);
  }

}
