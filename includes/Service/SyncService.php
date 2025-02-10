<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\Inputs;
use Dazamate\SurrealGraphSync\Enum\QueryType;
use Dazamate\SurrealGraphSync\Enum\MetaKeys;

class SyncService {
    const SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';

    public static function load_hooks() {
        add_action('surreal_delete_record', [__CLASS__, 'delete_record']);
    }

    protected static function get_surreal_db_conn(array $errors): ?\Surreal\Surreal {
        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            $errors[] = ['Surreal sync error: Unable to establish database connection'];
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
            return NULL;
        }

        return $db;
    }

    protected static function try_get_record_id_from_response(array $res): ?string {
        if ($res[0][0]['id'] instanceof \Surreal\Cbor\Types\Record\RecordId) {
            return $res[0][0]['id']->toString();
        }

        return $res[0][0]['id'] ?? null;
    }

    public static function validate(array $mapped_entity_data, array &$related_data_mappings, QueryType $query_type, array &$errors): bool {        
        if (!MappingDataValidator::validate($mapped_entity_data, $errors)) {
            $errors = array_map(fn($e) => sprintf("Surreal DB '%s' mapping error: %s", $e, $mapped_entity_data), $errors);
            return false;
        }

        $validate_errors = [];

        foreach ($related_data_mappings as $mapped_related_data) {
            $e = [];
            if (!RelatedMappingDataValidator::validate($mapped_related_data, $e)) {                
                $validate_errors = array_merge($e, $validate_errors);
            }
        }

        if (!empty($validate_errors)) {
            $validate_errors = array_map(fn($e) => 
                sprintf(
                    "Surreal DB '%s' related data mapping error: %s",
                    $mapped_related_data['relation_table'] ?? 'realtion not set',
                    $e
                ),
                $validate_errors
            );
            
            $errors = array_merge($validate_errors, $errors);
            return false;
        }

        // This block coverts any post ids found in the 'from' or 'to' fields and goes to that post and gets the surreal record id
        // Otherise it just passes the surreal record if it was directly placed in the mapping data
        foreach ($related_data_mappings as &$mapped_related_data) {
            if (empty($mapped_related_data['from_record'])) {
                $errors[] = sprintf("No surreal 'from' record id was set in the realtion mapping data.");
                return false;
            }

            if (empty($mapped_related_data['to_record'])) {
                $errors[] = sprintf("No surreal 'from' record id was set in the realtion mapping data.");
                return false;
            }

            $from_record = Inputs::parse_record_id($mapped_related_data['from_record'], $query_type);

            if (empty($from_record)) {
                // Check if the input data was an int (post id) and make error for that
                if (ctype_digit((string)$mapped_related_data['from_record'])) {
                    $errors[] = sprintf(
                        "Couldn't get 'from' surreal record user id: %s for '%s' relation. It may need re-saving",
                        $mapped_related_data['from_record'],
                        $mapped_related_data['relation_table']
                    );
                } else {
                    $errors[] = sprintf(
                        "Couldn't could parse surreal record '%s' when trying to map 'from_record' for '%s' relation.",
                        $mapped_related_data['from_record'],
                        $mapped_related_data['relation_table']
                    );
                }

                return false;
            }
            
            $to_record = Inputs::parse_record_id($mapped_related_data['to_record'], $query_type);

            if (empty($to_record)) {
                // Check if the input data was an int (post id) and make error for that
                if (ctype_digit((string)$mapped_related_data['to_record'])) {
                    $errors[] = sprintf(
                        "Couldn't get 'to' surreal record user id: %s for '%s' relation. It may need re-saving",
                        $mapped_related_data['to_record'],
                        $mapped_related_data['relation_table']
                    );
                } else {
                    $errors[] = sprintf(
                        "Couldn't could parse surreal record '%s' when trying to map 'to_record' for '%s' relation.",
                        $mapped_related_data['to_record'],
                        $mapped_related_data['relation_table']
                    );
                }

                return false;
            }

            $mapped_related_data['from_record'] = $from_record;
            $mapped_related_data['to_record'] = $to_record;
        }
        
        return true;
    }
    
    public static function do_relation_upsert_query(array $relation_data, \Surreal\Surreal $db) {
        $defaults = [
            'unique' => true
        ];

        $data = array_merge($defaults, $relation_data);

        $q = QueryBuilder::build_relate_query(
            $data['from_record'],
            $data['to_record'],
            $data['relation_table'],
            $data['data'] ?? [],
            $data['unique']
        );

        $res = $db->query($q);
    }

    public static function delete_record(string $surreal_record_id): bool {
        // Extra checks so we dont accidently run a DELETE all command when there is a missing record argument
        if (
            strlen($surreal_record_id) < 1 ||               // Make sure string is more than 1 char
            strpos($surreal_record_id, ':') === false       // Make sure it has the record delimiter
        ) return false;
        
        $q = "DELETE $surreal_record_id";

        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
            return false;
        }

        $res = $db->query($q);

        return true;
    }
}
