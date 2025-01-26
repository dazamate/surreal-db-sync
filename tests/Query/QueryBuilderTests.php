<?php

namespace Dazamate\SurrealGraphSync\Tests\Query;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class QueryBuilderTests extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        
        Functions\when('get_post_meta')->alias(function ($post_id, $key = '', $single = false) {        
            return match($post_id) {
                42 => "image:abfsdfd897sdf9",
                default => null
            };
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testShouldReturnTheAnswer()
    {
        $result = QueryBuilder::build_object_str([]);
        $this->assertEquals($result, '{}');
    }

    public function testShouldBuildObjectWithSimpleStringProperty()
    {
        $data = [
            'title' => [
                'type'  => 'string',
                'value' => 'Hello World',
            ]
        ];
        
        $expected = "{title: <string>'Hello World'}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldBuildObjectWithSimpleNumberProperty()
    {
        $data = [
            'post_id' => [
                'type'  => 'number',
                'value' => 42,
            ]
        ];

        $expected = "{post_id: <number>42}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldBuildObjectWithDatetimeProperty()
    {
        $data = [
            'created_at' => [
                'type'  => 'datetime',
                'value' => '2025-01-26T12:34:56Z',
            ]
        ];
        
        $expected = "{created_at: <datetime>'2025-01-26T12:34:56Z'}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldExtractSurrealRecordIDFromPostID()
    {
        $data = [
            'image' => [
                'type'  => 'record<image>',
                'value' => 42,
            ]
        ];

        $result = QueryBuilder::build_object_str($data);

        $this->assertStringContainsString('image: <record<image>>image:abfsdfd897sdf9', $result, 'Should contain the image surreal record id');        
    }


    public function testShouldBuildQueryWithNULLValueIfRecordIdNoExist()
    {
        $data = [
            'image' => [
                'type'  => 'record<image>',
                'value' => 22,
            ]
        ];

        $result = QueryBuilder::build_object_str($data);

        $this->assertStringContainsString('image: NULL', $result, 'Should contain NULL as the record');        
    }

    public function testShouldBuildObjectWithArrayOfStrings()
    {       
        $data = [
            'categories' => [
                'type'  => 'array<string>',
                'value' => ['Breakfast', 'Quick & Easy'],
            ],
        ];
        
        // We expect something like: {categories: <array<string>>['Breakfast', 'Quick & Easy']}
        $expected = "{categories: <array<string>>['Breakfast', 'Quick & Easy']}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldHandleEmptyArrayValue()
    {
        // If 'value' is an empty array for an array type, it should produce NULL
        $data = [
            'empty_list' => [
                'type'  => 'array',
                'value' => [],
            ],
        ];

        // => {empty_list: <array>[]}
        $expected = "{empty_list: NULL}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldBuildObjectWithNestedObject()
    {
        $data = [
            'recipe_yield' => [
                'type' => 'object',
                'value' => [
                    'amount' => [
                        'type'  => 'number',
                        'value' => 4,
                    ],
                    'measure_unit' => [
                        'type'  => 'string',
                        'value' => 'servings',
                    ]
                ]
            ]
        ];
        
        // => {recipe_yield: <object>{amount: <number>4, measure_unit: <string>'servings'}}
        $expected = "{recipe_yield: <object>{amount: <number>4, measure_unit: <string>'servings'}}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }

    public function testShouldBuildObjectWithNestedArraysOfObjects()
    {
        // Example akin to method_steps or nutrition fields in your mapper:
        $data = [
            'method_steps' => [
                'type'  => 'array',  // or 'array<object>'
                'value' => [
                    [
                        'type' => 'object',
                        'value' => [
                            'name' => [
                                'type' => 'string',
                                'value' => 'Step 1'
                            ],
                            'description' => [
                                'type' => 'string',
                                'value' => 'Do something...'
                            ],
                            'step_image' => [
                                'type' => 'record<image>',
                                'value' => 999
                            ]
                        ]
                    ],
                    [
                        'type' => 'object',
                        'value' => [
                            'name' => [
                                'type' => 'string',
                                'value' => 'Step 2'
                            ],
                            'description' => [
                                'type' => 'string',
                                'value' => 'Do something else...'
                            ],
                            'step_image' => [
                                'type' => 'record<image>',
                                'value' => ''
                            ]
                        ]
                    ],
                ],
            ],
        ];
                
        $result = QueryBuilder::build_object_str($data);

        // Basic structural checks:
        $this->assertStringStartsWith('{method_steps: <array>[', $result);
        $this->assertStringEndsWith(']}', $result);

        // Check partial contents:
        $this->assertStringContainsString("name: <string>'Step 1'", $result);
        $this->assertStringContainsString("description: <string>'Do something...'", $result);
        $this->assertStringContainsString("name: <string>'Step 2'", $result);
        $this->assertStringContainsString("description: <string>'Do something else...'", $result);
    }

    public function testShouldHandleNullValues()
    {
        // If 'value' is an empty string for a 'string' type, or null, we want to see "NULL"
        $data = [
            'someField' => [
                'type'  => 'string',
                'value' => '',
            ],
            'someOtherField' => [
                'type' => 'number',
                'value' => null,
            ]
        ];

        // => {someField: NULL, someOtherField: NULL}
        $expected = "{someField: NULL, someOtherField: NULL}";
        
        $this->assertSame($expected, QueryBuilder::build_object_str($data));
    }
}
