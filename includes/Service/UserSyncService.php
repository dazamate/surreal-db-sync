<?php

namespace Dazamate\SurrealGraphSync\Service;

use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;
use Dazamate\SurrealGraphSync\Utils\ErrorManager;
use Dazamate\SurrealGraphSync\Utils\Inputs;

if ( ! defined( 'ABSPATH' ) ) exit;

class UserSyncService {
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
     *
     * @param int      $user_id        The ID of the user being saved.
     * @param \WP_User $old_user_data  (optional) The old user object before the update.
     */
    public static function on_user_save( int $user_id, ?\WP_User $old_user_data ) {
        // Avoid hooking into auto-saves or AJAX if needed (similar to posts).
        // For example:
        // if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        //     return;
        // }

        ErrorManager::clear( $user_id ); // Clears any existing errors related to this user (optional).

        // Gather user data. 
        // If you want a separate 'UserMapper::map()', create that class and method
        // similarly to your 'PostMapper'. For now, we build a basic $mapped_data array:

        $mapped_data = [];

        // Basic example: If you want to map user roles to a SurrealDB "type" field:
        $user        = get_userdata( $user_id );
        $roles       = (array) $user->roles;
        $first_role  = !empty( $roles ) ? $roles[0] : 'subscriber';

        // Let plugins/themes adjust the data. 
        // e.g. surreal_graph_map_user => pass $mapped_data, $user_id
        $mapped_data = apply_filters( 'surreal_graph_map_user', [], $user );

        // Validate the mapped data
        $errors = [];
        if ( ! MappingDataValidator::validate( $mapped_data, $errors ) ) {
            ErrorManager::add( $user_id, array_map(
                fn( $e ) => sprintf( "Surreal DB user mapping error: %s", $e ),
                $errors
            ) );
            return;
        }

        // Build "related" data: relationships that might exist (groups, custom roles, etc.)
        $related_data_mappings = apply_filters( 'surreal_graph_build_relate_user', [], $user_id );

        foreach ( $related_data_mappings as $relate_data ) {
            if ( ! RelatedMappingDataValidator::validate( $relate_data, $errors ) ) {
                ErrorManager::add( $user_id, array_map(
                    fn( $e ) => sprintf( "Surreal DB user related data mapping error: %s", $e ),
                    $errors
                ) );
            }
        }

        // If validation errors exist, bail.
        if ( ! empty( $errors ) ) {
            return;
        }

        // Parse any record IDs (if your Surreal logic is the same as for posts).
        foreach ( $related_data_mappings as &$relate_data ) {
            $relate_data['from_record'] = Inputs::parse_record_id( $relate_data['from_record'] ?? '' );
            $relate_data['to_record']   = Inputs::parse_record_id( $relate_data['to_record'] ?? '' );
        }

        // Finally, call an action that does the actual sync.
        // This is analogous to how your SyncManager does do_action('surreal_sync_post', ...).
        do_action( 'surreal_sync_user', $user_id, $mapped_data, $related_data_mappings );
    }

    /**
     * Handle user deletion in SurrealDB.
     *
     * @param int $user_id
     */
    public static function on_user_delete( int $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        // Provide an action similar to posts, so any Surreal sync logic can delete the user there
        do_action( 'surreal_graph_delete_user', $user_id, $user );
    }
}
