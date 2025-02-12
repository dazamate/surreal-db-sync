<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Enum\QueryType;
use Dazamate\SurrealGraphSync\Enum\MetaKeys;

class PostSyncService extends SyncService {
    const SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';

    public static function load_hooks() {
        add_action('surreal_sync_post', [__CLASS__, 'sync_post'], 10, 3);
    }
    
    public static function sync_post(\WP_Post $post, string $mapped_table_name, array $mapped_entity_data) {
        ErrorManager::clear($post->ID);

        if (empty($mapped_table_name)) {
            ErrorManager::add($post->ID, ['No mapped table name found - you must use the "surreal_map_table_name" filter to map a post type to a Surreal DB table name']);
            return;
        }

        // No mapping done for this type
        if (empty($mapped_entity_data)) {
            return;
        }

        $errors = [];
        
        // Errors for mapped entity data
        self::validate($mapped_entity_data, QueryType::POST, $errors);

        if (!empty($errors)) {
            ErrorManager::add($post->ID, $errors);
            return false;
        }

        // Errors for failed db conn
        $db = self::get_surreal_db_conn($errors);

        if (!empty($errors)) {
            ErrorManager::add($post->ID, $errors);
            return false;
        }

        // Build the entire CONTENT clause to set/update all the fields
        // Note, SET can be used if you want to append data, not override all 
        $content_obj = QueryBuilder::build_object_str($mapped_entity_data);
        $where_clause = 'post_id = ' . $post->ID;

        $q = "UPSERT {$mapped_table_name} CONTENT {$content_obj} WHERE {$where_clause} RETURN id;";        

        try {
            $res = $db->query($q);
        } catch (\Exception $e) {
            ErrorManager::add($post->ID, [sprintf("Surreal query error: %s", $e->getMessage())]);
            return;
        }

        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            ErrorManager::add($post->ID, ['Surreal sync error: Did not get a record ID from surreal']);
            return;
        }
        
        update_post_meta($post->ID, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, $surreal_id);
        
        // get realted data here after the post has been saved and we have a post id and surreal id
        $mapped_related_data = apply_filters('surreal_graph_map_related', [], $post);

        // Errors for related mapped data
        self::validate_relation_mapping($mapped_related_data, QueryType::POST, $errors);

        if (!empty($errors)) {
            ErrorManager::add($post->ID, $errors);
            return false;
        }
 
        foreach($mapped_related_data as $mapping) {
            self::do_relation_upsert_query($mapping, $db);
        }
    }
}
