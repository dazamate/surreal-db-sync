<?php

namespace Dazamate\SurrealGraphSync\Validate;

use Dazamate\SurrealGraphSync\Query\QueryBuilder;

class InputValidator {
    /**
     * Check if $value is a valid ISO8601 datetime string.
     * 
     * Example: 2025-01-26T18:29:00+00:00
     */
    public static function is_ISO8601(mixed $value): bool {        
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

    public static function is_surreal_db_record(mixed $value): bool {
        return is_string($value) && strpos($value, ':') !== false;
    }
}
