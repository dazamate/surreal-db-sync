<?php

namespace Dazamate\SurrealGraphSync\Migration;

use Dazamate\SurrealGraphSync\Enum\GraphTable;
use  Dazamate\SurrealGraphSync\Query\QueryBuilder;

if ( ! defined( 'ABSPATH' ) ) exit;

class InitialMigration {    
    const MIGRATION_GROUP = 'generic_wordpress_types';
    const MIGRATION_NAME = 'initial_migration';
    const MIGRATION_DATE = '2025-01-01';

    public static function load_hooks() {
        add_filter('get_surreal_graph_migrations', [__CLASS__, 'build_migration_data'], 1, 1);
    }

    public static function build_migration_data(array $migrations): array {
        $queries                = [];
        $queries['up']          = self::up();
        $queries['down']        = self::down();
        $queries['datetime']    = self::MIGRATION_DATE;
        $queries['name']        = self::MIGRATION_NAME;

        $migrations[self::MIGRATION_GROUP][self::MIGRATION_NAME] = $queries;
        return $migrations;
    }

    public static function up(): array {
        $up = [];
        $up[] = self::create_migration_info_table();

        $up[] = self::create_image_table_query();
        $up[] = self::create_person_table_query();

        $up[] = self::create_image_fields_query();
        $up[] = self::create_person_fields_query();

        $up[] = self::create_migration_state_update_query();

        return $up;
    }

    public static function down(): array {
        return [];
    }

    protected static function create_migration_info_table(): string {
        return sprintf("CREATE %s:state;", GraphTable::MIGRATION->to_string());
    }

    protected static function create_image_table_query(): string {
        return sprintf("DEFINE TABLE %s SCHEMAFULL;", GraphTable::IMAGE->to_string());
    }

    protected static function create_person_table_query(): string {
        return sprintf("DEFINE TABLE %s SCHEMAFULL;", GraphTable::PERSON->to_string());
    }

    protected static function create_image_fields_query(): string {   
        $fields = [
            'width'     => [
                'type'      => 'number',
                'default'   => null,
                'assert'    => null
            ],
            'height'    => [
                'type'      => 'number',
                'default'   => null,
                'assert'    => null
            ],
            'url'       => [
                'type'      => 'string',
                'default'   => null,
                'assert'    => null
            ],
            'caption'   => [
                'type'      => 'string',
                'default'   => null,
                'assert'    => null
            ],
            'alt'       => [
                'type'      => 'string',
                'default'   => null,
                'assert'    => null
            ]
        ];
        
        return QueryBuilder::build_create_field_query_on_array(GraphTable::IMAGE->to_string(), $fields);
    }

    protected static function create_person_fields_query(): string {   
        $fields = [];
        return QueryBuilder::build_create_field_query_on_array(GraphTable::PERSON->to_string(), $fields);
    }

    protected static function create_migration_state_update_query(): string {
        return sprintf(
            'UPDATE %s:state SET %s.last_migration_name = "%s", %s.last_migration_time = <datetime>"%s";', 
            GraphTable::MIGRATION->to_string(),
            self::MIGRATION_GROUP,
            self::MIGRATION_NAME,
            self::MIGRATION_GROUP,
            self::MIGRATION_DATE
        );
    }
}
