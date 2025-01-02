<?php

namespace Dazamate\SurrealGraphSync\Settings;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdminSettings {
    const MENU_SLUG = 'surreal-sync-settings';
    static private string $options_key = 'surreal_sync_options';

    static public function load_hooks() {
        add_action( 'admin_menu', [__CLASS__, 'surreal_sync_add_menu_item'] );
    }

    static public function get_settings(): array {
        return get_option( self::$options_key ) ?: [];
    }

    static public function surreal_sync_add_menu_item() {
        add_menu_page(
            page_title: 'Surreal Sync Settings',
            menu_title: 'Surreal Sync Settings',
            capability: 'manage_options', 
            menu_slug: self::MENU_SLUG,
            callback: [__CLASS__, 'surreal_sync_render_page'],
            icon_url: 'dashicons-update',
            position: null
        );

        add_submenu_page(
            parent_slug: self::MENU_SLUG,
            page_title: 'Node Info',
            menu_title: 'Node Info',
            capability:'manage_options',
            menu_slug: 'surreal-sync-node-info',
            callback: [GraphInfoPage::class, 'render'],
            position: null
        );

        add_submenu_page(
            parent_slug: self::MENU_SLUG,
            page_title: 'Migrations',
            menu_title: 'Migrations',
            capability: 'manage_options',
            menu_slug: 'surreal-sync-migrations',
            callback: [MigrationsPage::class, 'render'],
            position: null
        );
    }

    static public function surreal_sync_render_page() {
        // Must have proper capability
        if ( ! current_user_can( 'manage_options' ) ) return;
    
        // Retrieve existing options from the database, if any
        $surreal_sync_options = get_option( self::$options_key );
    
        // Defaults for each setting if it's not yet stored
        $defaults = array(
            'db_address'   => '',
            'db_port'      => '',
            'db_username'  => '',
            'db_password'  => '',
            'db_namespace' => '',
            'db_name'  => '',
        );
    
        // Merge stored options with defaults
        $surreal_sync_options = wp_parse_args( $surreal_sync_options, $defaults );
    
        // If the form has been submitted, process and save the input
        if ( isset( $_POST['surreal_sync_submit'] ) && check_admin_referer( 'surreal_sync_settings_save', 'surreal_sync_nonce' ) ) {
            $surreal_sync_options['db_address']    = sanitize_text_field( $_POST['db_address'] );
            $surreal_sync_options['db_port']       = sanitize_text_field( $_POST['db_port'] );
            $surreal_sync_options['db_username']   = sanitize_text_field( $_POST['db_username'] );
            $surreal_sync_options['db_password']   = sanitize_text_field( $_POST['db_password'] );
            $surreal_sync_options['db_namespace']  = sanitize_text_field( $_POST['db_namespace'] );
            $surreal_sync_options['db_name']       = sanitize_text_field( $_POST['db_name'] );
    
            // Update the options in the database
            update_option( 'surreal_sync_options', $surreal_sync_options );

            ?>
            <div class="updated notice is-dismissible">
                <p>Surreal sync settings have been saved.</p>
            </div>
            <?php
        }
    
        // Render the form
        ?>
        <div class="wrap">
            <h1>Surreal Sync Settings</h1>
    
            <form method="post" action="">
                <?php wp_nonce_field( 'surreal_sync_settings_save', 'surreal_sync_nonce' ); ?>
    
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="address">Address</label></th>
                        <td>
                            <input type="text" 
                                   id="db_address" 
                                   name="db_address" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_address'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="port">Port</label></th>
                        <td>
                            <input type="text" 
                                   id="db_port" 
                                   name="db_port" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_port'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="username">Username</label></th>
                        <td>
                            <input type="text" 
                                   id="db_username" 
                                   name="db_username" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_username'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="password">Password</label></th>
                        <td>
                            <input type="password" 
                                   id="db_password" 
                                   name="db_password" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_password'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="namespace">Namespace</label></th>
                        <td>
                            <input type="text" 
                                   id="db_amespace" 
                                   name="db_namespace" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_namespace'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="database">Database Name</label></th>
                        <td>
                            <input type="text" 
                                   id="db_name" 
                                   name="db_name" 
                                   value="<?php echo esc_attr( $surreal_sync_options['db_name'] ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
    
                <?php submit_button( 'Save Settings', 'primary', 'surreal_sync_submit' ); ?>
            </form>
        </div>
        <?php
    }
};
