## Map Wordpress Post Type to Surreal Table Name ##
```php
    apply_filters('surreal_map_table_name', string $surreal_table_name, string $post_type, int $post_id): string;
```    

## Mapping post types ##

To map a post type to a surreal node, hook into this filter

```php
    apply_filters('surreal_graph_map_{post_type}', array $mapped_entity_data, int $post_id);
```

When a product is created/updated/ this will be called for the post type to get a mapping of surreal types.

```php
    add_filter('surreal_graph_map_service', $mapped_data, $post_id): array {
        $post = get_post($post_id);

        $mapped_data['title'] = [
            'value' => $post->post_title,
            'type' => 'string'
        ];

        $mapped_data['post_id'] = [
            'type' => 'number',
            'value' => $post_id
        ];

        $mapped_data['my_meta_key'] = [
            'type' => 'string',
            'value' => get_post_meta($post_id, 'my_meta_key', true)
        ];

        $mapped_data['another_record'] = [
            'type' => 'record<person>',
            'value' => 'person:98fsdfds987sdf90'
        ];

        $mapped_data['my_array'] = [
            'type' => 'array<string>',
            'value' => [
                'a', 'b', 'c'
            ]
        ];

        $mapped_data['my_object'] = [
            'type'  => 'object',
            'value' => [
                'object_field_a' => [
                    'type'  => 'number',
                    'value' => 42,
                ],                    
                'object_field_b'  => [
                    'type' => 'string',
                    'value' => 'my object field'
                ]
            ]
        ]

        // There may be mapping done ahead of you so merge your data
        return array_merge($product_map, $mapped_data);
    }

```

##Createing Relations##

To add graph relations, tap into the following filter which will be called during post save

```php
    apply_filters('surreal_graph_map_related', array $mappings, \WP_Post $post);
```

Append arrays of data to the mappings array to add relations

```php 
    add_filter('surreal_graph_map_related', $mappings, $post): array {
        if ($post->post_type !== 'order') return $mappings;

        $products = get_relevent_products($post);
        $user_record_id = 'customer:978fd9sdf987df';

        foreach($products as $product) {
            $mappings[] = [
                (required) 'from_record'    => $user_record_id,
                (required) 'to_record'      => get_post_meta($product->ID, 'surreal_id', true),
                (required) 'relation_table' => 'ordered',
                'unique'                    => false, // if multiple of the same relations is allowed or not
                'data'                      => [
                    'discounts'             => [
                        'type' => 'number',
                        'value' => 0.2
                    ],
                    'coupons'               => [
                        'type' => 'array',
                        'value' => [
                            ...
                        ]
                    ]
                ]
            ];
        }

        return $mappings;
    }
```

## User Mapping ##

To map a user to a Sureral table type, first map the user roles to a type with this filter.

The key of the array is the table name in Surreal, and the values are the wordpress role types that will be mapped to this table.

You can apply the role to multiple tables if you want.

```php
    add_filter('surreal_graph_user_role_map', function(array $user_role_map): array {
        $user_role_map['person'] = [
            'editor',
            'author',
            'contributor',
            'administrator'
        ];

        return $user_role_map;
    });
```

To map the user data you can follow the similar pattern as the entity types, hook into this filter.
But this filter wants the surreal type in the filter name

```php
    add_filter('surreal_graph_map_user_' . $surreal_user_type, function (array $mapped_data, \WP_User $user): array {
        $mapped_data['username'] =  [
            'type' => 'string',
            'value' => $user->user_login
        ];

        $mapped_data['email'] =  [
            'type' => 'string',
            'value' => $user->user_email
        ];

        $mapped_data['display_name'] =  [
            'type' => 'string',
            'value' => $user->display_name
        ];

        $mapped_data['user_id'] =  [
            'type' => 'number',
            'value' => $user->ID
        ];

        return $mapped_data;
    });
```

Mapping User realted data can be achieve with this filter

```php
    add_filter('surreal_graph_map_user_related', function(array $mapped_realtions, string $surreal_user_type, \WP_User $user): array {
        $friends_mapping = [
            'from_record'       => $user->ID,
            'to_record'         => get_another_user_id(),
            'relation_table'    => 'friends_with',
            'unique'            => true,
            'data'              => [
                'years' => [
                    'type' => 'number',
                    'value' => 15
                ]
            ]
        ];

        $mapped[] = $friends_mapping;

        return $mapped_realtions;
    });
```

##Migrations (Sidelined at the moment)##

Migrations should have the following data structure

```php
// Array keys are the migration group
[
    'migration_group_name' => [
        "initial_migration" => [
            "up"=> [
                "DEFINE TABLE mytable SCHEMAFULL;",
                "DEFINE FIELD title ON TABLE mytable TYPE string;",
                "UPDATE migration:state SET initial_migration.last_migration = 2025-01-02"
            ],
            "down" => [
                // down migrations array
            ],
            "datetime" => "2025-01-02" // used to order migrations
            'name' => 'initial migration'
        ]
        // .. add more migrations to groups that created the table schemas that might need changing
    ],
    'plugin_created_group_name' => [
        "next_migration" => [
            "up"=> [
                "DEFINE FIELD my_new_field ON TABLE mytable TYPE string;",
                "UPDATE migration:state SET next_migration.last_migration = 2025-01-03"
            ],
            "down" => [
                // down migrations array
            ],
            "datetime" => "2025-01-03", // used to order migrations
            'name' => 'plugin custom types migration'
        ],
        // .. add more migrations to groups that created the table schemas that might need changing
    ]
  ]
  ```