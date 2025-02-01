<?php

namespace Dazamate\SurrealGraphSync\Mapper\User;

if ( ! defined( 'ABSPATH' ) ) exit;

class PersonMapper {
    public static function register() {
        add_filter('surreal_graph_map_user_person', [__CLASS__, 'map'], 10, 2);
    }

    public static function map(array $mapped_data, \WP_User $user): array {
        $mapped_data['username'] = [
            'type' => 'string',
            'value' => $user->user_login
        ];

        $mapped_data['email'] = [
            'type' => 'string',
            'value' => $user->user_email
        ];

        $mapped_data['display_name'] = [
            'type' => 'string',
            'value' => $user->display_name
        ];
    }
};
