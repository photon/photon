<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
#
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010, 2011 Ceondo Ltd and contributors.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
# 02110-1301, USA.
#
# ***** END LICENSE BLOCK ***** */

/**
 * File utilities.
 */
namespace photon\path;

/**
 * Directory utilities.
 */
class Dir
{
    /**
     * List recursively all the files of a directory.
     *
     * The root directory is not returned as part of the file. For
     * example, if you give the directory '/home/login' and you have
     * the files '.profile' and '.ssh/authorized_keys' into the
     * directory, you will get array('.profile',
     * '.ssh/authorized_keys') a returned value. 
     *
     * @param $dir string Directory to get the files from without trailing slash
     * @param $regex Regular expression to exclude some files/folders (array())
     * @return array Files
     */
    public static function listFiles($dir, $regex=array())
    {
         $dirItr = new \RecursiveDirectoryIterator($dir);
         $filterItr = new RecursiveDotDirsFilterIterator($dirItr, null, $regex);
         $itr = new \RecursiveIteratorIterator($filterItr, 
                                      \RecursiveIteratorIterator::SELF_FIRST);
         $files = array();
         $dirl = strlen($dir) + 1;
         foreach ($itr as $filePath => $fileInfo) {
             if ($fileInfo->isFile()) {
                 $files[] = substr($filePath, $dirl, strlen($filePath));
             }
         }

         return $files;
    }
}


/**
 * Filter out the common .* files/folders we do not want when listing files.
 *
 * Usage:
 *
 * <pre>
 * $dirItr = new \RecursiveDirectoryIterator('/sample/path');
 * $filterItr = new RecursiveDotDirsFilterIterator($dirItr);
 * $itr = new \RecursiveIteratorIterator($filterItr, 
 *                                      \RecursiveIteratorIterator::SELF_FIRST);
 * foreach ($itr as $filePath => $fileInfo) {
 *     echo $fileInfo->getFilename() . PHP_EOL;
 * }
 *</pre>
 */
class RecursiveDotDirsFilterIterator extends \RecursiveFilterIterator 
{
    public static $filters = array('.', '..', '.svn', '.git', '.DS_Store');
    public static $regex = array();

    public function __construct($iterator, $filters=null, $regex=null)
    {
        parent::__construct($iterator);
        self::$filters = (null !== $filters) ? $filters : self::$filters;
        self::$regex = (null !== $regex) ? $regex : self::$regex;
    }

    public function accept() 
    {
        if (in_array($this->current()->getFilename(), self::$filters, true)) {

            return false;
        }
        foreach (self::$regex as $regex) {
            if (preg_match($regex, $this->current()->getFilename())) {
                return false;
            }
        }

        return true;
    }
}