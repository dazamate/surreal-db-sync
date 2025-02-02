<?php

namespace Dazamate\SurrealGraphSync\Mapper\User;

if ( ! defined( 'ABSPATH' ) ) exit;

class PersonMapper {
    public static function register() {
        add_filter('surreal_graph_map_user_person', [__CLASS__, 'map'], 10, 2);
        add_filter('surreal_graph_user_role_map', [__CLASS__, 'map_user_roles_to_surreal_type'], 10, 1);
    }

    public static function map_user_roles_to_surreal_type(array $user_role_map): array {
        $user_role_map['person'] = [
            'editor',
            'author',
            'contributor',
            'administrator'
        ];

        return $user_role_map;
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

        $mapped_data['user_id'] =  [
            'type' => 'number',
            'value' => $user->ID
        ];

        return $mapped_data;
    }
};
