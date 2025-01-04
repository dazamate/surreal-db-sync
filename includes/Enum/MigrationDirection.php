<?php 

namespace Dazamate\SurrealGraphSync\Enum;

enum MigrationDirection: string {
    case UP = 'up';
    case DOWN  = 'down';
}
