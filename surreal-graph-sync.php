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

use Dazamate\SurrealGraphSync\Container;
use Dazamate\SurrealGraphSync\Settings\AdminSettings;
use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Migration\InitialMigration;
use Dazamate\SurrealGraphSync\Migration\UserRelationsMigration;
use Dazamate\SurrealGraphSync\Service\PostSyncService;
use Dazamate\SurrealGraphSync\Service\UserSyncService;

use Dazamate\SurrealGraphSync\Mapper\ImageMapper;
use Dazamate\SurrealGraphSync\Mapper\PostMapper;
use Dazamate\SurrealGraphSync\Mapper\PersonMapper;

add_action( 'plugins_loaded', function() {
    AdminSettings::load_hooks();
    PostSyncService::load_hooks();
    UserSyncService::load_hooks();
});

add_action('init', function () {
    SyncManager::load_hooks();
    InitialMigration::load_hooks();
    UserRelationsMigration::load_hooks();
    Container::load_hooks();

    ImageMapper::register();
    PostMapper::register();
    PersonMapper::register();
});
