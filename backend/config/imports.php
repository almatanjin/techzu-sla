<?php

return [
    'disk' => env('IMPORTS_DISK', 'local'),
    'chunk_size' => (int) env('IMPORTS_CHUNK_SIZE', 500),
];

