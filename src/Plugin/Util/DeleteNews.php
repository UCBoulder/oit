<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Finds and deletes old news nodes not referenced anywhere on the site.
 */
class DeleteNews {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a new DeleteNews object.
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    AliasManagerInterface $alias_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->aliasManager = $alias_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Build the deletion plan without performing any deletes.
   *
   * @param int $years
   *   News nodes whose created timestamp is older than this many years
   *   are candidates for deletion.
   *
   * @return array
   *   [
   *     'deleted' => [nid => title, ...],
   *     'skipped' => [nid => ['title' => '...', 'reason' => '...'], ...],
   *   ]
   */
  public function findDeletable(int $years): array {
    $candidates = $this->getCandidates($years);
    if (empty($candidates)) {
      return ['deleted' => [], 'skipped' => []];
    }

    $alias_map = $this->buildAliasMap($candidates);
    $referenced = $this->buildReferencedSet($candidates, $alias_map);

    $deleted = array_diff_key($candidates, $referenced);
    $skipped = [];
    foreach ($referenced as $nid => $reason) {
      $skipped[$nid] = [
        'title' => $candidates[$nid],
        'reason' => $reason,
      ];
    }

    return ['deleted' => $deleted, 'skipped' => $skipped];
  }

  /**
   * Build the plan and execute it.
   *
   * Calls findDeletable() internally, then deletes every node in the
   * 'deleted' list and writes a watchdog notice per node.
   *
   * @param int $years
   *   News nodes whose created timestamp is older than this many years
   *   are candidates for deletion.
   *
   * @return array
   *   Same shape as findDeletable().
   */
  public function deleteNews(int $years): array {
    $result = $this->findDeletable($years);
    $logger = $this->loggerFactory->get('oit');

    if (!empty($result['deleted'])) {
      $storage = $this->entityTypeManager->getStorage('node');
      $chunks = array_chunk($result['deleted'], 50, TRUE);
      foreach ($chunks as $chunk) {
        foreach ($chunk as $nid => $title) {
          $logger->notice('Deleted news nid:@nid title:@title', [
            '@nid' => $nid,
            '@title' => $title,
          ]);
        }
        $entities = $storage->loadMultiple(array_keys($chunk));
        $storage->delete($entities);
      }
    }

    $logger->notice('News deletion: Deleted @deleted / Skipped @skipped', [
      '@deleted' => count($result['deleted']),
      '@skipped' => count($result['skipped']),
    ]);

    return $result;
  }

  /**
   * Query candidate news nodes older than $years years.
   */
  protected function getCandidates(int $years): array {
    $cutoff = strtotime("-{$years} years");
    $query = $this->connection->select('node_field_data', 'n');
    $query->fields('n', ['nid', 'title']);
    $query->condition('n.type', 'news');
    $query->condition('n.created', $cutoff, '<');
    $candidates = [];
    foreach ($query->execute() as $row) {
      $candidates[(int) $row->nid] = $row->title;
    }
    return $candidates;
  }

  /**
   * Build a map of nid => alias for candidates that have a custom alias.
   */
  protected function buildAliasMap(array $candidates): array {
    $alias_map = [];
    foreach (array_keys($candidates) as $nid) {
      $alias = $this->aliasManager->getAliasByPath('/node/' . $nid);
      if ($alias !== '/node/' . $nid) {
        $alias_map[$nid] = $alias;
      }
    }
    return $alias_map;
  }

  /**
   * Run all reference checks and return [nid => reason] for referenced nodes.
   *
   * Checks run in order A -> B -> C -> D; first match wins per nid.
   */
  protected function buildReferencedSet(array $candidates, array $alias_map): array {
    $referenced = [];
    $this->checkLongTextFields($candidates, $alias_map, $referenced);
    $this->checkEntityReferenceFields($candidates, $referenced);
    $this->checkMenuLinks($candidates, $alias_map, $referenced);
    $this->checkRedirects($candidates, $referenced);
    return $referenced;
  }

  /**
   * Check A: Scan text_long, text_with_summary, and string_long fields.
   */
  protected function checkLongTextFields(array $candidates, array $alias_map, array &$referenced): void {
    $unique_fields = [];
    $seen = [];
    foreach (['text_long', 'text_with_summary', 'string_long'] as $type) {
      foreach ($this->entityFieldManager->getFieldMapByFieldType($type) as $entity_type => $fields) {
        foreach (array_keys($fields) as $field_name) {
          $key = $entity_type . ':' . $field_name;
          if (!isset($seen[$key])) {
            $seen[$key] = TRUE;
            $unique_fields[] = [$entity_type, $field_name];
          }
        }
      }
    }

    $nids = array_keys($candidates);
    foreach ($unique_fields as [$entity_type, $field_name]) {
      $table = $entity_type . '__' . $field_name;
      $value_col = $field_name . '_value';
      if (!$this->connection->schema()->tableExists($table)) {
        continue;
      }

      foreach (array_chunk($nids, 200) as $chunk_nids) {
        $unreferenced_nids = array_diff($chunk_nids, array_keys($referenced));
        if (empty($unreferenced_nids)) {
          continue;
        }

        $nid_pattern = implode('|', $unreferenced_nids);
        $alias_parts = [];
        foreach ($unreferenced_nids as $nid) {
          if (isset($alias_map[$nid])) {
            $alias_parts[] = $this->mysqlRegexpEscape(ltrim($alias_map[$nid], '/'));
          }
        }

        $query = $this->connection->select($table, 'f');
        $query->fields('f', ['entity_id', $value_col]);
        $or = $query->orConditionGroup()
          ->where("f.`{$value_col}` REGEXP :node_pat", [
            ':node_pat' => '/node/(' . $nid_pattern . ')([^0-9]|$)',
          ]);
        if (!empty($alias_parts)) {
          $or->where("f.`{$value_col}` REGEXP :alias_pat", [
            ':alias_pat' => '(' . implode('|', $alias_parts) . ')([^a-zA-Z0-9/_-]|$)',
          ]);
        }
        $query->condition($or);

        foreach ($query->execute() as $row) {
          $value = $row->$value_col;
          foreach ($unreferenced_nids as $nid) {
            if (isset($referenced[$nid])) {
              continue;
            }
            if (preg_match('#/node/' . $nid . '([^0-9]|$)#', $value)) {
              $referenced[$nid] = "Referenced in {$field_name} of {$entity_type}/{$row->entity_id}";
              continue;
            }
            if (isset($alias_map[$nid])) {
              $alias = ltrim($alias_map[$nid], '/');
              if (preg_match('#' . preg_quote($alias, '#') . '([^a-zA-Z0-9/_-]|$)#', $value)) {
                $referenced[$nid] = "Referenced via alias in {$field_name} of {$entity_type}/{$row->entity_id}";
              }
            }
          }
        }
      }
    }
  }

  /**
   * Check B: Entity reference fields whose target_type is node.
   */
  protected function checkEntityReferenceFields(array $candidates, array &$referenced): void {
    $nids = array_keys($candidates);
    foreach ($this->entityFieldManager->getFieldMapByFieldType('entity_reference') as $entity_type => $fields) {
      try {
        $storage_defs = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
      }
      catch (\Exception $e) {
        continue;
      }

      foreach (array_keys($fields) as $field_name) {
        if (!isset($storage_defs[$field_name])) {
          continue;
        }
        if ($storage_defs[$field_name]->getSetting('target_type') !== 'node') {
          continue;
        }

        $table = $entity_type . '__' . $field_name;
        $target_col = $field_name . '_target_id';
        if (!$this->connection->schema()->tableExists($table)) {
          continue;
        }

        foreach (array_chunk($nids, 200) as $chunk_nids) {
          $unreferenced = array_diff($chunk_nids, array_keys($referenced));
          if (empty($unreferenced)) {
            continue;
          }

          $query = $this->connection->select($table, 'er');
          $query->fields('er', ['entity_id', $target_col]);
          $query->condition($target_col, $unreferenced, 'IN');
          foreach ($query->execute() as $row) {
            $nid = (int) $row->$target_col;
            if (!isset($referenced[$nid])) {
              $referenced[$nid] = "Referenced via {$field_name} on {$entity_type}/{$row->entity_id}";
            }
          }
        }
      }
    }
  }

  /**
   * Check C: Menu links pointing at candidate nodes.
   */
  protected function checkMenuLinks(array $candidates, array $alias_map, array &$referenced): void {
    if (!$this->connection->schema()->tableExists('menu_link_content_data')) {
      return;
    }

    $uri_to_nid = [];
    foreach (array_keys($candidates) as $nid) {
      if (isset($referenced[$nid])) {
        continue;
      }
      foreach (['entity:node/' . $nid, 'internal:/node/' . $nid] as $uri) {
        $uri_to_nid[$uri] = $nid;
      }
      if (isset($alias_map[$nid])) {
        $uri = 'internal:' . $alias_map[$nid];
        $uri_to_nid[$uri] = $nid;
      }
    }

    foreach (array_chunk(array_keys($uri_to_nid), 200) as $chunk) {
      $query = $this->connection->select('menu_link_content_data', 'm');
      $query->fields('m', ['id', 'link__uri', 'menu_name']);
      $query->condition('link__uri', $chunk, 'IN');
      foreach ($query->execute() as $row) {
        $nid = $uri_to_nid[$row->link__uri] ?? NULL;
        if ($nid !== NULL && !isset($referenced[$nid])) {
          $referenced[$nid] = "Linked from menu '{$row->menu_name}'";
        }
      }
    }
  }

  /**
   * Check D: Redirects whose destination is a candidate node.
   */
  protected function checkRedirects(array $candidates, array &$referenced): void {
    if (!$this->connection->schema()->tableExists('redirect')) {
      return;
    }

    $uri_to_nid = [];
    foreach (array_keys($candidates) as $nid) {
      if (!isset($referenced[$nid])) {
        $uri_to_nid['internal:/node/' . $nid] = $nid;
      }
    }

    foreach (array_chunk(array_keys($uri_to_nid), 200) as $chunk) {
      $query = $this->connection->select('redirect', 'r');
      $query->fields('r', ['rid', 'redirect_source__path', 'redirect_redirect__uri']);
      $query->condition('redirect_redirect__uri', $chunk, 'IN');
      foreach ($query->execute() as $row) {
        $nid = $uri_to_nid[$row->redirect_redirect__uri] ?? NULL;
        if ($nid !== NULL && !isset($referenced[$nid])) {
          $referenced[$nid] = "Redirect /{$row->redirect_source__path} points to this node";
        }
      }
    }
  }

  /**
   * Escape special characters for MySQL REGEXP patterns.
   */
  protected function mysqlRegexpEscape(string $str): string {
    return preg_replace('/([.^$*+?{}()\[\]\\\\|])/', '\\\\$1', $str);
  }

}
