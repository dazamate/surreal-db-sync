<?php

namespace Dazamate\SurrealGraphSync\Tests\Validate;

use PHPUnit\Framework\TestCase;
use Dazamate\SurrealGraphSync\Validate\RelatedMappingDataValidator;

class RelateMappingDataValidatorTests extends TestCase {

	/**
	 * Test that validation fails if `from_record` is missing.
	 */
	public function test_missing_from_record_should_fail() {
		$relate_data = [
			// 'from_record' => 'some_table:1', // intentionally missing
			'to_record'       => 'some_table:2',
			'relation_table'  => 'edge_table',
		];

		$errors = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid or missing "from_record"', $errors[0] );
	}

	/**
	 * Test that validation fails if `from_record` is not a Surreal record or numeric.
	 */
	public function test_invalid_from_record_should_fail() {
		$relate_data = [
			'from_record'    => 'invalid_format',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertStringContainsString( 'Invalid or missing "from_record"', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation fails if `to_record` is missing.
	 */
	public function test_missing_to_record_should_fail() {
		$relate_data = [
			'from_record'    => 'some_table:1',
			// 'to_record'    => 'some_table:2', // intentionally missing
			'relation_table' => 'edge_table',
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid or missing "to_record"', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation fails if `relation_table` is missing.
	 */
	public function test_missing_relation_table_should_fail() {
		$relate_data = [
			'from_record' => 'some_table:1',
			'to_record'   => 'some_table:2',
			// 'relation_table' => 'edge_table', // intentionally missing
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid or missing "relation_table"', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation fails if `unique` is present but not a boolean.
	 */
	public function test_invalid_unique_type_should_fail() {
		$relate_data = [
			'from_record'    => 'some_table:1',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
			'unique'         => 'yes', // not boolean
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'The "unique" key must be a boolean', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation fails if `data` is present but not an array.
	 */
	public function test_invalid_data_type_should_fail() {
		$relate_data = [
			'from_record'    => 'some_table:1',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
			'data'           => 'I am not an array',
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'The "data" key must be a key value array if specified.', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation fails if `data` is an array but invalid according to MappingDataValidator.
	 * 
	 * Here, we pretend that MappingDataValidator fails. In real tests, you would either
	 * mock MappingDataValidator or craft data that definitely fails its checks.
	 */
	public function test_data_fails_mapping_data_validator_should_fail() {
		// Craft data that you expect MappingDataValidator to reject (example: missing 'type')
		$invalid_data = [
			'field_without_type' => [
				'value' => 'some-value',
				// 'type' => 'string', intentionally missing
			],
		];

		$relate_data = [
			'from_record'    => 'some_table:1',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
			'data'           => $invalid_data,
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertFalse( $is_valid );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Related data mapping error:', implode( ' ', $errors ) );
	}

	/**
	 * Test that validation passes with correct structure and minimal data.
	 */
	public function test_valid_minimal_data_should_succeed() {
		$relate_data = [
			'from_record'    => 'some_table:1',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertTrue( $is_valid, 'Expected validation to succeed.' );
		$this->assertEmpty( $errors, 'Expected no validation errors.' );
	}

	/**
	 * Test that validation passes with all valid fields including `unique` and `data`.
	 *
	 * In reality, you'll want `data` that passes MappingDataValidator fully. The example below
	 * is a simple demonstration of valid structure (assuming MappingDataValidator accepts it).
	 */
	public function test_valid_full_data_should_succeed() {
		$relate_data = [
			'from_record'    => 'some_table:1',
			'to_record'      => 'some_table:2',
			'relation_table' => 'edge_table',
			'unique'         => true,
			'data'           => [
				'custom_field' => [
					'type'  => 'string',
					'value' => 'Hello World',
				],
			],
		];

		$errors   = [];
		$is_valid = RelatedMappingDataValidator::validate( $relate_data, $errors );

		$this->assertTrue( $is_valid );
		$this->assertEmpty( $errors );
	}
}
