<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * The project URLs file "includes" the urls of your apps at the right
 * points in the URL space of your project.
 */

return array(
    array('regex' => '#^/hello#',
           'sub' => include __DIR__ . '/apps/helloworld/urls.php'),
);
