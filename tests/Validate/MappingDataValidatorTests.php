<?php

namespace Dazamate\SurrealGraphSync\Tests\Validate;

use PHPUnit\Framework\TestCase;
use Dazamate\SurrealGraphSync\Validate\MappingDataValidator;

class MappingDataValidatorTests extends TestCase {

    public function testValidateEmptyData(): void {
        $mapping_data = [];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors, 'No errors should be returned for empty data.' );
    }

    public function testValidateMissingType(): void {
        $mapping_data = [
            'field1' => [
                // 'type' => 'string', // intentionally missing
                'value' => 'hello',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'Missing \'type\' field', $errors[0] );
    }

    public function testValidateStringFieldSuccess(): void {
        $mapping_data = [
            'title' => [
                'type'  => 'string',
                'value' => 'Some Title',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateStringFieldFailure(): void {
        // 'value' is an integer, not a string
        $mapping_data = [
            'title' => [
                'type'  => 'string',
                'value' => 123,
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertCount( 1, $errors );
        $this->assertStringContainsString( 'must be a string', $errors[0] );
    }

    public function testValidateNumberFieldSuccess(): void {
        $mapping_data = [
            'quantity' => [
                'type'  => 'number',
                'value' => 123,
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateNumberFieldFailure(): void {
        $mapping_data = [
            'quantity' => [
                'type'  => 'number',
                'value' => 'abc', // Not numeric
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'must be numeric', $errors[0] );
    }

    public function testValidateDateTimeFieldSuccessWithDateString(): void {
        // ISO 8601 example => 2025-01-26T18:29:00+00:00
        $mapping_data = [
            'published' => [
                'type'  => 'datetime',
                'value' => '2025-01-26T18:29:00+00:00',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateDateTimeFieldSuccessWithTimeStampStr(): void {
        // ISO 8601 example => 2025-01-26T18:29:00+00:00
        $mapping_data = [
            'published' => [
                'type'  => 'datetime',
                'value' => '345435435345',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }


    public function testValidateDateTimeFieldSuccessWithTimeStampInt(): void {
        // ISO 8601 example => 2025-01-26T18:29:00+00:00
        $mapping_data = [
            'published' => [
                'type'  => 'datetime',
                'value' => 345435435345,
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateDateTimeFieldFailure(): void {
        $mapping_data = [
            'published' => [
                'type'  => 'datetime',
                'value' => 'Not a date',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'must be a timestamp or a valid ISO8601 datetime string', $errors[0] );
    }

    public function testValidateRecordFieldSuccessWithPostID(): void {
        // For record type, value must be numeric post ID
        $mapping_data = [
            'author' => [
                'type'  => 'record',
                'value' => '123', // numeric string
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateRecordFieldSuccessWithRecordID(): void {
        // For record type, value must be numeric post ID
        $mapping_data = [
            'author' => [
                'type'  => 'record',
                'value' => 'person:35r4', // numeric string
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateRecordFieldFailure(): void {
        $mapping_data = [
            'author' => [
                'type'  => 'record',
                'value' => 'abc', // not numeric
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString('must be a surreal record id or a post ID for type=record.', $errors[0] );
    }

    public function testValidateArrayFieldSuccess(): void {
        // An 'array' field with sub-items
        $mapping_data = [
            'tags' => [
                'type'  => 'array',
                'value' => [
                    'recipes',
                    'summer',
                    [
                        'type'  => 'string',
                        'value' => 'nested typed item',
                    ],
                ],
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateArrayFieldFailure(): void {
        // 'array' but 'value' is not an array
        $mapping_data = [
            'tags' => [
                'type'  => 'array',
                'value' => 'not an array',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'must be an array for type=array', $errors[0] );
    }

    public function testValidateObjectFieldSuccess(): void {
        // 'object' field, each sub-key is itself a typed array
        $mapping_data = [
            'metadata' => [
                'type'  => 'object',
                'value' => [
                    'creator' => [
                        'type'  => 'string',
                        'value' => 'John Doe',
                    ],
                    'count'   => [
                        'type'  => 'number',
                        'value' => 42,
                    ],
                ],
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertTrue( $is_valid );
        $this->assertEmpty( $errors );
    }

    public function testValidateObjectFieldFailure_NotAnArray(): void {
        $mapping_data = [
            'metadata' => [
                'type'  => 'object',
                'value' => 'not an array',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'must be an associative array for type=object', $errors[0] );
    }

    public function testValidateObjectFieldFailure_MissingSubType(): void {
        // 'object' has a sub-key that is not typed
        $mapping_data = [
            'metadata' => [
                'type'  => 'object',
                'value' => [
                    'creator'   => [
                        'type'  => 'string',
                        'value' => 'John Doe',
                    ],
                    'untypeKey' => [
                        // 'type' => 'number', // missing
                        'value' => 123,
                    ],
                ],
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'Each key in an object must be typed data', $errors[0] );
    }

    public function testValidateUnsupportedType(): void {
        // 'customType' is not recognized in getPrimitiveType
        $mapping_data = [
            'fieldX' => [
                'type'  => 'customType',
                'value' => 'whatever',
            ],
        ];
        $errors       = [];

        $is_valid = MappingDataValidator::Validate( $mapping_data, $errors );

        $this->assertFalse( $is_valid );
        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'Unsupported type', $errors[0] );
    }
}
