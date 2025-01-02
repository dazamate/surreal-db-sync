<?php

namespace Dazamate\SurrealGraphSync\Settings;

if ( ! defined( 'ABSPATH' ) ) exit;

class GraphInfoPage {
    static public function load_hooks() {
        add_action( 'admin_menu', [__CLASS__, 'surreal_sync_add_menu_item'] );
    }

    static public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Retrieve your database connection details if needed
        // (Assuming they're saved in an option called 'surreal_sync_options')
        $surreal_sync_options = get_option( 'surreal_sync_options', array() );
    
        // Example: create or fetch your graph DB connection object
        // $db = create_graph_db_connection( $surreal_sync_options );
    
        ?>
        <div class="wrap">
            <h1>Node Info</h1>
    
            <?php
            // 1) Check for 'recipe' post type
            if ( post_type_exists( 'recipe' ) ) :
                // Call your custom function/class to get info from the graph DB
                $recipe_info = \Dazamate\SurrealGraphSync\Node\RecipeNode::get_info( $db );
                ?>
                <h2>Recipe Node Info</h2>
                <pre><?php print_r( $recipe_info ); ?></pre>
            <?php else : ?>
                <p>The Recipe post type does not exist.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}