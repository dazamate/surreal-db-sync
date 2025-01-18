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
}
