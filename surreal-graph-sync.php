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
use Dazamate\SurrealGraphSync\Manager\UserSyncManager;
use Dazamate\SurrealGraphSync\Migration\InitialMigration;
use Dazamate\SurrealGraphSync\Migration\UserRelationsMigration;
use Dazamate\SurrealGraphSync\Service\PostSyncService;
use Dazamate\SurrealGraphSync\Service\UserSyncService;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Utils\UserErrorManager;

use Dazamate\SurrealGraphSync\Mapper\Entity\ImageMapper;
use Dazamate\SurrealGraphSync\Mapper\Entity\PostMapper;

use Dazamate\SurrealGraphSync\Mapper\User\UserMapper;
use Dazamate\SurrealGraphSync\Mapper\User\PersonMapper;

add_action( 'plugins_loaded', function() {
    AdminSettings::load_hooks();
    PostSyncService::load_hooks();
    UserSyncService::load_hooks();

    // Test user realtions
    //add_action('')
    
});

add_action('init', function () {    
    Container::load_hooks();
    ErrorManager::load_hooks();
    UserErrorManager::load_hooks();
    
    SyncManager::load_hooks();
    UserSyncManager::load_hooks();

    ImageMapper::register();
    PostMapper::register();

    UserMapper::register();
    PersonMapper::register();
});
