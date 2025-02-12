<?php

namespace Dazamate\SurrealGraphSync\Manager;

use Dazamate\SurrealGraphSync\Mapper\Entity\PostMapper;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\PostType\ImagePostType;
use Dazamate\SurrealGraphSync\Enum\MetaKeys;

class PostSyncManager {
    public static function load_hooks() {
        add_action('save_post', [__CLASS__, 'on_post_save'], 100, 3);
        add_action('before_delete_post', [__CLASS__, 'on_post_delete'], 10, 1);
        add_action('trashed_post', [__CLASS__, 'on_post_trash'], 10, 1);
        add_action('untrash_post', [__CLASS__, 'on_post_untrash'], 10, 1);

        add_action('add_attachment', [__CLASS__, 'on_attatchemnt_change']);
        add_action('edit_attachment', [__CLASS__, 'on_attatchemnt_change']);
        add_action('delete_attachment', [__CLASS__, 'on_attatchemnt_delete']);

        add_action('post_submitbox_misc_actions', [__CLASS__, 'render_surreal_id_info']);
        add_filter('admin_post_thumbnail_html', [__CLASS__, 'render_surreal_id_info_in_image_ui'], 10, 2);
        add_filter('attachment_fields_to_edit', [__CLASS__, 'render_surreal_id_attatchment_edit'], 10, 2);

        add_filter('surreal_map_table_name', [__CLASS__, 'map_surreal_table_name'], 10, 3);
    }

    public static function render_surreal_id_info() {
        echo '<div class="misc-pub-section misc-pub-db-id-info">';
            echo 'Surreal ID: <br>';
            printf('<span><b>%s</b></span>', get_post_meta(get_the_id(), MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true));
        echo '</div>';
    }

    public static function render_surreal_id_info_in_image_ui($content, $post_id) {
        // Only output this markup when editing an attachment (the image details page).
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( ! isset( $screen->post_type ) || 'attachment' !== $screen->post_type ) {
                return $content;
            }
        }

        $surreal_id = get_post_meta($post_id, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true);

        $html = '<div class="surreal-image-info" style="margin-top: 10px;">';
        $html .= 'Surreal ID: <strong>' . esc_html($surreal_id) . '</strong>';
        $html .= '</div>';
    
        return $content . $html;
    }

    public static function render_surreal_id_attatchment_edit($form_fields, $post) {
        $surreal_id = get_post_meta($post->ID, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true);

        $form_fields['surreal_db_id_field'] = [
            'label' => 'Surreal ID',
            'input' => 'html',
            'html'  => sprintf(
                '<input type="text" readonly style="width:100%%;" value="%s" />',
                esc_attr($surreal_id ?? '')
            )
        ];
    
        return $form_fields;
    }

    public static function on_post_save(int $post_id, \WP_Post $post, bool $update) {
        if ( 'draft' === $post->post_status || wp_is_post_revision( $post_id ) || defined( 'DOING_AJAX' ) ) return;
        
        // Ignore trying to sync drafts
        $ignore_post_states = [
            'draft',
            'auto-draft'
        ];

        if (in_array($post->post_status, $ignore_post_states)) return;

        // Map the post type to a surreal table name (entity)
        $surreal_table_name = apply_filters('surreal_map_table_name', '', $post->post_type, $post_id);

        // Allways map the generic post data, downstream filters can add/remove generic data
        $mapped_entity_data = PostMapper::map([], $post_id);
        $mapped_entity_data = apply_filters('surreal_graph_map_' . $post->post_type, $mapped_entity_data, $post_id);

        //echo '<pre>'; var_dump($related_data_mappings); exit;

        do_action('surreal_sync_post', $post, $surreal_table_name, $mapped_entity_data);
    }

    public static function on_post_delete(int $post_id) {
        self::delete_surreal_record_by_post_id($post_id);   
    }

    public static function on_post_trash(int $post_id) {
        self::delete_surreal_record_by_post_id($post_id);        
    }

    public static function on_post_untrash(int $post_id) {
        $post = get_post($post_id);

        if ( ! ( $post instanceof \WP_Post ) ) return;
        
        self::on_post_save($post_id, $post, true);
    }

    public static function on_attatchemnt_change(int $post_id) {
        $post = get_post($post_id);
        $surreal_table_name = apply_filters('surreal_map_table_name', 'image', $post->post_type, $post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {
            $mapped_data = apply_filters('surreal_graph_map_image', [], $post_id);       
            do_action('surreal_sync_post', $post, $surreal_table_name, $mapped_data);
        }
    }

    public static function map_surreal_table_name(string $surreal_table_name, string $post_type, int $post_id) {
        if ($post_type === ImagePostType::POST_TYPE)  {
            if (strpos($post_type, 'image/') === 0) {
                return 'image';
            }
        }
        return $surreal_table_name;
    }

    public static function on_attatchemnt_delete(int $post_id) {
        $post = get_post($post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {
            self::delete_surreal_record_by_post_id($post_id);
        }
    }

    private static function delete_surreal_record_by_post_id(int $post_id) {
        $surreal_record_id = get_post_meta($post_id, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true);

        if (empty($surreal_record_id)) return;

        do_action('surreal_delete_record', $surreal_record_id);

        // delete the meta key incase it's just going to trash
        delete_post_meta($post_id, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value);
    }
}
