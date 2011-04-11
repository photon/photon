<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
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
 * Locale aware formatting of the core information.
 *
 * Do not reinvent the wheel, this approach is coming from the Django
 * project: http://www.djangoproject.com
 */
namespace photon\locale\en\formats;

const DATE_FORMAT = 'N j, Y';
const TIME_FORMAT = 'P';
const DATETIME_FORMAT = 'N j, Y, P';
const YEAR_MONTH_FORMAT = 'F Y';
const MONTH_DAY_FORMAT = 'F j';
const SHORT_DATE_FORMAT = 'm/d/Y';
const SHORT_DATETIME_FORMAT = 'm/d/Y P';
const FIRST_DAY_OF_WEEK = 0; // Sunday

// One cannot store an array in a constant, so we store a string
// delimited with double pipe || for each format.
//
// Pay particular attention not to put || at the start or at the end
// and not to have spaces before or after. You cannot split a constant
// on several lines and you cannot create it from a variable. A lot of
// constraints, yes, but the good point is that you can access it as
// \photon\local\en\formats\THE_CONSTANT easily. Put examples, in the
// right order, just after.
//
// The available formats are given here: http://php.net/strftime
//
const DATE_INPUT_FORMATS = 'Y-m-d||m/d/Y||m/d/y';
// Y-m-d - 2006-10-25
// m/d/Y - 10/25/2006
// m/d/y - 10/25/06


const TIME_INPUT_FORMATS = 'H:i:s||H:i';
// H:i:s - 14:30:59
// H:i   - 14:30

const DATETIME_INPUT_FORMATS = 'Y-m-d H:i:s||Y-m-d H:i||Y-m-d||m/d/y H:i:s||m/d/y H:i||m/d/y||m/d/Y H:i:s||m/d/Y H:i||m/d/Y';
// Y-m-d H:i:s - 2006-10-25 14:30:22
// Y-m-d H:i   - 2006-10-25 14:30
// Y-m-d       - 2006-10-25
// m/d/y H:i:s - 10/25/06 14:30:59
// m/d/y H:i   - 10/25/06 14:30
// m/d/y       - 10/25/06
// m/d/Y H:i:s - 10/25/2006 14:30:59
// m/d/Y H:i   - 10/25/2006 14:30
// m/d/Y       - 10/25/2006

const DECIMAL_SEPARATOR = '.';
const THOUSAND_SEPARATOR = ',';
const NUMBER_GROUPING = 3;
