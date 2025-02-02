<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;


class PostSyncService extends AbstractSyncService {
    const SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';

    public static function load_hooks() {
        add_action('surreal_sync_post', [__CLASS__, 'sync_post'], 10, 4);
        add_action('surreal_delete_post', [__CLASS__, 'delete_post'], 10, 1);
    }
    
    public static function sync_post(int $post_id, string $mapped_table_name, array $mapped_entity_data, array $mapped_related_data) {     
        ErrorManager::clear($post_id);

        if (empty($mapped_table_name)) {
            ErrorManager::add($post_id, ['No mapped table name found - you must use the "surreal_map_table_name" filter to map a post type to a Surreal DB table name']);
            return;
        }

        // No mapping done for this type
        if (empty($mapped_entity_data)) {
            return;
        }

        $errors = [];
        
        self::validate($mapped_entity_data, $mapped_related_data, $errors);

        if (!empty($errors)) {
            ErrorManager::add($post_id, $errors);
            return false;
        }

        $db = self::get_surreal_db_conn($errors);

        if (!empty($errors)) {
            ErrorManager::add($post_id, $errors);
            return false;
        }

        // Build the entire CONTENT clause to set/update all the fields
        // Note, SET can be used if you want to append data, not override all 
        $content_obj = QueryBuilder::build_object_str($mapped_entity_data);
        $where_clause = 'post_id = ' . $post_id;

        $q = "UPSERT {$mapped_table_name} CONTENT {$content_obj} WHERE {$where_clause} RETURN id;";        

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
 
        foreach($mapped_related_data as $mapping) {
            self::do_relation_upsert_query($mapping, $db);
        }
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
}
