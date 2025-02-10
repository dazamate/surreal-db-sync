<?php

namespace Dazamate\SurrealGraphSync\Mapper\Entity;

if ( ! defined( 'ABSPATH' ) ) exit;

class PostMapper {
    public static function register() {
        add_filter('surreal_map_table_name', [__CLASS__, 'map_table_name'], 10, 3);
    }

    public static function map_table_name(string $node_name, string $post_type, int $post_id): string {
        if ($post_type === 'post') return 'article';
        return $node_name;
    }

    public static function map(array $mapped_data, int $post_id): array {
        $post = get_post($post_id);
        
        $mapped_data['title'] = [
            'value' => $post->post_title,
            'type' => 'string'
        ];
        
        $mapped_data['content'] = [
            'value' => $post->post_content,
            'type' => 'string'
        ];

        $mapped_data['post_id'] = [
            'type' => 'number',
            'value' => $post_id
        ];

        $mapped_data['created'] = [
            'type' => 'datetime',
            'value' => date('c', strtotime( $post->post_date_gmt ))
        ];

        $mapped_data['published'] = [
            'type' => 'datetime',
            'value' => date('c', strtotime( $post->post_date_gmt ))
        ];

        $mapped_data['update'] = [
            'type' => 'datetime',
            'value' => date('c', strtotime( $post->post_modified_gmt ?: $post->post_date_gmt ))
        ];

        $thumbnail_image_id = get_post_thumbnail_id($post_id);   
       
        if (!empty($thumbnail_image_id)) {
            $mapped_data['thumbnail_image'] = [
                'type' => 'record<image>',
                'value' => $thumbnail_image_id
            ];
        }

        return $mapped_data;
    }
}
