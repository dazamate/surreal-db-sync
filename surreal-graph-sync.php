<?php
/*
 * Plugin Name:       Surreal Graph Sync
 * Description:       Convert wordpress data to a Surreal DB graph data
 * Version:           1.0
 * Requires PHP:      8.4
 * Author:            Dale Woods
 * Author URI:        https://dalewoods.me
 * Requires Plugins:  
 */

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use Dazamate\SurrealGraphSync\Settings\AdminSettings;
use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Migration\InitialMigration;

add_action( 'plugins_loaded', function() {
    AdminSettings::load_hooks();
});

add_action('init', function() {
    if (is_admin()) return;
    $post_id = 49;
    //$payload = apply_filters('surreal_graph_map_recipe', $payload = [], $post_id);
    // echo '<pre>';
    // var_dump($payload); 
    // echo '</pre>';
    // exit;
});

add_action('admin_init', function () {
    SyncManager::load_hooks();    
    InitialMigration::load_hooks();

    $settings = AdminSettings::get_settings();

    $db = new \Surreal\Surreal();
    
    // try {
    //     $db->connect($settings['db_address'] . ':' . $settings['db_port'], [
    //         "namespace"     => $settings['db_namespace'],
    //         "database"      => $settings['db_name']
    //     ]);

    //     $token = $db->signin([
    //         "user" => $settings['db_username'],
    //         "pass" => $settings['db_password']
    //     ]);

        
    //     var_dump($token);
    // } catch(\Throwable $e) {
    //     var_dump($e->getMessage()); 
    // //    exit;
    // }    
    
    // We want to authenticate as a root user.
    

    //exit;
});
