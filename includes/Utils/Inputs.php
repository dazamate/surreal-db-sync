<?php

namespace Dazamate\SurrealGraphSync\Utils;

use Dazamate\SurrealGraphSync\Validate\InputValidator;

class Inputs {
    public static function parse_record_id(string $record_value): ?string {
        // A raw surreal record id was passed
        if (InputValidator::is_surreal_db_record($record_value)) return $record_value;

        // if it's an integer, assume it's a post id
        if (ctype_digit((string)$record_value)) {
            return get_post_meta((int)$record_value, 'surreal_id', true) ?: NULL;
        }

        return NULL;
    }
}