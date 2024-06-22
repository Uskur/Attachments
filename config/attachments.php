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
        'path' => '/tmp/filestorage',
        's3-endpoint' => false,
        's3-region' => '',
        's3-key' => '',
        's3-secret' => '',
        's3-bucket' => '',
    ]
];

return $config;
