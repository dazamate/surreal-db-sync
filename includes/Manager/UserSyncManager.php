<?php

namespace Dazamate\SurrealGraphSync\Manager;

use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Utils\Inputs;

if ( ! defined( 'ABSPATH' ) ) exit;

class UserSyncManager {
    const SURREAL_DB_ID_META_KEY = 'surreal_id';

    /**
     * Attach all hooks required for user sync.
     */
    public static function load_hooks() {
        // Runs after a user is created (i.e., registration) in WP Admin or front end
        add_action( 'user_register', [ __CLASS__, 'on_user_save' ], 10, 1 );

        // Runs when a user is updated (e.g., profile_update). 
        // Hook signature: do_action( 'profile_update', $user_id, $old_user_data );
        add_action( 'profile_update', [ __CLASS__, 'on_user_save' ], 10, 2 );

        // Runs just before a user is deleted. 
        add_action( 'delete_user', [ __CLASS__, 'on_user_delete' ], 10, 1 );
    }

    /**
     * Handle creating or updating a user record in SurrealDB.
     */
    public static function on_user_save( int $user_id, ?\WP_User $old_user_data ) {
        // Avoid hooking into auto-saves or AJAX if needed
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        ErrorManager::clear( $user_id );
      
        $user = get_userdata( $user_id );

        if ( ! ( $user instanceof \WP_User ) ) {
            ErrorManager::add( $user_id, sprintf( "Surreal DB user mapping error: Unable to fetch user id %d", $user_id ) );
            return;
        }

        $user_role_map = [];

        $user_role_map['person'] =  [
            'editor',
            'author',
            'contributor',
            'administrator'
        ];

        $user_role_map['user'] = [
            'subscriber'
        ];

        $user_role_map = apply_filters('surreal_graph_user_role_map', $user_role_map);

        foreach ($user_role_map as $surreal_user_type => $wordpress_user_types) {
            // If the user has the role that is mapped to this surreal type, then process with mapping the user as this type
            if ( count( array_intersect( $user->roles, $wordpress_types ) ) > 0 ) {
                $mapped_user_data = apply_filters('surreal_graph_map_user_' . $surreal_user_type, $mapped_data, $user );

                // Validate the mapped data
                $errors = [];
                if ( ! MappingDataValidator::validate( $mapped_user_data, $errors ) ) {
                    ErrorManager::add( $user_id, array_map(
                        fn( $e ) => sprintf( "Surreal DB user mapping error: %s", $e ),
                        $errors
                    ) );
                    
                    return;
                }
                
                $related_data_mappings = apply_filters('surreal_graph_map_user_related', [], $surreal_user_type, $user);

                // Validated mapped related data
                foreach ( $related_data_mappings as $mapped_related_data ) {
                    if ( ! RelatedMappingDataValidator::validate( $mapped_related_data, $errors ) ) {
                        ErrorManager::add( $user_id, array_map(
                            fn( $e ) => sprintf( "Surreal DB user type [%s] related data mapping error: %s", $e ),
                            $surreal_user_type,
                            $errors
                        ) );
                    }
                }

                // If validation errors exist, bail.
                if ( ! empty( $errors ) ) {
                    return;
                }

                // Parse any record IDs (if your Surreal logic is the same as for posts).
                foreach ( $related_data_mappings as &$mapped_related_data ) {
                    $relate_data['from_record'] = Inputs::parse_record_id( $mapped_related_data['from_record'] ?? '' );
                    $relate_data['to_record']   = Inputs::parse_record_id( $mapped_related_data['to_record'] ?? '' );
                }

                // parse the records
                do_action('surreal_sync_user', $post_id, $post->post_type, $mapped_data, $related_data_mappings);
            }
        }
    }

    /**
     * Handle user deletion in SurrealDB.
     *
     * @param int $user_id
     */
    public static function on_user_delete( $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        // Provide an action similar to posts, so any Surreal sync logic can delete the user there
        do_action( 'surreal_graph_delete_user', $user_id, $user );
    }
}
