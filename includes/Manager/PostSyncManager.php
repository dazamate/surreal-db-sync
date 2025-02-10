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
        if ( wp_is_post_revision( $post_id ) || defined( '\DOING_AJAX' ) ) {
            return;
        }

        // Map the post type to a surreal table name (entity)
        $surreal_table_name = apply_filters('surreal_map_table_name', '', $post->post_type, $post_id);

        // Allways map the generic post data, downstream filters can add/remove generic data
        $mapped_entity_data = PostMapper::map([], $post_id);
        $mapped_entity_data = apply_filters('surreal_graph_map_' . $post->post_type, $mapped_entity_data, $post_id);

        $related_data_mappings = apply_filters('surreal_graph_map_related', [], $post);        

        do_action('surreal_sync_post', $post_id, $surreal_table_name, $mapped_entity_data, $related_data_mappings);
    }

    public static function on_post_delete(int $post_id) {
        $surreal_record_id = get_post_meta($post_id, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true);
    
        // Make sure we have data
        if ( $surreal_record_id === false ) return;

        do_action('surreal_graph_delete_record', $surreal_record_id);        
    }

    public static function on_attatchemnt_change(int $post_id) {
        $post = get_post($post_id);
        $surreal_table_name = apply_filters('surreal_map_table_name', 'image', $post->post_type, $post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {
            $mapped_data = apply_filters('surreal_graph_map_image', [], $post_id);       
            do_action('surreal_sync_post', $post_id, $surreal_table_name, $mapped_data);
        }
    }

    public static function map_surreal_table_name(string $surreal_table_name, string $post_type, int $post_id) {
        if ($post_type === ImagePostType::POST_TYPE)  {
            if (strpos($post->post_mime_type, 'image/') === 0) {
                return 'image';
            }
        }
        return $surreal_table_name;
    }

    public static function on_attatchemnt_delete(int $post_id) {
        $post = get_post($post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {     
            $surreal_record_id = get_post_meta($post_id, MetaKeys::SURREAL_DB_RECORD_ID_META_KEY->value, true);
        
            // Make sure we have data
            if ( $surreal_record_id === false ) return;

            do_action('surreal_delete_record', $surreal_record_id);
        }
    }
}
