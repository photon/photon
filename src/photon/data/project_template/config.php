<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * The project configuration file configures all the apps in your project.
 */
return array(
             // Once your work is completed, you must switch the flag
             // to false as in case of errors in debug mode, a lot of
             // data are printed on screen.

             // The main debug flag will set the logging to debug
             // mode, but also the handling of errors.
             'debug' => true,

             'urls' => include __DIR__.'/urls.php',

             'secret_key' => '%%SECRET_KEY%%',
             'admins' => array(array('1st Admin Name', 'admin1@example.com')),

             // Only one simple application is installed in project,
             // the 'helloworld' application.
             'installed_apps' => array('helloworld'),

             // The templates are compiled as .php files and are
             // stored in the tmp folder.
             'tmp_folder' => '/tmp',

             // The template folders are where your templates are stored.
             'template_folders' => array(),
             );
             
