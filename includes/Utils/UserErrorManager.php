<?php

namespace Dazamate\SurrealGraphSync\Utils;

use Dazamate\SurrealGraphSync\Enum\MetaKeys;

class UserErrorManager {
    public static function load_hooks() {
        add_action('admin_notices', [__CLASS__, 'display_errors']);
    }

    public static function add(int $user_id, array $errors): void {
        $existing = get_user_meta($user_id, MetaKeys::SURREAL_SYNC_ERROR_META_KEY->value, true);

        if (!is_array($existing))  $existing = [];

        $merged = array_merge($existing, $errors);

        update_user_meta($user_id, MetaKeys::SURREAL_SYNC_ERROR_META_KEY->value, $merged);
    }
    
    public static function display_errors(): void {
        $screen = get_current_screen();

        // Make sure we're on a user page.
        if ( empty( $screen ) || ! in_array( $screen->id, ['profile', 'user-edit'], true ) ) {
            return;
        }
        
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : get_current_user_id();
        $errors = get_user_meta($user_id, MetaKeys::SURREAL_SYNC_ERROR_META_KEY->value, true);

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
        delete_user_meta($user_id, MetaKeys::SURREAL_SYNC_ERROR_META_KEY->value);
    }
}
