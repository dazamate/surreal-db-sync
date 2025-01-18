<?php 

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Manager\SyncManager;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class UserSyncService {
    public static function load_hooks() {
        // add_action('surreal_sync_user', [__CLASS__, 'sync_post'], 10, 3);
        /// add_action('surreal_delete_user', [__CLASS__, 'delete_post'], 10, 1);
    }
}
