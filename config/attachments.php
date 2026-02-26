<?php

/**
 * Copyright 2010 - 2015, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2015, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
use Cake\Core\Configure;

$config = [
    'Attachment' => [
        'path' => env('ATTACHMENT_PATH', '/tmp/filestorage'),
        's3-endpoint' => env('ATTACHMENT_S3_ENDPOINT', false),
        's3-region' => env('ATTACHMENT_S3_REGION', null),
        's3-key' => env('ATTACHMENT_S3_KEY', null),
        's3-secret' => env('ATTACHMENT_S3_SECRET', null),
        's3-bucket' => env('ATTACHMENT_S3_BUCKET', null),
    ]
];

return $config;
