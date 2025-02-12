<?php

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\UserErrorManager;
use Dazamate\SurrealGraphSync\Utils\Inputs;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Enum\QueryType;
use Dazamate\SurrealGraphSync\Enum\MetaKeys;

class UserSyncService extends SyncService {
    /**
     * Attach all hooks required for user sync.
     */
    public static function load_hooks() {
        add_action('surreal_sync_user', [__CLASS__, 'sync_user'], 10, 4);
    }    

    public static function sync_user(\WP_User $user, string $mapped_table_name, array $mapped_user_data, array $mapped_related_data) {       
        UserErrorManager::clear($user->ID);

        if (empty($mapped_table_name)) {
            UserErrorManager::add($user->ID, ['No mapped user table name found - you must use the "surreal_map_table_name" filter to map a post type to a Surreal DB table name']);
            return;
        }

        // No mapping done for this type
        if (empty($mapped_user_data)) {
            return;
        }

        $errors = [];
        
        self::validate($mapped_user_data, QueryType::USER, $errors);

        if (!empty($errors)) {
            UserErrorManager::add($user->ID, $errors);
            return false;
        }

        $db = self::get_surreal_db_conn($errors);

        if (!empty($errors)) {
            UserErrorManager::add($user->ID, $errors);
            return false;
        }

        // Build the entire CONTENT clause to set/update all the fields
        // Note, SET can be used if you want to append data, not override all 
        $content_obj = QueryBuilder::build_object_str($mapped_user_data);
        $where_clause = 'user_id = ' . $user->ID;

        $q = "UPSERT {$mapped_table_name} CONTENT {$content_obj} WHERE {$where_clause} RETURN id;";        

        try {
            $res = $db->query($q);
        } catch (\Throwable $e) {
            UserErrorManager::add($user->ID, [sprintf("Surreal query error: %s", $e->getMessage())]);
            return;
        }
        
        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            UserErrorManager::add($user->ID, ['Surreal sync error: Did not get a record ID from surreal']);
            return;
        }
        
        update_user_meta($user->ID, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, $surreal_id);

        // Only validate after we save the user and get a surreal id
        self::validate_relation_mapping($mapped_related_data, QueryType::USER, $errors);

        if (!empty($errors)) {
            UserErrorManager::add($user->ID, $errors);
            return false;
        }
 
        foreach($mapped_related_data as $mapping) {
            self::do_relation_upsert_query($mapping, $db);
        }
    }
}
