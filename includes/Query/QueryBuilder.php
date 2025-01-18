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

    public static function build_set_clause(array $mapped_data): string {
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
}
