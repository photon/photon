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
 * Autoloader for Photon.
 */
function photonAutoLoad($class)
{
    $parts = array_filter(explode('\\', $class));
    if (1 < count($parts)) {
        // We have a namespace.
        $class_name = array_pop($parts);
        $file = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    } else {
        $file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    }
    // As we load only once to have everything is the process, the
    // require_once instead of require penalty is low. But the
    // require_once will prevent double loading a file and will result
    // in non confusing error messages.
    require_once $file;
}

set_include_path(realpath(__DIR__ . '/../')  . PATH_SEPARATOR 
                 . get_include_path());
spl_autoload_register('photonAutoLoad', true, true);
