<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * The project configuration file for unit testing.
 *
 * It configures all the apps in your project.
 *
 */
$base_conf = include __DIR__ . '/config.php';
$test_conf =  array(
                    'debug' => true,

                    // NEVER EVER USE YOUR PRODUCTION DATABASE TO RUN
                    // YOUR UNIT TESTS OR YOUR CONTINUOUS INTEGRATION
                    // SERVER. 
                    // AGAIN, NEVER EVER USE YOUR PRODUCTION DATABASE
                    // FOR UNIT TESTING OR YOU WILL END UP DROPPING
                    // YOUR PRODUCTION DATABASE AND ALL YOUR CUSTOMER
                    // DATA WILL BE LOST.
                    'db_login' => 'testuser',
                    'db_password' => 'testpassword',
                    'db_server' => '127.0.0.1',
                    'db_database' => 'testdatabase',
                    'db_engine' => 'WhateverIsYourEngine',
                    );

return array_merge($base_conf, $test_conf);

             
