<?php

namespace Dazamate\SurrealGraphSync\Utils;

class ErrorManager {
    private const META_KEY = '_surreal_sync_error_messages';

    public static function load_hooks() {
        add_action('admin_notices', [__CLASS__, 'display_errors']);
    }

    public static function add(int $post_id, array $errors): void {
        $existing = get_post_meta($post_id, self::META_KEY, true);

        if (!is_array($existing))  $existing = [];

        $merged = array_merge($existing, $errors);

        update_post_meta($post_id, self::META_KEY, $merged);
    }
    
    public static function display_errors(): void {      
        $post_id = get_the_id();  
        $errors = get_post_meta($post_id, self::META_KEY, true);

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
    
    public static function clear(int $post_id): void {
        delete_post_meta($post_id, self::META_KEY);
    }
}
