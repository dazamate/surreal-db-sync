<?php 

namespace Dazamate\SurrealGraphSync\Enum;

enum GraphTable: string {
    case MIGRATION = 'migration';
    case PERSON = 'person';
    case IMAGE = 'image';

    public function to_string(): string {
        return $this->value;
    }
}