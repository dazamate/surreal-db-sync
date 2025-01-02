<?php

namespace Dazamate\SurrealGraphSync\Settings;

if ( ! defined( 'ABSPATH' ) ) exit;

class MigrationsPage {
    static public function load_hooks() {
        add_action( 'admin_menu', [__CLASS__, 'surreal_sync_add_menu_item'] );
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

        // echo '<pre>';
        // var_dump($migrations); 
        // echo '</pre>';
        // exit;

        // Handle form submissions (Up/Down migrations)
        self::handle_migration_post();

        ?>
        <div class="wrap">
            <h1>Surreal Migrations</h1>

            <form method="POST">
                <?php wp_nonce_field( 'surreal_sync_migrations_nonce', 'surreal_sync_migrations_nonce_field' ); ?>

                <?php foreach ($migrations as $migration_group_name => $migration_group_data): ?>
                    <?php $friendly_migration_group_name = str_ireplace('_', ' ', $migration_group_name);?>
                    <h2><?php echo $friendly_migration_group_name; ?></h2>

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
                                    <?php $friendly_migration_name = str_ireplace('_', ' ', $migration_name);?>
                                    <tr>
                                        <td><?php echo esc_html( $friendly_migration_name ); ?></td>
                                        <td>
                                            <button type="submit" name="migration_up" value="<?php echo esc_attr( $migration_name ); ?>" class="button">Migrate up</button>
                                            <button type="submit" name="migration_down" value="<?php echo esc_attr( $migration_name ); ?>" class="button">Migrate down</button>
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
                                        <button type="submit" name="migration_up_all" value="<?php echo esc_attr( $migration_name ); ?>" class="button">Migrate all <b><?php echo esc_attr( $friendly_migration_name ); ?></b> up</button>
                                        <button type="submit" name="migration_down_all" value="<?php echo esc_attr( $migration_name ); ?>" class="button">Migrate all <b><?php echo esc_attr( $friendly_migration_name ); ?></b> down</button>
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
            //echo '<pre>' . print_r($migration_group, 1) . '</pre>';
            uasort($migration_group, function($a, $b) {
                
                $datetime_a = strtotime($a['datetime'] ?? '');
                $datetime_b = strtotime($b['datetime'] ?? '');
               // var_dump([$a['datetime'], $b['datetime']]);
                //var_dump([$datetime_a, $datetime_b]);
                return $datetime_a - $datetime_b;
            });
            
        }
    }

    private static function handle_migration_post() {
        if ( ! isset( $_POST['surreal_sync_migrations_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['surreal_sync_migrations_nonce_field'], 'surreal_sync_migrations_nonce' ) ) {
            return; // Security check
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
}
