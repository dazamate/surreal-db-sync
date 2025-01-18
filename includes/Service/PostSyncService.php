<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Manager\SyncManager;

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

        // Build the entire SET clause to set/update all the fields
        $set_clause = self::build_set_clause($mapped_data);
        $where_clause = 'post_id = ' . $post_id;

        $q = "UPSERT {$node_type} SET {$set_clause} WHERE {$where_clause} RETURN id;";

        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
        }

        $res = $db->query($q);

        $surreal_id = self::try_get_record_id_from_response($res);

        if (empty($surreal_id)) {
            add_action('admin_notices', [__CLASS__, 'render_surreal_id_error_notice']);
            return;
        }
        
        update_post_meta($post_id, 'surreal_id', $surreal_id);

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
            <p><?php 'Did not get an ID back from surreal'; ?></p>
        </div>
    <?php
    }    
    
    private static function get_primative_type(string $type): string {
        if (strpos($type, 'array') === 0 || strpos($type, '<array') === 0) {
            return 'array';
        }

        if (strpos($type, 'record') === 0 || strpos($type, '<record') === 0) {
            return 'record';
        }

        return $type;
    }

    private static function is_associative(array $arr): bool {
        if ([] === $arr) return false; // Empty array is considered sequential.
    
        $expectedKey = 0;
        foreach ($arr as $key => $_) {
            if ($key !== $expectedKey) {
                return true;
            }
            $expectedKey++;
        }
        return false;
    }

    private static function get_field_value(array $field_data): mixed {
        // If there's no 'value' key, return NULL
        if (!array_key_exists('value', $field_data)) return null;

        // If this is a number field, handle 0 correctly
        if ($field_data['type'] === 'number') {
            if ($field_data['value'] === '0' || $field_data['value'] === 0) {
                return 0;
            } else {
                return $field_data['value'];
            }
        }

        // For all other cases, just return the raw value
        return empty($field_data['value']) ? null : $field_data['value'];
    }

    private static function get_record_type(string $value): ?string {
        if (preg_match('/<([^>]*)>/', $input, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function get_record_id_from_post(int $post_id): ?string {
        return get_post_meta($post_id, 'surreal_id', true) ?: null;
    }

    private static function build_set_clause(array $mapped_data): string {
        $set_clauses = [];

        foreach($mapped_data as $field_key => $field_data) {
            if (!isset($field_data['type'])) continue;
            
            if (self::get_field_value($field_data) === null) {
                $fields[] = sprintf('%s = NULL', $field_key);
                continue;
            }
       
            switch (self::get_primative_type($field_data['type'])) {
                case 'string':
                    $set_clauses[] = sprintf("%s = <string>'%s'", $field_key, $field_data['value']);
                    break;

                case 'number':                    
                    $set_clauses[] = sprintf("%s = <number>%d", $field_key, $field_data['value']);
                    break;

                case 'record': {
                    $record_id = self::get_record_id_from_post((int) $field_data['value']);

                    if ($record_id === null) {
                        $fields[] = sprintf('%s = NULL', $field_key);
                    } else {
                        $set_clauses[] = sprintf("%s = <%s>%s", 
                            $field_key, 
                            $field_data['type'],
                            $record_id
                        );
                    }

                    break;
                }

                case 'array':
                    $array_type = ($field_data['type'] === 'array') ? 'array' : $field_data['type'];
      
                    $set_clauses[] = sprintf("%s = <%s>%s",
                        $field_key,
                        $array_type,
                        self::build_array_str($field_data['value'])
                    );

                    break;

                case 'object':
                    $set_clauses[] = sprintf("%s = <object>%s",
                        $field_key,
                        self::build_object_str($field_data['value'])
                    );
                    break;

                default:
                    $set_clauses[] = sprintf("%s = %s%s", $field_key, $field_data['type'], $field_data['value']);
            }
        }

        return implode(', ', $set_clauses);
    }

    private static function build_array_str(array $array_data): string {
        if (empty($array_data)) return '[]';

        $fields = [];

        foreach ($array_data as $item) {
            // If $item is an array that has ['type'] and ['value'], handle it:
            if (is_array($item) && isset($item['type'], $item['value'])) {
                switch (self::get_primative_type($item['type'])) {
                    case 'array':
                        $fields[] = self::build_array_str($item['value']);
                        break;

                    case 'object':
                        $fields[] = self::build_object_str($item['value']);
                        break;

                    case 'record': {
                        $record_id = self::get_record_id_from_post((int) $item['value']);
    
                        if ($record_id !== null) {
                            $fields[] = $record_id;
                        }

                        break;
                    }

                    case 'string':
                        $fields[] = sprintf("'%s'", $item['value']);
                        break;

                    case 'number':
                        $fields[] = $item['value'];
                        break;

                    default:
                        $fields[] = sprintf("'%s'", $item['value']); 
                }
            }

            // If $item is an array but not the "['type', 'value']" structure, recurse as a plain array:
            elseif (is_array($item)) {
                $fields[] = self::build_array_str($item);
            }

            // If $item is a string or scalar
            elseif (is_string($item)) {
                $fields[] = sprintf("'%s'", $item);
            }

            else {
                $fields[] = $item; // e.g., booleans, integers
            }
        }

        return sprintf("[%s]", implode(', ', $fields));
    }

    private static function build_object_str(array $object_data): string {
        $fields = [];

        foreach($object_data as $object_key => $object_field_data) {
            if (self::get_field_value($object_field_data) === null) {
                $fields[] = sprintf('%s: NULL', $object_key );                
                continue;
            }

            switch(self::get_primative_type($object_field_data['type'])) {
                case 'array':
                    $array_type = ($object_field_data['type'] === 'array') ? '<array>' : $field_data['type'];

                    $fields[] = sprintf('%s: %s%s',
                        $object_key,
                        $array_type,
                        self::build_array_str($object_field_data['value'])
                    );
                    break;

                case 'object':
                    $fields[] = sprintf('%s: <object>%s',
                        $object_key,
                        self::build_object_str($object_field_data['value'])
                    );
                    break;

                case 'record': {
                    $record_id = self::get_record_id_from_post((int) $object_field_data['value']);

                    if ($record_id === null) {
                        $fields[] = sprintf('%s: NULL', $object_key);
                    } else {
                        $fields[] = sprintf("%s: <%s>%s",
                            $object_key, 
                            $object_field_data['type'],
                            $record_id
                        );
                    }

                    break;
                }
                
                case 'string':
                    $fields[] = sprintf("%s: <string>'%s'",
                        $object_key,
                        $object_field_data['value']
                    );
                    break;

                case 'number':
                    $fields[] = sprintf("%s: <number>%d",
                        $object_key,
                        $object_field_data['value']
                    );
                    break;    

                default:
                    $fields[] = sprintf('%s: <%s>%s',
                        $object_key,
                        $object_field_data['type'],
                        $object_field_data['value']
                    );                    
            }
        }

        return sprintf("{%s}", implode(', ', $fields));
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
}