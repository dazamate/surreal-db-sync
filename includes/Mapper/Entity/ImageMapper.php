<?php

namespace Dazamate\SurrealGraphSync\Mapper\Entity;

if ( ! defined( 'ABSPATH' ) ) exit;

class ImageMapper {
    public static function register() {
        add_filter('surreal_graph_map_image', [__CLASS__, 'map'], 10, 2);
        add_filter('surreal_map_table_name', [__CLASS__, 'map_table_name'], 10, 3);
    }

    public static function map_table_name(string $node_name, string $post_type, int $post_id): string {
        if ($post_type !== 'attachment') return $node_name;

        $post = get_post($post_id);

        if (strpos($post->post_mime_type, 'image/') === 0) return 'image';

        return $node_name;
    }

    public static function map(array $mapped_data, int $post_id): array {
        $post = get_post($post_id);
        
        $mapped_data['title'] = [
            'value' => $post->post_title,
            'type' => 'string'
        ];

        $mapped_data['post_id'] = [
            'type' => 'number',
            'value' => $post_id
        ];

        $mapped_data['src'] = [
            'type' => 'string',
            'value' => ''
        ];

        $mapped_data['mime'] = [
            'type' => 'string',
            'value' => $post->post_mime_type
        ];

        $mapped_data['src'] = [
            'type' => 'string',
            'value' => wp_get_attachment_url( $post_id ) ?: null
        ];

        return $mapped_data;
    }
}