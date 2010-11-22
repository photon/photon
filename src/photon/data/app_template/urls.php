<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * The app URLs file is included into the main project url files 
 * to be available at the right point in the URL space.
 */

return array(array('regex' => '#^/$#',
                   'view' => array('\helloworld\views\Views', 'index'),
                   'name' => 'helloworld_index',
                   ),
             array('regex' => '#^/(.*)$#',
                   'view' => array('\helloworld\views\Views', 'you'),
                   'name' => 'helloworld_you',
                   ),
             );
