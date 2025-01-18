<?php

namespace Dazamate\SurrealGraphSync\Mapper;

use Dazamate\SurrealGraphSync\Mapper\ImagePostType;

if ( ! defined( 'ABSPATH' ) ) exit;

class PersonMapper {
    public static function register() {
        add_filter('surreal_graph_map_user', [__CLASS__, 'map'], 10, 2);
        add_filter('surreal_graph_node_name', [__CLASS__, 'get_node_type'], 10, 3);
    }

    public static function get_node_type($node_name, $post_type, $post_id) {
        if ($post_type === 'user') return 'person';
        return $node_name;
    }

    public static function map(array $mapped_data, int $post_id): array {
        $post = get_post($post_id);
        
        $mapped_data['name'] = [
            'value' => $post->post_title,
            'type' => 'string'
        ];

        $mapped_data['post_id'] = [
            'type' => 'number',
            'value' => $post_id
        ];

        return $mapped_data;
    }
}
