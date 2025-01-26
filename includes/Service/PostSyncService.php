<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;

class PostSyncService {
    const SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';

    public static function load_hooks() {
        add_action('surreal_sync_post', [__CLASS__, 'sync_post'], 10, 3);
        add_action('surreal_delete_post', [__CLASS__, 'delete_post'], 10, 1);
    }

    public static function sync_post(int $post_id, string $post_type, array $mapped_data) {
        $node_type = apply_filters('surreal_graph_node_name', '', $post_type, $post_id);

        // It's not a post type this plugin handles
        if (empty($node_type)) {
            ErrorManager::add($post_id, ['Unable to map the post type to surreal node type. Please register in filter surreal_graph_node_name']);
            return;
        }

        // Build the entire CONTENT clause to set/update all the fields
        // Note, SET can be used if you want to append data, not override all 
        $content_obj = QueryBuilder::build_object_str($mapped_data);
        $where_clause = 'post_id = ' . $post_id;

        $q = "UPSERT {$node_type} CONTENT {$content_obj} WHERE {$where_clause} RETURN id;";

        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            ErrorManager::add($post_id, ['Surreal sync error: Unable to establish database connection']);
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
            return;
        }

        try {
            $res = $db->query($q);
        } catch (\Exception $e) {
            ErrorManager::add($post_id, [sprintf("Surreal query error: %s", $e->getMessage())]);
            return;
        }

        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            ErrorManager::add($post_id, ['Surreal sync error: Did not get a record ID from surreal']);
            return;
        }
        
        update_post_meta($post_id, 'surreal_id', $surreal_id);
        delete_post_meta($post_id, self::SURREAL_SYNC_ERROR_META_KEY);

        $mappings = apply_filters('surreal_sync_post_related_mapping', [], $post_id, $post_type);

        foreach($mappings as $mapping) {
            self::map_relation($mapping, $db);
        }
    }

    private static function try_get_record_id_from_response(array $res): ?string {
        if ($res[0][0]['id'] instanceof \Surreal\Cbor\Types\Record\RecordId) {
            return $res[0][0]['id']->toString();
        }

        return $res[0][0]['id'] ?? null;
    }

    public static function delete_post(\WP_Post $post): void {
        $surreal_record_id = get_post_meta($post->ID, SyncManager::SURREAL_DB_ID_META_KEY, true);

        // Extra checks so we dont accidently run a DELETE all command when there is a missing record argument
        if (
            $surreal_record_id === false ||                 // Make sure we have data
            strlen($surreal_record_id) < 1 ||               // Make sure string is more than 1 char
            strpos($surreal_record_id, ':') === false       // Make sure it has the record delimiter
        ) return;
        
        $q = "DELETE $surreal_record_id";

        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            ErrorManager::add($post->ID, ['Unable to get Surreal DB connection']);
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
            return;
        }

        $res = $db->query($q);
    }

    // TODO - move to different validate class
    private static function validate_relation_data(array $relation_data): bool {
        $required_keys = [
            'from_record',
            'to_record',
            'relation_name'
        ];
        
        $diff = array_diff_key(array_flip($required_keys), $relation_data);
        
        if (count($diff) > 0) {
            ErrorManager::add(get_the_id(), [sprintf("Surreal mapping Error - Couldn't sync data. Missing keys: %s", implode(', ', $diff))]);
            return false;
        }

        return true;
    }

    public static function map_relation(array $relation_data, \Surreal\Surreal $db) {
        $defaults = [
            'unique' => true
        ];

        $data = array_merge($defaults, $relation_data);

        if (!self::validate_relation_data($data)) {
            // echo '<pre>';
            // var_dump('nope');
            // echo '</pre>';
            return;
        }

        $q = QueryBuilder::build_relate_query(
            $data['from_record'],
            $data['to_record'],
            $data['relation_name'],
            $data['data'],
            $data['unique']
        );

        $res = $db->query($q);
    }
}
