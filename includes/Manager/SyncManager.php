<?php

namespace Dazamate\SurrealGraphSync\Manager;

use Dazamate\SurrealGraphSync\Mapper\PostMapper;
use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Utils\Inputs;

if ( ! defined( 'ABSPATH' ) ) exit;

class SyncManager {
    const SURREAL_DB_ID_META_KEY = 'surreal_id';

    public static function load_hooks() {
        add_action('save_post', [__CLASS__, 'on_post_save'], 100, 3);
        add_action('before_delete_post', [__CLASS__, 'on_post_delete'], 10, 1);

        add_action('add_attachment', [__CLASS__, 'on_attatchemnt_change']);
        add_action('edit_attachment', [__CLASS__, 'on_attatchemnt_change']);
        add_action('delete_attachment', [__CLASS__, 'on_attatchemnt_delete']);

        add_action('post_submitbox_misc_actions', [__CLASS__, 'render_surreal_id_info']);
        add_filter('admin_post_thumbnail_html', [__CLASS__, 'render_surreal_id_info_in_image_ui'], 10, 2);
        add_filter('attachment_fields_to_edit', [__CLASS__, 'render_surreal_id_attatchment_edit'], 10, 2);
    }

    public static function render_surreal_id_info() {
        echo '<div class="misc-pub-section misc-pub-db-id-info">';
            echo 'Surreal ID: <br>';
            printf('<span><b>%s</b></span>', get_post_meta(get_the_id(), self::SURREAL_DB_ID_META_KEY, true));
        echo '</div>';
    }

    public static function render_surreal_id_info_in_image_ui($content, $post_id) {
        $surreal_id = get_post_meta($post_id, self::SURREAL_DB_ID_META_KEY, true);

        $html = '<div class="surreal-image-info" style="margin-top: 10px;">';
        $html .= 'Surreal ID: <strong>' . esc_html($surreal_id) . '</strong>';
        $html .= '</div>';
    
        return $content . $html;
    }

    public static function render_surreal_id_attatchment_edit($form_fields, $post) {
        $surreal_id = get_post_meta($post->ID, self::SURREAL_DB_ID_META_KEY, true);

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

        ErrorManager::clear($post_id);

        // Allways map the generic post data, downstream filters can add/remove generic data
        $mapped_data = PostMapper::map([], $post_id);
        $mapped_data = apply_filters('surreal_graph_map_' . $post->post_type, $mapped_data, $post_id);

        // validate the mapped data
        $errors = [];
        
        if (!MappingDataValidator::validate($mapped_data, $errors)) {
            ErrorManager::add($post_id, array_map(fn($e) => sprintf("Surreal DB mapping error: %s", $e), $errors));
            return;
        }

        $related_data_mappings = apply_filters('surreal_graph_build_relate_' . $post->post_type, [], $post_id);
        
        foreach ($related_data_mappings as $related_data) {
            if (!RelatedMappingDataValidator::validate($relate_data, $errors)) {
                ErrorManager::add($post_id, array_map(fn($e) => sprintf("Surreal DB related data mapping error: %s", $e), $errors));
            }
        }

        if (!empty($errors)) return;

        foreach ($related_data_mappings as &$related_data) {
            $relate_data['from_record'] = Inputs::parse_record_id($relate_data['from_record']);
            $relate_data['to_record'] = Inputs::parse_record_id($relate_data['to_record']);
        }

        do_action('surreal_sync_post', $post_id, $post->post_type, $mapped_data, $relate_data);
    }

    public static function on_post_delete(int $post_id) {
        $post = get_post( $post_id );

        do_action('surreal_graph_delete_' . $post->post_type, $post_id, $post);
    }

    public static function on_attatchemnt_change(int $post_id) {
        $post = get_post($post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {
            $mapped_data = apply_filters('surreal_graph_map_image', [], $post_id);       
            do_action('surreal_sync_post', $post_id, $post->post_type, $mapped_data);
        }
    }

    public static function on_attatchemnt_delete(int $post_id) {
        $post = get_post($post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) {
            do_action('surreal_delete_post', $post);
        }
    }
}
