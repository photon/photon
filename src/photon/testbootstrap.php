<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Bootstrap file for PHPUnit.
 *
 * To correctly run the unit tests with PHPUnit one needs to have:
 *
 * - the Photon autoloader in the autoloader SPL stack.
 * - the unit test configuration loaded.
 *
 * But... PHPUnit accepts only one PHP prepend file. Good news,
 * PHPUnit can also set some php.ini values before the run. So, Photon
 * is calling PHPUnit with this bootstrap file and at the same time,
 * it passes PHPUnit the photon.config value with the path to the
 * config file for the unit tests.
 *
 * Of course, if you do not like this bootstrap file, you can run your
 * tests this way:
 * 
 * $ photon runtests --bootstrap=path/to/yourbootstrap.php
 *
 * Also, by default, Photon will load the config.test.php file in your
 * current folder. You can change it by running:
 *
 * $ photon --conf=path/to/myconfig.php runtests
 *
 * You can combine them:
 *
 * $ photon --conf=fooconfig.php runtests --bootstrap=/other/testbootstrap.php
 * 
 * WARNING: For added security, photon will never accept to run tests
 * against a config file named "config.php". This is to avoid the
 * "oops I run the tests against my production settings and I have
 * just dropped the main database.".
 */

namespace 
{
    include_once __DIR__ . '/autoload.php';
    $config = array('tmp_folder' => sys_get_temp_dir(),
                    'debug' => true,
                    'secret_key' => 'SECRET_KEY');
    $init = ini_get('photon.config');
    if (file_exists($init)) {
        $init = include ini_get('photon.config');
        $config = array_merge($config, $init);
    }
    $config['runtests'] = true;
    \photon\config\Container::load($config);
}