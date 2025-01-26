<?php

namespace Dazamate\SurrealGraphSync\Validate;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class MappingDataValidator {
    /**
     * Validate the entire mapped_data structure.
     *
     * @param  array  $mapping_data
     * @param  array  $errors       an array passed by reference in which errors will be stored
     * @return bool                 returns true if valid, false otherwise
     */
    public static function validate(array $mapping_data, array &$errors): bool {
        $isValid = true;

        // Validate each top-level key => mapped item
        foreach ($mapping_data as $key => $item) {
            if (!self::validate_item($item, $key, $errors)) {
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Validate a single piece of mapped data. 
     * 
     * The $path argument is used for building a meaningful location string
     * in the validation errors (e.g. "someField.subField[2]").
     */
    private static function validate_item(mixed $item, string $path, array &$errors): bool {
        // If for some reason the item isn't even an array, that's an error.
        if (!is_array($item)) {
            $errors[] = "[$path]: Invalid item structure (must be an array).";
            return false;
        }

        // If there's no 'type', we cannot proceed.
        if (empty($item['type'])) {
            $errors[] = "[$path]: Missing 'type' field.";
            return false;
        }

        $type = $item['type'];

        // We can allow a missing 'value' to mean "null" or "no value".
        // But often, you might want to ensure it is present, so check below:
        if (!array_key_exists('value', $item)) {
            // If your logic says we require 'value' always, then set an error:
            // $errors[] = "[$path]: Missing 'value' field.";
            // return false;
            return true; 
        }

        $value = $item['value'];

        // Switch on the "primitive" type (like in QueryBuilder).
        switch (self::get_primitive_type($type)) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "[$path]: 'value' must be a string for type=string.";
                    return false;
                }
                return true;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "[$path]: 'value' must be numeric for type=number.";
                    return false;
                }
                return true;

            case 'datetime':
                // Now we check strictly for ISO8601 (like date('c')).
                if (!self::is_ISO8601($value)) {
                    $errors[] = "[$path]: 'value' must be a valid ISO8601 datetime string (e.g. 2025-01-26T18:29:00+00:00).";
                    return false;
                }
                return true;

            case 'record':
                // Check it's integer-ish (the post ID).
                if (!ctype_digit((string)$value) && !empty($value)) {
                    $errors[] = "[$path]: 'value' must be a numeric post ID for type=record.";
                    return false;
                }
                return true;

            case 'array':
                if (!is_array($value)) {
                    $errors[] = "[$path]: 'value' must be an array for type=array.";
                    return false;
                }
                // Validate sub-items
                foreach ($value as $index => $subItem) {
                    $subPath = "{$path}[{$index}]";
                    // If this subItem is itself a mapped data array with 'type' => '...', 'value' => ...
                    // we can recurse. If it's just a scalar, skip unless you want to enforce typed items only.
                    if (is_array($subItem) && isset($subItem['type'])) {
                        if (!self::validate_item($subItem, $subPath, $errors)) {
                            return false;
                        }
                    }
                }
                return true;

            case 'object':
                if (!is_array($value)) {
                    $errors[] = "[$path]: 'value' must be an associative array for type=object.";
                    return false;
                }
                // Check each subKey => subItem
                foreach ($value as $subKey => $subItem) {
                    $subPath = "{$path}.{$subKey}";
                    if (!is_array($subItem) || !isset($subItem['type'])) {
                        $errors[] = "[$subPath]: Each key in an object must be typed data.";
                        return false;
                    }
                    if (!self::validate_item($subItem, $subPath, $errors)) {
                        return false;
                    }
                }
                return true;

            default:
                // Unrecognized type
                $errors[] = "[$path]: Unsupported type '{$type}'.";
                return false;
        }
    }

    private static function get_primitive_type(string $type_name): string
    {
        return QueryBuilder::get_primitive_type($type_name);
    }

    /**
     * Check if $value is a valid ISO8601 datetime string.
     * 
     * Example: 2025-01-26T18:29:00+00:00
     */
    private static function is_ISO8601(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Try creating a DateTime object from the string:
        // \DateTime::ATOM is basically "Y-m-d\TH:i:sP"
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $value);
        if (!$dt) {
            return false;
        }

        return ($dt->format(\DateTime::ATOM) === $value);
    }
}
