<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class PostSyncService {
    public static function load_hooks() {
        add_action('surreal_sync_post', [__CLASS__, 'sync_post'], 10, 3);
        add_action('surreal_delete_post', [__CLASS__, 'delete_post'], 10, 1);
    }

    public static function sync_post(int $post_id, string $post_type, array $mapped_data) {
        $node_type = apply_filters('surreal_graph_node_name', '', $post_type, $post_id);

        // It's not a post type this plugin handles
        if (empty($node_type)) {
            throw new \Exception('Unable to map the post type to surreal node type. Please register in filter surreal_graph_node_name');
        }

        // Build the entire CONTENT clause to set/update all the fields
        // Note, SET can be used if you want to append data, not override all 
        $content_obj = QueryBuilder::build_object_str($mapped_data);
        $where_clause = 'post_id = ' . $post_id;

        $q = "UPSERT {$node_type} CONTENT {$content_obj} WHERE {$where_clause} RETURN id;";

        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            add_action('admin_notices', [__CLASS__, 'render_surreal_db_conn_error_notice']);
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
            return;
        }

        $res = $db->query($q);

        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            add_action('admin_notices', [__CLASS__, 'render_surreal_id_error_notice']);
            return;
        }
        
        update_post_meta($post_id, 'surreal_id', $surreal_id);

        $mappings = apply_filters('surreal_sync_post_related_mapping', [], $post_id, $post_type);

        //var_dump($mappings); exit;

        foreach($mappings as $mapping) {
            self::map_relation($mapping, $db);
        }

        exit;
        
        // header("Content-Type: text/plain");
        // var_dump($q); exit;
    }

    private static function try_get_record_id_from_response(array $res): ?string {
        if ($res[0][0]['id'] instanceof \Surreal\Cbor\Types\Record\RecordId) {
            return $res[0][0]['id']->toString();
        }

        return $res[0][0]['id'] ?? null;
    }

    private static function render_surreal_id_error_notice() { ?>
        <div class="notice notice-error is-dismissible">
            <p>Did not get an ID back from surreal</p>
        </div>
    <?php
    }

    private static function render_surreal_db_conn_error_notice() { ?>
        <div class="notice notice-error is-dismissible">
            <p>Unable to establish connection database to Surreal DB</p>
        </div>
    <?php
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
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
        }

        $res = $db->query($q);
    }

    private static function validate_sync_data(array $relation_data): bool {
        $required_keys = [
            'from_record',
            'to_record',
            'relation_name'
        ];
        
        $diff = array_diff_key(array_flip($relation_data), array_keys($relation_data));

        if (count($diff) > 0) {
            add_action('admin_notices', function() use ($diff) { ?>
                <div class="notice notice-error is-dismissible">
                    <p>Surreal mapping Error - Couldn't sync data. Missing keys: <?php echo implode(', ', $diff); ?></p>
                </div>
            <?php });

            return false;
        }

        return true;
    }

    private static function validate_relation_data(array $relation_data): bool {
        $required_keys = [
            'from_record',
            'to_record',
            'relation_name'
        ];
        
        $diff = array_diff_key(array_flip($required_keys), $relation_data);
        
        if (count($diff) > 0) {
            add_action('admin_notices', function() use ($diff) { ?>
                <div class="notice notice-error is-dismissible">
                    <p>Surreal mapping Error - Couldn't map relationship data. Missing keys: <?php echo implode(', ', $diff); ?></p>
                </div>
            <?php });

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

        header('Content-Type: text/plain');
        echo '<pre>';
        var_dump($q);
        var_dump($db->query($q));
        echo '</pre>';
        // exit;

    }
}
