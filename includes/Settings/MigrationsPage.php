<?php

namespace Dazamate\SurrealGraphSync\Settings;

use Dazamate\SurrealGraphSync\Enum\MigrationDirection;

if ( ! defined( 'ABSPATH' ) ) exit;

class MigrationsPage {
    static public function load_hooks() {
        add_action( 'admin_menu', [__CLASS__, 'surreal_sync_add_menu_item'] );
    }

    static private function get_previous_migrations(array $group_names): array {
        $db = apply_filters('get_surreal_db_conn', null);

        if ( ! ( $db instanceof \Surreal\Surreal ) ) {
            error_log('Unable to get Surreal DB connection ' . __FUNCTION__ . ' ' . __LINE__);
        }

        $q = sprintf("SELECT * FROM migration:state");

        $res = $db->query($q); 
        
        $last_migarations = array_reduce($group_names, function($carry = [], $group_name = '') {
            $carry[$group_name] = 0;
            return $carry;
        });

        foreach ($res[0][0] ?? [] as $k => $v) {
            if ($k === 'id') continue;

            if (($v['last_migration_time'] ?? null) instanceof \DateTime) {
                $last_migarations[$k] = $v['last_migration_time']->getTimeStamp();
            }
        }

        return $last_migarations;
    }

    static public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        /**
         * Should contain an orderd array with the migration name as the key, and array with an up and down key, each containing 
         * an ordered array of migrations to perform
         */
        $migrations = [];
        $migrations = apply_filters('get_surreal_graph_migrations', $migrations);

        self::sort_migrations($migrations);

        // Handle form submissions (Up/Down migrations)
        self::handle_migration_post();

        $prev_migrations = self::get_previous_migrations(array_keys($migrations));

        ?>
        <div class="wrap">
            <h1>Surreal Migrations</h1>

            <form method="POST">
                <?php wp_nonce_field( 'surreal_sync_migrations_nonce', 'surreal_sync_migrations_nonce_field' ); ?>

                <?php foreach ($migrations as $migration_group_name => $migration_group_data): ?>
                    <?php $friendly_migration_group_name = str_ireplace('_', ' ', $migration_group_name); ?>
                    
                    <?php $last_migration_stamp = $prev_migrations[$migration_group_name] ?? 0; ?>
                    <h2><?php echo $friendly_migration_group_name; ?></h2>

                    <?php if ($last_migration_stamp === 0): ?>
                        <p>Last migration date id: <i>none</i></p>
                    <?php else: ?>
                        <p>Last migration date id: <?php echo date('Y m d', $last_migration_stamp); ?></p>
                    <?php endif; ?>

                    <table class="widefat fixed" style="margin-bottom: 50px;" cellspacing="0">
                        <thead>
                            <tr>                            
                                <th class="manage-column column-title">Migration Number</th>
                                <th class="manage-column column-title">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $migration_group_data ) ) : ?>
                                <?php foreach ($migration_group_data as $migration_name => $migration_data ) : ?>
                                    <?php $has_migrated = $last_migration_stamp < strtotime($migration_data['datetime'] ?? 0); ?>
                                    <?php $friendly_migration_name = str_ireplace('_', ' ', $migration_name);?>
                                    <tr>
                                        <td><?php echo esc_html( $friendly_migration_name ); ?> - <?php echo date('Y m d', $last_migration_stamp); ?></td>
                                        <td>
                                            <button 
                                                type="submit"
                                                name="migration_up"
                                                value="<?php echo esc_attr( $migration_name ); ?>"
                                                class="button"
                                                <?php if (isset($migration_data['datetime'])): ?>
                                                    <?php echo ($has_migrated) ? '' :  'disabled'; ?>
                                                <?php endif; ?>
                                            >Migrate up</button>
                                            <button 
                                                type="submit"
                                                name="migration_down"
                                                value="<?php echo esc_attr( $migration_name ); ?>"
                                                class="button"
                                                <?php if (isset($migration_data['datetime'])): ?>
                                                    <?php echo ($has_migrated) ? 'disabled' : ''; ?>
                                                <?php endif; ?>
                                            >Migrate down</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Migrate All</td>
                                    <td>
                                        <button 
                                            type="submit"
                                            name="migration_up_all"
                                            value="<?php echo esc_attr( $migration_group_name ); ?>"
                                            class="button"
                                        >Migrate all <b><?php echo esc_attr( $friendly_migration_group_name ); ?></b> up</button>
                                        
                                        <button 
                                            type="submit" 
                                            name="migration_down_all" 
                                            value="<?php echo esc_attr( $migration_group_name ); ?>"
                                            class="button"
                                        >Migrate all <b><?php echo esc_attr( $friendly_migration_group_name ); ?></b> down</button>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <tr>
                                    <td colspan="2">No migrations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>    
            </form>
        </div>
        <?php
    }

    private static function sort_migrations(array &$migrations): void {
        foreach ($migrations as &$migration_group) {
            uasort($migration_group, function($a, $b) {                
                $datetime_a = strtotime($a['datetime'] ?? '');
                $datetime_b = strtotime($b['datetime'] ?? '');
                return $datetime_a - $datetime_b;
            });            
        }
    }

    private static function handle_migration_post() {
        if ( ! isset( $_POST['surreal_sync_migrations_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['surreal_sync_migrations_nonce_field'], 'surreal_sync_migrations_nonce' ) ) {
            return;
        }

        if ( isset($_POST['migration_up_all']) ||  isset($_POST['migration_down_all'])) {
            self::handle_group_migration(
                $_POST['migration_up_all'] ?? $_POST['migration_down_all'],
                (isset($_POST['migration_up_all'])) ? MigrationDirection::UP : MigrationDirection::DOWN
            );
        }

        // If a migration_up or migration_down was triggered, run it
        if ( isset( $_POST['migration_up'] ) || isset( $_POST['migration_down'] ) ) {
            $migration_class = isset( $_POST['migration_up'] ) 
                ? sanitize_text_field( $_POST['migration_up'] )
                : sanitize_text_field( $_POST['migration_down'] );

            // Double-check the class implements the interface
            if ( ! class_exists( $migration_class ) ) {
                add_settings_error( 'migration', 'migration_not_found', "Migration class $migration_class not found.", 'error' );
                return;
            }

            if ( ! in_array( IMigration::class, class_implements( $migration_class ) ?: [] ) ) {
                add_settings_error( 'migration', 'not_a_migration', "Class $migration_class does not implement IMigration.", 'error' );
                return;
            }

            // Instantiate your Surreal DB class (adjust to your needs)
            $db = SurrealDB::get_instance(); // Example of retrieving your Surreal DB instance

            // Run the appropriate migration
            if ( isset( $_POST['migration_up'] ) ) {
                $success = $migration_class::up( $db );
                if ( $success ) {
                    add_settings_error( 'migration', 'migration_up_success', "Successfully migrated up: $migration_class", 'updated' );
                } else {
                    add_settings_error( 'migration', 'migration_up_failure', "Migration up failed: $migration_class", 'error' );
                }
            } else {
                $success = $migration_class::down( $db );
                if ( $success ) {
                    add_settings_error( 'migration', 'migration_down_success', "Successfully migrated down: $migration_class", 'updated' );
                } else {
                    add_settings_error( 'migration', 'migration_down_failure', "Migration down failed: $migration_class", 'error' );
                }
            }
        }
    }

    protected static function handle_group_migration(string $group_name, MigrationDirection $direction) {
        $db = apply_filters('get_surreal_db_conn', null);

        $queries = self::get_all_migration_queries_of_group($group_name, $direction);
        ob_start();
        try {
            foreach($queries as $q): if (empty($q)) continue; ?>
                <?php $result = $db->query($q); ?>
                <pre style="white-space: pre-line;"><?php echo htmlentities($q); ?></pre>
                <pre style="white-space: no-wrap; background-color: #393939; color:white; padding: 20px;"><?php htmlentities(var_dump($result) ?? ''); ?></pre>
            <?php endforeach;
        } catch (\Throwable $e) { ?>
            <pre style="white-space: pre-line;"><?php echo htmlentities($q); ?></pre>
            <pre style="white-space: pre-line; background-color:red; color:white;"><?php echo htmlentities($e->getMessage()); ?></pre>
        <?php } finally {
            $db->close();
        }

        $result_html = ob_get_clean(); ?>
        <h2>Migration Output</h2>
        <div id="migration-restults" style="
            height:500px;
            overflow-y: scroll;
            padding: 30px;
            border: #c4c4c4 solid 1px;
            background: #fffbe1;">
            <?php echo $result_html; ?></div>
        <?php
    }

    protected static function get_all_migration_queries_of_group(string $group_name, MigrationDirection $direction): array {
        $migrations = apply_filters('get_surreal_graph_migrations', []);
        self::sort_migrations($migrations);

        $migration_group = $migrations[$group_name];
        
        if (empty($migration_group)) return [];

        $ordered_queries = [];

        foreach ($migration_group as $migration_name => $migration_data) {
            if (empty($migration_data[$direction->value])) continue;
            foreach ($migration_data[$direction->value] as $q)
            $ordered_queries[] = $q;
        }

        return $ordered_queries;
    }
}
