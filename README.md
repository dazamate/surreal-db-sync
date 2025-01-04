##Migrations##

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