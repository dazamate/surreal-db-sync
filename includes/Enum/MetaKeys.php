<?php 

namespace Dazamate\SurrealGraphSync\Enum;

enum MetaKeys: string {
    case SURREAL_DB_RECORD_ID_META_KEY = 'surreal_id';
    case SURREAL_SYNC_ERROR_META_KEY = 'surreal_sync_error';
}