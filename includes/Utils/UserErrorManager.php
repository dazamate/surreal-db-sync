<?php

namespace Dazamate\SurrealGraphSync\Utils;

class UserErrorManager {
    private const META_KEY = '_surreal_sync_error_messages';

    public static function load_hooks() {
        add_action('admin_notices', [__CLASS__, 'display_errors']);
    }

    public static function add(int $user_id, array $errors): void {
        $existing = get_user_meta($user_id, self::META_KEY, true);

        if (!is_array($existing))  $existing = [];

        $merged = array_merge($existing, $errors);

        update_user_meta($user_id, self::META_KEY, $merged);
    }
    
    public static function display_errors(): void {      
        $user_id = get_current_user_id();  
        $errors = get_user_meta($user_id, self::META_KEY, true);

        if (!empty($errors)) {
            foreach ($errors as $message) {
                $safe_message = esc_html($message);

                echo sprintf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    $safe_message
                );
            }
        }
    }
    
    public static function clear(int $user_id): void {
        delete_user_meta($user_id, self::META_KEY);
    }
}
