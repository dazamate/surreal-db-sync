<?php

namespace Dazamate\SurrealGraphSync\Migration;

use Dazamate\SurrealGraphSync\Enum\GraphTable;
use  Dazamate\SurrealGraphSync\Query\QueryBuilder;

if ( ! defined( 'ABSPATH' ) ) exit;

class UserRelationsMigration {    
    const MIGRATION_GROUP = 'generic_wordpress_types';
    const MIGRATION_NAME = 'user_relations_migration';
    const MIGRATION_DATE = '2025-01-03';

    public static function load_hooks() {
        add_filter('get_surreal_graph_migrations', [__CLASS__, 'build_migration_data'], 1, 1);
    }

    public static function build_migration_data(array $migrations): array {
        $queries = [];
        $queries['up'] = self::up();
        $queries['down'] = self::down();
        $queries['datetime'] = self::MIGRATION_DATE;
        $queries['name'] = self::MIGRATION_NAME;

        $migrations[self::MIGRATION_GROUP][self::MIGRATION_NAME] = $queries;
        return $migrations;
    }

    public static function up(): array {
        $up = [];

        return $up;
    }

    public static function down(): array {
        return [];
    }
}
