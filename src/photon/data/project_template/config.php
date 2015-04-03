<?php

// Set the initial log level
\photon\log\Log::setLevel('INFO');

return array(
    'debug' => true,
    'secret_key' => 'A very long secret key to sign cookie',

    'base_urls' => '',
    'urls' => include 'urls.php',

    'template_folders' => array(
        __DIR__ .'/HelloWorld/templates',
    ),

    'middleware_classes' => array(
        'photon\middleware\Gzip',
    ),
);
