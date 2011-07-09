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
             'base_urls' => '/demo',
             'urls' => include __DIR__.'/urls.php',

             'secret_key' => '%%SECRET_KEY%%',
             'admins' => array(array('1st Admin Name', 'admin1@example.com')),

             // List of installed applications
             'installed_apps' => array('helloworld', 'photonchat'),

             // The templates are compiled as .php files and are
             // stored in the tmp folder.
             'tmp_folder' => sys_get_temp_dir(),

             // The template folders are where your templates are stored.
             'template_folders' => array(__DIR__ . '/photonchat/templates'),
             // List of installed tasks
             'installed_tasks' => 
             array('photonchat_server' => '\photonchat\task\Server'),
             // And configuration for each task
             'photon_task_photonchat_server' => 
             array('m2_pub' => 'tcp://127.0.0.1:9996'),
             );

