<?php

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\UserErrorManager;
use Dazamate\SurrealGraphSync\Utils\Inputs;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class UserSyncService extends AbstractSyncService {
    /**
     * Attach all hooks required for user sync.
     */
    public static function load_hooks() {
        add_action('surreal_sync_user', [__CLASS__, 'sync_user'], 10, 4);
    }    

    public static function sync_user(\Wp_User $user, string $mapped_table_name, array $mapped_user_data, array $mapped_related_data) {
        UserErrorManager::clear($user->ID);

        if (empty($mapped_table_name)) {
            UserErrorManager::add($$user->ID, ['No mapped user table name found - you must use the "surreal_map_table_name" filter to map a post type to a Surreal DB table name']);
            return;
        }

        // No mapping done for this type
        if (empty($mapped_user_data)) {
            return;
        }

        $errors = [];
        
        self::validate($mapped_user_data, $mapped_related_data, $errors);

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
        } catch (\Exception $e) {
            UserErrorManager::add($user->ID, [sprintf("Surreal query error: %s", $e->getMessage())]);
            return;
        }

        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            UserErrorManager::add($user->ID, ['Surreal sync error: Did not get a record ID from surreal']);
            return;
        }
        
        update_post_meta($user->ID, 'surreal_id', $surreal_id);
 
        foreach($mapped_related_data as $mapping) {
            self::do_relation_upsert_query($mapping, $db);
        }
    }
}