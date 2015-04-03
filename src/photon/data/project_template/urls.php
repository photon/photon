<?php

$urls_mapping = array();

$urls_mapping[] = array('regex' => '#^/$#',
                        'view' => array('\HelloWorld\Views\Base', 'redirect'),
                        'name' => 'redirect_view');

$urls_mapping[] = array('regex' => '#^/hello$#',
                        'view' => array('\HelloWorld\Views\Base', 'hello'),
                        'name' => 'hello_view');

$urls_mapping[] = array('regex' => '#^/hello/(.*)$#',
                        'view' => array('\HelloWorld\Views\Base', 'hello_doe'),
                        'name' => 'hello_doe_view');

return $urls_mapping;
