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
namespace photon\locale\fr\formats;

const DATE_FORMAT = 'j F Y';
const TIME_FORMAT = 'H:i:s';
const DATETIME_FORMAT = 'j F Y H:i:s';
const YEAR_MONTH_FORMAT = 'F Y';
const MONTH_DAY_FORMAT = 'j F';
const SHORT_DATE_FORMAT = 'j N Y';
const SHORT_DATETIME_FORMAT = 'j N Y H:i:s';
const FIRST_DAY_OF_WEEK = 1; // Lundi

const DATE_INPUT_FORMATS = 'd/m/Y||d.m.Y||d.m.y||Y-m-d||y-m-d';
// d/m/Y - 25/10/2006
// d/m/y - 25/10/06
// d.m.Y - 25.10.2006
// d.m.y - 25.10.06
// Y-m-d - 2006-10-25
// y-m-d - 06-10-25

const TIME_INPUT_FORMATS = 'H:i:s||H:i';
// H:i:s - 14:30:59
// H:i   - 14:30

const DATETIME_INPUT_FORMATS = 'd/m/Y H:i:s||d/m/Y H:i||d/m/Y||d.m.Y H:i:s||d.m.Y H:i||d.m.Y||Y-m-d H:i:s||Y-m-d H:i||Y-m-d';
// d/m/Y H:i:s - 25/10/2006 14:30:59
// d/m/Y H:i   - 25/10/2006 14:30
// d/m/Y       - 25/10/2006
// d.m.Y H:i:s - 25.10.2006 14:30:59
// d.m.Y H:i   - 25.10.2006 14:30
// d.m.Y       - 25.10.2006
// Y-m-d H:i:s - 2006-10-25 14:30:59
// Y-m-d H:i   - 2006-10-25 14:30
// Y-m-d       - 2006-10-25

const DECIMAL_SEPARATOR = ',';
const THOUSAND_SEPARATOR = ' ';
const NUMBER_GROUPING = 3;

