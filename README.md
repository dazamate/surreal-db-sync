## Mapping post types ##

To map a post type to a surreal node, hook into this filter

```php
    add_filter('surreal_graph_map_' . ProductPostType::POST_TYPE, [__CLASS__, 'map'], 10, 2);
```
When a product is created/updated/ this will be called for the post type to get a mapping of surreal types.

```php
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