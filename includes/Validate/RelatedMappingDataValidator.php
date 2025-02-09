<?php

namespace Dazamate\SurrealGraphSync\Validate;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;
use Dazamate\SurrealGraphSync\Validate\InputValidator;

class RelatedMappingDataValidator {    
    /**
     * Validate the structure of $relate_data needed by build_relate_query().
     *
     * Expected structure:
     *
     *  [
     *    'from_record'      => 'some_table:record_id',  // Required, string
     *    'to_record'        => 'some_table:record_id',  // Required, string
     *    'relation_table'   => 'my_relation_edge',      // Required, string (the SurrealDB edge table name)
     *    'data'             => [ ... ],                 // Optional, array of mapped data to pass to MappingDataValidator
     *    'unique'           => true|false               // Optional, boolean
     *  ]
     *
     * @param array $relate_data
     * @param array $errors  passed by reference, will be populated with validation errors
     * @return bool          true if valid, false if not
     */
    public static function validate(array $relate_data, array &$errors): bool
    {
        $is_valid = true;

        // Ensure from_record is provided - can be surreal record or post id
        if (
            empty($relate_data['from_record']) ||
            (
                ! InputValidator::is_surreal_db_record($relate_data['from_record']) &&
                ! ctype_digit((string)$relate_data['from_record'])
            )
        ) {
            $errors[] = 'Invalid or missing "from_record" in relation data.';
            $is_valid = false;
        }

        // Ensure to_record is provided - can be surreal record or post id
        if (
            empty($relate_data['to_record']) ||
            (
                ! InputValidator::is_surreal_db_record($relate_data['to_record']) &&
                ! ctype_digit((string)$relate_data['to_record'])
            )
        ) {
            $errors[] = 'Invalid or missing "to_record" in relation data.';
            $is_valid = false;
        }

        // Ensure relation_table is provided
        if (empty($relate_data['relation_table']) || ! is_string($relate_data['relation_table'])) {
            $errors[] = 'Invalid or missing "relation_table" in relation data.';
            $is_valid = false;
        }

        // Optional data array to pass through MappingDataValidator
        if (isset($relate_data['data'])) {
            // Must be an array
            if (!is_array($relate_data['data'])) {
                $errors[] = 'The "data" key must be a key value array if specified.';
                $is_valid = false;
            } else {
                // Validate the mapping data inside "data"
                $sub_errors = [];
                if (!MappingDataValidator::validate($relate_data['data'], $sub_errors)) {
                    $is_valid = false;
                    foreach($sub_errors as $err) {
                        $errors[] = 'Related data mapping error: ' . $err;
                    }
                }
            }
        }

        // Optional unique field
        if (isset($relate_data['unique']) && ! is_bool($relate_data['unique'])) {
            $errors[] = 'The "unique" key must be a boolean if specified.';
            $is_valid = false;
        }

        return $is_valid;
    }
}