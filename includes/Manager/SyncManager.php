<?php

namespace Dazamate\SurrealGraphSync\Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class SyncManager {
    public static function load_hooks() {
        add_action('save_post', [__CLASS__, 'on_post_save'], 100, 3);
        add_action('before_delete_post', [__CLASS__, 'on_post_delete'], 10, 1);
    }

    public static function on_post_save(int $post_id, WP_Post $post, bool $update) {
        if ( wp_is_post_revision( $post_id ) || defined( DOING_AJAX ) ) {
            return;
        }

        // Build all the queries in order 
        $surreal_queries = [];

        $surreal_queries = apply_filters('surreal_graph_build_save_queries_' . $post->post_type, $surreal_queries, $post_id, $post);

        if (empty($surreal_queries)) return;

        do_action('surreal_graph_sync_save_' . $post->post_type, $surreal_queries, $post_id);
    }

    public static function on_post_delete(int $post_id) {
        $post = get_post( $post_id );

        $surreal_queries = [];

        $surreal_queries = apply_filters('surreal_graph_build_delete_queries_' . $post->post_type, $surreal_queries, $post_id, $post);

        do_action('surreal_graph_sync_delete_' . $post->post_type, $post_id, $post);
    }
}
