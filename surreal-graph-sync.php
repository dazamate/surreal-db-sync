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
use Dazamate\SurrealGraphSync\Manager\PostSyncManager;
use Dazamate\SurrealGraphSync\Manager\UserSyncManager;
use Dazamate\SurrealGraphSync\Manager\ImageSyncManager;
use Dazamate\SurrealGraphSync\Migration\InitialMigration;
use Dazamate\SurrealGraphSync\Migration\UserRelationsMigration;

use Dazamate\SurrealGraphSync\Service\SyncService;
use Dazamate\SurrealGraphSync\Service\PostSyncService;
use Dazamate\SurrealGraphSync\Service\UserSyncService;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Utils\UserErrorManager;

use Dazamate\SurrealGraphSync\Mapper\Entity\ImageMapper;
use Dazamate\SurrealGraphSync\Mapper\Entity\PostMapper;

use Dazamate\SurrealGraphSync\Mapper\User\UserMapper;
use Dazamate\SurrealGraphSync\Mapper\User\PersonMapper;

add_action( 'plugins_loaded', function() {
    Container::init_db_connection();
    Container::load_hooks();

    if ( ! Container::has_db_conn() ) return;

    AdminSettings::load_hooks();
    SyncService::load_hooks();
    PostSyncService::load_hooks();
    UserSyncService::load_hooks();    
});

add_action('init', function () {
    if ( ! Container::has_db_conn() ) return;

    ErrorManager::load_hooks();
    UserErrorManager::load_hooks();
    
    PostSyncManager::load_hooks();
    UserSyncManager::load_hooks();
    ImageSyncManager::load_hooks();

    ImageMapper::register();
    PostMapper::register();

    UserMapper::register();
    PersonMapper::register();
});