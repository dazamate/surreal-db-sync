<?php 

namespace Dazamate\SurrealGraphSync;

use Dazamate\SurrealGraphSync\Settings\AdminSettings;
use Surreal\Surreal;

class Container {
    static private bool $_connetion = false;
    static private Surreal $_db;

    static public function has_db_conn(): bool {
        return self::$_connetion;
    }

    static public function load_hooks() {
        add_filter('get_surreal_db_conn', [__CLASS__, 'get_surreal_db_conn']);
        add_action('admin_init', [__CLASS__, 'check_db_conn']);
    }

    static public function check_db_conn() {
        if ( ! self::$_connetion ) self::display_no_db_error();
    }

    static public function display_no_db_error() {
        add_action('admin_notices', function() {
            echo sprintf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                'Couldn\'t connect to Surreal DB'
            );
        });
    }

    static public function init_db_connection(): bool {
        $settings = AdminSettings::get_settings();
        $db = new Surreal();
    
        try {
            $db->connect($settings['db_address'] . ':' . $settings['db_port'], [
                "namespace"     => $settings['db_namespace'],
                "database"      => $settings['db_name']
            ]);

            $token = $db->signin([
                "user" => $settings['db_username'],
                "pass" => $settings['db_password']
            ]);

            self::$_db = $db;
            self::$_connetion = true;
        } catch(\Throwable $e) {
            error_log($e->getMessage());
            return false;
        }

        return true;
    }

    static public function get_surreal_db_conn(): ?\Surreal\Surreal {
        return self::$_db;
    }
}