<?php 

namespace Dazamate\SurrealGraphSync\Query;

class QueryBuilder {
    static public function build_create_field_query(string $table, string $key, array $rules): string {
        $q = sprintf('DEFINE FIELD %s ON TABLE %s TYPE %s', 
            $key,
            $table,
            $rules['type']
        );
        
        if (is_string($rules['default'])) {
            $q .= sprintf(" DEFAULT '%s'", $rules['default']);
        } else if (is_numeric($rules['default']) && $rules['default'] === 0) {
            if ($rules['type'] === 'datetime' ) {
                $q .= ' DEFAULT d"1900-01-01"'; // Default to a 0 date
            } else {
                $q .= ' DEFAULT 0';
            }
        } else if (is_array($rules['default'])) {
            $arr = $rules['default'];

            if (count($arr) < 1) {
                $q .= ' DEFAULT []';
            } else {
                $q .= sprintf(" DEFAULT [%s]", implode(',', $arr));
            }
        } else if (!empty($rules['default'])) {
            $q .= ' DEFAULT ' . $rules['default'];
        }

        if (!empty($rules['assert'])) {
            $q .= ' ASSERT ' . $rules['assert'];
        }

        $q .= ';';

        return $q;
    }

    static public function build_create_field_query_on_array(string $table, array $params): string {
        $output = [];

        foreach ($params as $key => $rules) {
            $output[] = self::build_create_field_query($table, $key, $rules);
        }

        return implode(' ', $output);
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

    private static function get_record_type(string $value): ?string {
        if (preg_match('/<([^>]*)>/', $input, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function get_record_id_from_post(int $post_id): ?string {
        return get_post_meta($post_id, 'surreal_id', true) ?: null;
    }

    private static function get_field_value(array $data): mixed {
        // If there's no 'value' key, return NULL
        if (!array_key_exists('value', $data)) return null;

        // If this is a number field, handle 0 correctly
        if ($data['type'] === 'number') {
            if ($data['value'] === '0' || $data['value'] === 0) {
                return 0;
            } else {
                return $data['value'];
            }
        }

        // For all other cases, just return the raw value
        return empty($data['value']) ? null : $data['value'];
    }

    public static function build_set_clause(array $mapped_data): string {
        $set_clauses = [];

        foreach($mapped_data as $key => $data) {
            if (!isset($data['type'])) continue;
            
            if (self::get_field_value($data) === null) {
                $fields[] = sprintf('%s = NULL', $key);
                continue;
            }
       
            switch (self::get_primative_type($data['type'])) {
                case 'string':
                    $set_clauses[] = sprintf("%s = <string>'%s'", $key, $data['value']);
                    break;
                
                case 'datetime':
                    $set_clauses[] = sprintf("%s = <datetime>'%s'", $key, $data['value']);
                    break;
                    
                case 'number':                    
                    $set_clauses[] = sprintf("%s = <number>%d", $key, $data['value']);
                    break;

                case 'record': {
                    $record_id = self::get_record_id_from_post((int) $data['value']);

                    if ($record_id === null) {
                        $fields[] = sprintf('%s = NULL', $key);
                    } else {
                        $set_clauses[] = sprintf("%s = <%s>%s", 
                            $key, 
                            $data['type'],
                            $record_id
                        );
                    }

                    break;
                }

                case 'array':
                    $array_type = ($data['type'] === 'array') ? 'array' : $data['type'];
      
                    $set_clauses[] = sprintf("%s = <%s>%s",
                        $key,
                        $array_type,
                        self::build_array_str($data['value'])
                    );

                    break;

                case 'object':
                    $set_clauses[] = sprintf("%s = <object>%s",
                        $key,
                        self::build_object_str($data['value'])
                    );
                    break;

                default:
                    $set_clauses[] = sprintf("%s = %s%s", $key, $data['type'], $data['value']);
            }
        }

        return implode(', ', $set_clauses);
    }

    public static function build_array_str(array $array_data): string {
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

                    case 'datetime':
                        $fields[] = sprintf("<datetime>'%s'", $item['value']);
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

    public static function build_object_str(array $object_data): string {
        $fields = [];

        foreach($object_data as $key => $data) {
            if (self::get_field_value($data) === null) {
                $fields[] = sprintf('%s: NULL', $key );                
                continue;
            }

            switch(self::get_primative_type($data['type'])) {
                case 'array':
                    $array_type = ($data['type'] === 'array') ? '<array>' : $data['type'];

                    $fields[] = sprintf('%s: %s%s',
                        $key,
                        $array_type,
                        self::build_array_str($data['value'])
                    );
                    break;

                case 'object':
                    $fields[] = sprintf('%s: <object>%s',
                        $key,
                        self::build_object_str($data['value'])
                    );
                    break;

                case 'record': {
                    $record_id = self::get_record_id_from_post((int) $data['value']);

                    if ($record_id === null) {
                        $fields[] = sprintf('%s: NULL', $key);
                    } else {
                        $fields[] = sprintf("%s: <%s>%s",
                            $key, 
                            $data['type'],
                            $record_id
                        );
                    }

                    break;
                }
                
                case 'string':
                    $fields[] = sprintf("%s: <string>'%s'",
                        $key,
                        $data['value']
                    );
                    break;

                case 'datetime':
                    $fields[] = sprintf("%s: <datetime>'%s'",
                        $key,
                        $data['value']
                    );
                    break;

                case 'number':
                    $fields[] = sprintf("%s: <number>%d",
                        $key,
                        $data['value']
                    );
                    break;

                default:
                    $fields[] = sprintf('%s: <%s>%s',
                        $key,
                        $data['type'],
                        $data['value']
                    );
            }
        }

        return sprintf("{%s}", implode(', ', $fields));
    }

    static public function build_relate_query(
        string $from_record_id,
        string $to_record_id,
        string $relation_table_name,
        array $data,
        bool $unique = true // only one link allowed
    ) {
        /** 
         * Generates a relation query like this:
         * 
         * LET $rel_id = (SELECT id FROM created_by where in = ($person.id) and out = ($recipe.id)).id[0];
         * 
         * IF $rel_id is NONE THEN {
         *   LET $new_rel_id = (RELATE ($person.id)->created_by->($recipe.id));
         *   UPDATE $new_rel_id CONTENT { units: 'created' };
         * } else {
         *    UPDATE $rel_id CONTENT { units: 'updated' };
         * } END;
         */
        $data_obj_str = self::build_object_str($data);
    
        if (!$unique) {
            return sprintf(
                'RELATE (%s)->%s->(%s) CONTENT %s RETURN id;',
                $from_record_id,
                $relation_table_name,
                $to_record_id,
                $data_obj_str
            );
        }
    
        $q = sprintf(
            'LET $rel_id = (SELECT id FROM %s where in = %s and out = %s).id[0];',
            $relation_table_name,
            $from_record_id,
            $to_record_id
        );
    
        $q .= 'IF $rel_id is NONE THEN {';
        $q .= sprintf(
            '$rel_id = (RELATE (%s)->%s->(%s));',
            $from_record_id,
            $relation_table_name,
            $to_record_id
        );
    
        $q .= sprintf(
            'UPDATE $rel_id CONTENT %s;',
            $data_obj_str
        );
    
        $q .= '} else {';
    
        $q .= sprintf(
            'UPDATE $rel_id CONTENT %s;',
            $data_obj_str
        );
    
        $q .= '} END;';

        return $q;
    }
}
