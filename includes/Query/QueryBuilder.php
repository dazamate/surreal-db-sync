<?php 

namespace Dazamate\SurrealGraphSync\Query;

class QueryBuilder {
    static public function build_create_field_query(string $table, string $key, array $rules): string {
        $q = sprintf('DEFINE FIELD %s ON TABLE %s TYPE %s', 
            $key,
            $table,
            $rules['type']
        );

        if (!empty($rules['default'])) {
            $q .= 'DEFAULT ' . $rules['default'];
        }

        if (!empty($rules['assert'])) {
            $q .= 'ASSERT ' . $rules['assert'];
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
