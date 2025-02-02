<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\Inputs;

class AbstractSyncService {
    const SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';

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

    public static function validate(array $mapped_entity_data, array &$related_data_mappings, array &$errors): bool {        
        if (!MappingDataValidator::validate($mapped_entity_data, $errors)) {
            $errors = array_map(fn($e) => sprintf("Surreal DB '%s' mapping error: %s", $e, $mapped_entity_data), $errors);
            return false;
        }
        
        
        foreach ($related_data_mappings as $mapped_related_data) {
            if (!RelatedMappingDataValidator::validate($mapped_related_data, $errors)) {
                $errors = array_map(fn($e) => sprintf("Surreal DB '%s' related data mapping error: %s", $e, $mapped_entity_data), $errors);
            }
        }

        if (!empty($errors)) return false;

        // This block coverts any post ids found in the 'from' or 'to' fields and goes to that post and gets the surreal record id
        // Otherise it just passes the surreal record if it was directly placed in the mapping data
        foreach ($related_data_mappings as &$mapped_related_data) {
            $from_record = Inputs::parse_record_id($mapped_related_data['from_record']);

            if (empty($from_record)) {
                if (ctype($mapped_related_data['from_record'])) {
                    $errors[] = sprintf(
                        "Coulen't get 'from' surreal record id %s from '%s' relation. It may need re-saving",
                        $mapped_related_data['from_record'],
                        $mapped_related_data['relation_table']
                    );                    
                } else {
                    $errors[] = sprintf("No surreal 'from' record id was set in the realtion mapping data.");
                }

                return false;
            }
            
            $to_record = Inputs::parse_record_id($mapped_related_data['to_record']);
            
            if (empty($to_record)) {
                if (ctype($mapped_related_data['from_record'])) {
                    $errors[] = sprintf(
                        "Coulen't get 'from' surreal record id %s from '%s' relation. It may need re-saving",
                        $mapped_related_data['to_record'],
                        $mapped_related_data['relation_table']
                    );   
                } else {
                    $errors[] = sprintf("No surreal 'to' record id was set in the realtion mapping data.");
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
            $data['data'],
            $data['unique']
        );

        $res = $db->query($q);
    }
}
