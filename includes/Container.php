<?php 

namespace Dazamate\SurrealGraphSync;

use Dazamate\SurrealGraphSync\Settings\AdminSettings;

class Container {
    static public function load_hooks() {
        add_filter('get_surreal_db_conn', [__CLASS__, 'get_surreal_db_conn']);
    }

    static public function get_surreal_db_conn(): ?\Surreal\Surreal {
        $settings = AdminSettings::get_settings();

        $db = new \Surreal\Surreal();
    
        try {
            $db->connect($settings['db_address'] . ':' . $settings['db_port'], [
                "namespace"     => $settings['db_namespace'],
                "database"      => $settings['db_name']
            ]);

            $token = $db->signin([
                "user" => $settings['db_username'],
                "pass" => $settings['db_password']
            ]);

            return $db;
        } catch(\Throwable $e) {
            error_log($e->get_message());
            return null;
        }    
    }
}