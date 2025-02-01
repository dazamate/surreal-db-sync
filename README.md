## Map Wordpress Post Type to Surreal Table Name ##
```php
    apply_filters('surreal_map_table_name', string $surreal_table_name, string $post_type, int $post_id): string;
```    

## Mapping post types ##

To map a post type to a surreal node, hook into this filter

```php
    apply_filters('surreal_graph_map_{post_type}', array $mapped_data, int $post_id);
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
    apply_filters('surreal_graph_map_related', array $mappings, \WP_Post);
```

Append arrays of data to the mappings array to add relations

```php 
    add_filter('surreal_graph_map_related', $mappings, $post): array {
        if ($post->post_type !== 'order') return $mappings;

        $products = get_relevent_products($post);
        $user_record_id = 'customer:978fd9sdf987df';

        foreach($products as $product) {
            $mappings[] = [
                (required) 'from_record'   => $user_record_id,
                (required) 'to_record'     => get_post_meta($product->ID, 'surreal_id', true),
                (required) 'relation_table' => 'ordered',
                'unique'        => false,
                'data'          => [
                    'discounts' => [
                        'type' => 'number',
                        'value' => 0.2
                    ],
                    'coupons' => [
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