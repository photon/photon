<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
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
 * Collection of reusable views.
 *
 * These views can be used to perform some simple operations. For
 * example, serving a directory of static content.
 */
namespace photon\views;

use \photon\shortcuts;
use \photon\core\URL as URL;
use \photon\http\response\Redirect;
use \photon\http\response\NotModified;
use \photon\translation\Translation;

/**
 * Render a template.
 */
class Template
{
    /**
     * The simplest possible rendering.
     */
    public function simple($request, $match, $template)
    {
        return shortcuts\Template::RenderToResponse($template, array(), $request);
    }

    /**
     * Like previous function, but with i18n support.
     * It's allow to serve static content build with the template engine.
     * The first variable part of the url must be the language code.
     * /[en|fr|de]/home
     * /[en|fr|de]/contacts
     */
    public function simpleWithLocale($request, $match, $template)
    {
        Translation::setLocale(strtolower($match[1]));
        return shortcuts\Template::RenderToResponse($template, array(), $request);
    }
}

class Simple
{
    /**
     * Simple content view.
     *
     * @param Request Request object
     * @param array Match
     * @param string Content of the page
     */
    function content($request, $match, $content)
    {
        return new \photon\http\Response($content);
    }

    function redirect($request, $match, $view)
    {
        return new Redirect(URL::forView($view[0], $view[1]));
    }

    function redirectUrl($request, $match, $url)
    {
        return new Redirect($url);
    }
}

class AssetDir
{
    /**
     * Serve a virtual directory from a phar archive.
     *
     * The directory must be defined as phar://path/in/the/phar
     *
     * The url definition is loaded only once at the start of the
     * Photon server, this means that you can be smart and depending
     * of \Phar::running() in the urls.php either use serveFromPhar() or
     * serve().
     *
     * @param $request Photon request
     * @param $match first match the path. It must contain only "A-Za-z0-9./-".
     *               if '..' is found in the match, it will not be accepted.
     * @param $directory Directory to serve
     */
    public function serveFromPhar($request, $match, $directory)
    {
        if (preg_match('/[^A-Za-z0-9\-\_\.\/]/', $match[1]) || false !== strpos($match[1], '..')) {
            return new \photon\http\response\Forbidden($request);
        }

        list($pharPath, $subFolder) = explode('.phar', $directory);
        $pharPath = substr($pharPath, 7) . '.phar';
        $subFolder = ($subFolder[0] === '/') ? substr($subFolder, 1) : $subFolder;

        $phar = new \Phar($pharPath);
        if (substr($match[1], 0, 1) !== '/') {
          $match[1] = '/' . $match[1];
        }
        $path = $subFolder . $match[1];

        if (!isset($phar[$path])) {
            throw new \photon\http\error\NotFound();
        }

        $file = $phar[$path];
        $crc32 = dechex($file->getCRC32());

        $comps = explode('.', $file);
        $ext = array_pop($comps);
        $mime = isset(self::$mimes[$ext]) ? self::$mimes[$ext] : 'application/octet-stream';

        $etag = $request->getHeader('if-none-match', null);
        if ($etag == $crc32) {
            return new NotModified('', $mime);
        }

        $res = new \photon\http\Response(file_get_contents($file), $mime);
        $res->headers['ETag'] = $crc32;
        $res->headers['Cache-Control'] = 'max-age=604800';

        return $res;
    }

    /**
     * Serve a directory.
     *
     * It is not efficient because no caching is done, but it is
     * practical for development.
     *
     * @param $request Photon request
     * @param $match Array of the matched element
     * @param $directory Directory to serve
     */
    public function serve($request, $match, $directory)
    {
        // Detect request to the root folder and try to serve index.html
        if ($match[1] === '/') {
            $match[1] = 'index.html';
        }

        // Handle phar content
        if (substr($directory, 0, 7) === 'phar://') {
            return self::serveFromPhar($request, $match, $directory);
        }

        $directory = realpath($directory);
        $file = realpath($directory . '/' . $match[1]);
        if (0 !== strpos($file, $directory)) {
            throw new \photon\http\error\NotFound();
        }
        if (!file_exists($file) || !is_file($file)) {
            throw new \photon\http\error\NotFound();
        }
        // Now we have the file, we need to find the mime type to send
        // it correctly. We have a table to do it.
        $comps = explode('.', $file);
        $ext = array_pop($comps);
        $mime = (isset(self::$mimes[$ext]))
            ? self::$mimes[$ext] : 'application/octet-stream';
        return new \photon\http\Response(file_get_contents($file),
                                         $mime);
    }

    static $mimes = array (
                           'ez' => 'application/andrew-inset',
                           'anx' => 'application/annodex',
                           'atom' => 'application/atom+xml',
                           'atomcat' => 'application/atomcat+xml',
                           'atomsrv' => 'application/atomserv+xml',
                           'lin' => 'application/bbolin',
                           'cap' => 'application/cap',
                           'pcap' => 'application/cap',
                           'cu' => 'application/cu-seeme',
                           'davmount' => 'application/davmount+xml',
                           'tsp' => 'application/dsptype',
                           'es' => 'application/ecmascript',
                           'spl' => 'application/x-futuresplash',
                           'hta' => 'application/hta',
                           'jar' => 'application/java-archive',
                           'ser' => 'application/java-serialized-object',
                           'class' => 'application/java-vm',
                           'js' => 'application/javascript',
                           'm3g' => 'application/m3g',
                           'hqx' => 'application/mac-binhex40',
                           'cpt' => 'image/x-corelphotopaint',
                           'nb' => 'application/mathematica',
                           'nbp' => 'application/mathematica',
                           'mdb' => 'application/msaccess',
                           'doc' => 'application/msword',
                           'dot' => 'application/msword',
                           'mxf' => 'application/mxf',
                           'bin' => 'application/octet-stream',
                           'oda' => 'application/oda',
                           'ogx' => 'application/ogg',
                           'pdf' => 'application/pdf',
                           'key' => 'application/pgp-keys',
                           'pgp' => 'application/pgp-signature',
                           'prf' => 'application/pics-rules',
                           'ps' => 'application/postscript',
                           'ai' => 'application/postscript',
                           'eps' => 'application/postscript',
                           'epsi' => 'application/postscript',
                           'epsf' => 'application/postscript',
                           'eps2' => 'application/postscript',
                           'eps3' => 'application/postscript',
                           'rar' => 'application/rar',
                           'rdf' => 'application/rdf+xml',
                           'rss' => 'application/rss+xml',
                           'rtf' => 'application/rtf',
                           'smi' => 'application/smil',
                           'smil' => 'application/smil',
                           'xhtml' => 'application/xhtml+xml',
                           'xht' => 'application/xhtml+xml',
                           'xml' => 'application/xml',
                           'xsl' => 'application/xml',
                           'xsd' => 'application/xml',
                           'xspf' => 'application/xspf+xml',
                           'zip' => 'application/zip',
                           'apk' => 'application/vnd.android.package-archive',
                           'cdy' => 'application/vnd.cinderella',
                           'kml' => 'application/vnd.google-earth.kml+xml',
                           'kmz' => 'application/vnd.google-earth.kmz',
                           'xul' => 'application/vnd.mozilla.xul+xml',
                           'xls' => 'application/vnd.ms-excel',
                           'xlb' => 'application/vnd.ms-excel',
                           'xlt' => 'application/vnd.ms-excel',
                           'cat' => 'application/vnd.ms-pki.seccat',
                           'stl' => 'application/vnd.ms-pki.stl',
                           'ppt' => 'application/vnd.ms-powerpoint',
                           'pps' => 'application/vnd.ms-powerpoint',
                           'odc' => 'application/vnd.oasis.opendocument.chart',
                           'odb' => 'application/vnd.oasis.opendocument.database',
                           'odf' => 'application/vnd.oasis.opendocument.formula',
                           'odg' => 'application/vnd.oasis.opendocument.graphics',
                           'otg' => 'application/vnd.oasis.opendocument.graphics-template',
                           'odi' => 'application/vnd.oasis.opendocument.image',
                           'odp' => 'application/vnd.oasis.opendocument.presentation',
                           'otp' => 'application/vnd.oasis.opendocument.presentation-template',
                           'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                           'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
                           'odt' => 'application/vnd.oasis.opendocument.text',
                           'odm' => 'application/vnd.oasis.opendocument.text-master',
                           'ott' => 'application/vnd.oasis.opendocument.text-template',
                           'oth' => 'application/vnd.oasis.opendocument.text-web',
                           'application/vnd.osa.netdeploy' => 'application/vnd.osa.netdeploy',
                           'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                           'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                           'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                           'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
                           'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
                           'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
                           'cod' => 'application/vnd.rim.cod',
                           'mmf' => 'application/vnd.smaf',
                           'sdc' => 'application/vnd.stardivision.calc',
                           'sds' => 'application/vnd.stardivision.chart',
                           'sda' => 'application/vnd.stardivision.draw',
                           'sdd' => 'application/vnd.stardivision.impress',
                           'sdf' => 'chemical/x-mdl-sdfile',
                           'sdw' => 'application/vnd.stardivision.writer',
                           'sgl' => 'application/vnd.stardivision.writer-global',
                           'sxc' => 'application/vnd.sun.xml.calc',
                           'stc' => 'application/vnd.sun.xml.calc.template',
                           'sxd' => 'application/vnd.sun.xml.draw',
                           'std' => 'application/vnd.sun.xml.draw.template',
                           'sxi' => 'application/vnd.sun.xml.impress',
                           'sti' => 'application/vnd.sun.xml.impress.template',
                           'sxm' => 'application/vnd.sun.xml.math',
                           'sxw' => 'application/vnd.sun.xml.writer',
                           'sxg' => 'application/vnd.sun.xml.writer.global',
                           'stw' => 'application/vnd.sun.xml.writer.template',
                           'sis' => 'application/vnd.symbian.install',
                           'vsd' => 'application/vnd.visio',
                           'wbxml' => 'application/vnd.wap.wbxml',
                           'wmlc' => 'application/vnd.wap.wmlc',
                           'wmlsc' => 'application/vnd.wap.wmlscriptc',
                           'wpd' => 'application/vnd.wordperfect',
                           'wp5' => 'application/vnd.wordperfect5.1',
                           'wk' => 'application/x-123',
                           '7z' => 'application/x-7z-compressed',
                           'abw' => 'application/x-abiword',
                           'dmg' => 'application/x-apple-diskimage',
                           'bcpio' => 'application/x-bcpio',
                           'torrent' => 'application/x-bittorrent',
                           'cab' => 'application/x-cab',
                           'cbr' => 'application/x-cbr',
                           'cbz' => 'application/x-cbz',
                           'cdf' => 'application/x-cdf',
                           'cda' => 'application/x-cdf',
                           'vcd' => 'application/x-cdlink',
                           'pgn' => 'application/x-chess-pgn',
                           'cpio' => 'application/x-cpio',
                           'csh' => 'text/x-csh',
                           'deb' => 'application/x-debian-package',
                           'udeb' => 'application/x-debian-package',
                           'dcr' => 'application/x-director',
                           'dir' => 'application/x-director',
                           'dxr' => 'application/x-director',
                           'dms' => 'application/x-dms',
                           'wad' => 'application/x-doom',
                           'dvi' => 'application/x-dvi',
                           'rhtml' => 'application/x-httpd-eruby',
                           'pfa' => 'application/x-font',
                           'pfb' => 'application/x-font',
                           'gsf' => 'application/x-font',
                           'pcf' => 'application/x-font',
                           'pcf.Z' => 'application/x-font',
                           'mm' => 'application/x-freemind',
                           'gnumeric' => 'application/x-gnumeric',
                           'sgf' => 'application/x-go-sgf',
                           'gcf' => 'application/x-graphing-calculator',
                           'gtar' => 'application/x-gtar',
                           'tgz' => 'application/x-gtar',
                           'taz' => 'application/x-gtar',
                           'hdf' => 'application/x-hdf',
                           'phtml' => 'application/x-httpd-php',
                           'pht' => 'application/x-httpd-php',
                           'php' => 'application/x-httpd-php',
                           'phps' => 'application/x-httpd-php-source',
                           'php3' => 'application/x-httpd-php3',
                           'php3p' => 'application/x-httpd-php3-preprocessed',
                           'php4' => 'application/x-httpd-php4',
                           'php5' => 'application/x-httpd-php5',
                           'ica' => 'application/x-ica',
                           'info' => 'application/x-info',
                           'ins' => 'application/x-internet-signup',
                           'isp' => 'application/x-internet-signup',
                           'iii' => 'application/x-iphone',
                           'iso' => 'application/x-iso9660-image',
                           'jam' => 'application/x-jam',
                           'jnlp' => 'application/x-java-jnlp-file',
                           'jmz' => 'application/x-jmol',
                           'chrt' => 'application/x-kchart',
                           'kil' => 'application/x-killustrator',
                           'skp' => 'application/x-koan',
                           'skd' => 'application/x-koan',
                           'skt' => 'application/x-koan',
                           'skm' => 'application/x-koan',
                           'kpr' => 'application/x-kpresenter',
                           'kpt' => 'application/x-kpresenter',
                           'ksp' => 'application/x-kspread',
                           'kwd' => 'application/x-kword',
                           'kwt' => 'application/x-kword',
                           'latex' => 'application/x-latex',
                           'lha' => 'application/x-lha',
                           'lyx' => 'application/x-lyx',
                           'lzh' => 'application/x-lzh',
                           'lzx' => 'application/x-lzx',
                           'frm' => 'application/x-maker',
                           'maker' => 'application/x-maker',
                           'frame' => 'application/x-maker',
                           'fm' => 'application/x-maker',
                           'fb' => 'application/x-maker',
                           'book' => 'application/x-maker',
                           'fbdoc' => 'application/x-maker',
                           'mif' => 'application/x-mif',
                           'wmd' => 'application/x-ms-wmd',
                           'wmz' => 'application/x-ms-wmz',
                           'com' => 'application/x-msdos-program',
                           'exe' => 'application/x-msdos-program',
                           'bat' => 'application/x-msdos-program',
                           'dll' => 'application/x-msdos-program',
                           'msi' => 'application/x-msi',
                           'nc' => 'application/x-netcdf',
                           'pac' => 'application/x-ns-proxy-autoconfig',
                           'dat' => 'application/x-ns-proxy-autoconfig',
                           'nwc' => 'application/x-nwc',
                           'o' => 'application/x-object',
                           'oza' => 'application/x-oz-application',
                           'p7r' => 'application/x-pkcs7-certreqresp',
                           'crl' => 'application/x-pkcs7-crl',
                           'pyc' => 'application/x-python-code',
                           'pyo' => 'application/x-python-code',
                           'qgs' => 'application/x-qgis',
                           'shp' => 'application/x-qgis',
                           'shx' => 'application/x-qgis',
                           'qtl' => 'application/x-quicktimeplayer',
                           'rpm' => 'application/x-redhat-package-manager',
                           'rb' => 'application/x-ruby',
                           'sh' => 'text/x-sh',
                           'shar' => 'application/x-shar',
                           'swf' => 'application/x-shockwave-flash',
                           'swfl' => 'application/x-shockwave-flash',
                           'scr' => 'application/x-silverlight',
                           'sit' => 'application/x-stuffit',
                           'sitx' => 'application/x-stuffit',
                           'sv4cpio' => 'application/x-sv4cpio',
                           'sv4crc' => 'application/x-sv4crc',
                           'tar' => 'application/x-tar',
                           'tcl' => 'text/x-tcl',
                           'gf' => 'application/x-tex-gf',
                           'pk' => 'application/x-tex-pk',
                           'texinfo' => 'application/x-texinfo',
                           'texi' => 'application/x-texinfo',
                           '~' => 'application/x-trash',
                           '%' => 'application/x-trash',
                           'bak' => 'application/x-trash',
                           'old' => 'application/x-trash',
                           'sik' => 'application/x-trash',
                           't' => 'application/x-troff',
                           'tr' => 'application/x-troff',
                           'roff' => 'application/x-troff',
                           'man' => 'application/x-troff-man',
                           'me' => 'application/x-troff-me',
                           'ms' => 'application/x-troff-ms',
                           'ustar' => 'application/x-ustar',
                           'src' => 'application/x-wais-source',
                           'wz' => 'application/x-wingz',
                           'crt' => 'application/x-x509-ca-cert',
                           'xcf' => 'application/x-xcf',
                           'fig' => 'application/x-xfig',
                           'xpi' => 'application/x-xpinstall',
                           'amr' => 'audio/amr',
                           'awb' => 'audio/amr-wb',
                           'axa' => 'audio/annodex',
                           'au' => 'audio/basic',
                           'snd' => 'audio/basic',
                           'flac' => 'audio/flac',
                           'mid' => 'audio/midi',
                           'midi' => 'audio/midi',
                           'kar' => 'audio/midi',
                           'mpga' => 'audio/mpeg',
                           'mpega' => 'audio/mpeg',
                           'mp2' => 'audio/mpeg',
                           'mp3' => 'audio/mpeg',
                           'm4a' => 'audio/mpeg',
                           'm3u' => 'audio/x-mpegurl',
                           'oga' => 'audio/ogg',
                           'ogg' => 'audio/ogg',
                           'spx' => 'audio/ogg',
                           'sid' => 'audio/prs.sid',
                           'aif' => 'audio/x-aiff',
                           'aiff' => 'audio/x-aiff',
                           'aifc' => 'audio/x-aiff',
                           'gsm' => 'audio/x-gsm',
                           'wma' => 'audio/x-ms-wma',
                           'wax' => 'audio/x-ms-wax',
                           'ra' => 'audio/x-realaudio',
                           'rm' => 'audio/x-pn-realaudio',
                           'ram' => 'audio/x-pn-realaudio',
                           'pls' => 'audio/x-scpls',
                           'sd2' => 'audio/x-sd2',
                           'wav' => 'audio/x-wav',
                           'alc' => 'chemical/x-alchemy',
                           'cac' => 'chemical/x-cache',
                           'cache' => 'chemical/x-cache',
                           'csf' => 'chemical/x-cache-csf',
                           'cbin' => 'chemical/x-cactvs-binary',
                           'cascii' => 'chemical/x-cactvs-binary',
                           'ctab' => 'chemical/x-cactvs-binary',
                           'cdx' => 'chemical/x-cdx',
                           'cer' => 'chemical/x-cerius',
                           'c3d' => 'chemical/x-chem3d',
                           'chm' => 'chemical/x-chemdraw',
                           'cif' => 'chemical/x-cif',
                           'cmdf' => 'chemical/x-cmdf',
                           'cml' => 'chemical/x-cml',
                           'cpa' => 'chemical/x-compass',
                           'bsd' => 'chemical/x-crossfire',
                           'csml' => 'chemical/x-csml',
                           'csm' => 'chemical/x-csml',
                           'ctx' => 'chemical/x-ctx',
                           'cxf' => 'chemical/x-cxf',
                           'cef' => 'chemical/x-cxf',
                           'emb' => 'chemical/x-embl-dl-nucleotide',
                           'embl' => 'chemical/x-embl-dl-nucleotide',
                           'spc' => 'chemical/x-galactic-spc',
                           'inp' => 'chemical/x-gamess-input',
                           'gam' => 'chemical/x-gamess-input',
                           'gamin' => 'chemical/x-gamess-input',
                           'fch' => 'chemical/x-gaussian-checkpoint',
                           'fchk' => 'chemical/x-gaussian-checkpoint',
                           'cub' => 'chemical/x-gaussian-cube',
                           'gau' => 'chemical/x-gaussian-input',
                           'gjc' => 'chemical/x-gaussian-input',
                           'gjf' => 'chemical/x-gaussian-input',
                           'gal' => 'chemical/x-gaussian-log',
                           'gcg' => 'chemical/x-gcg8-sequence',
                           'gen' => 'chemical/x-genbank',
                           'hin' => 'chemical/x-hin',
                           'istr' => 'chemical/x-isostar',
                           'ist' => 'chemical/x-isostar',
                           'jdx' => 'chemical/x-jcamp-dx',
                           'dx' => 'chemical/x-jcamp-dx',
                           'kin' => 'chemical/x-kinemage',
                           'mcm' => 'chemical/x-macmolecule',
                           'mmd' => 'chemical/x-macromodel-input',
                           'mmod' => 'chemical/x-macromodel-input',
                           'mol' => 'chemical/x-mdl-molfile',
                           'rd' => 'chemical/x-mdl-rdfile',
                           'rxn' => 'chemical/x-mdl-rxnfile',
                           'sd' => 'chemical/x-mdl-sdfile',
                           'tgf' => 'chemical/x-mdl-tgf',
                           'mcif' => 'chemical/x-mmcif',
                           'mol2' => 'chemical/x-mol2',
                           'b' => 'chemical/x-molconn-Z',
                           'gpt' => 'chemical/x-mopac-graph',
                           'mop' => 'chemical/x-mopac-input',
                           'mopcrt' => 'chemical/x-mopac-input',
                           'mpc' => 'chemical/x-mopac-input',
                           'zmt' => 'chemical/x-mopac-input',
                           'moo' => 'chemical/x-mopac-out',
                           'mvb' => 'chemical/x-mopac-vib',
                           'asn' => 'chemical/x-ncbi-asn1-spec',
                           'prt' => 'chemical/x-ncbi-asn1-ascii',
                           'ent' => 'chemical/x-pdb',
                           'val' => 'chemical/x-ncbi-asn1-binary',
                           'aso' => 'chemical/x-ncbi-asn1-binary',
                           'pdb' => 'chemical/x-pdb',
                           'ros' => 'chemical/x-rosdal',
                           'sw' => 'chemical/x-swissprot',
                           'vms' => 'chemical/x-vamas-iso14976',
                           'vmd' => 'chemical/x-vmd',
                           'xtel' => 'chemical/x-xtel',
                           'xyz' => 'chemical/x-xyz',
                           'gif' => 'image/gif',
                           'ief' => 'image/ief',
                           'jpeg' => 'image/jpeg',
                           'jpg' => 'image/jpeg',
                           'jpe' => 'image/jpeg',
                           'pcx' => 'image/pcx',
                           'png' => 'image/png',
                           'svg' => 'image/svg+xml',
                           'svgz' => 'image/svg+xml',
                           'tiff' => 'image/tiff',
                           'tif' => 'image/tiff',
                           'djvu' => 'image/vnd.djvu',
                           'djv' => 'image/vnd.djvu',
                           'wbmp' => 'image/vnd.wap.wbmp',
                           'cr2' => 'image/x-canon-cr2',
                           'crw' => 'image/x-canon-crw',
                           'ras' => 'image/x-cmu-raster',
                           'cdr' => 'image/x-coreldraw',
                           'pat' => 'image/x-coreldrawpattern',
                           'cdt' => 'image/x-coreldrawtemplate',
                           'erf' => 'image/x-epson-erf',
                           'ico' => 'image/x-icon',
                           'art' => 'image/x-jg',
                           'jng' => 'image/x-jng',
                           'bmp' => 'image/x-ms-bmp',
                           'nef' => 'image/x-nikon-nef',
                           'orf' => 'image/x-olympus-orf',
                           'psd' => 'image/x-photoshop',
                           'pnm' => 'image/x-portable-anymap',
                           'pbm' => 'image/x-portable-bitmap',
                           'pgm' => 'image/x-portable-graymap',
                           'ppm' => 'image/x-portable-pixmap',
                           'rgb' => 'image/x-rgb',
                           'xbm' => 'image/x-xbitmap',
                           'xpm' => 'image/x-xpixmap',
                           'xwd' => 'image/x-xwindowdump',
                           'eml' => 'message/rfc822',
                           'igs' => 'model/iges',
                           'iges' => 'model/iges',
                           'msh' => 'model/mesh',
                           'mesh' => 'model/mesh',
                           'silo' => 'model/mesh',
                           'wrl' => 'x-world/x-vrml',
                           'vrml' => 'x-world/x-vrml',
                           'x3dv' => 'model/x3d+vrml',
                           'x3d' => 'model/x3d+xml',
                           'x3db' => 'model/x3d+binary',
                           'manifest' => 'text/cache-manifest',
                           'ics' => 'text/calendar',
                           'icz' => 'text/calendar',
                           'css' => 'text/css',
                           'csv' => 'text/csv',
                           '323' => 'text/h323',
                           'html' => 'text/html',
                           'htm' => 'text/html',
                           'shtml' => 'text/html',
                           'uls' => 'text/iuls',
                           'mml' => 'text/mathml',
                           'asc' => 'text/plain',
                           'txt' => 'text/plain',
                           'text' => 'text/plain',
                           'pot' => 'text/plain',
                           'brf' => 'text/plain',
                           'rtx' => 'text/richtext',
                           'sct' => 'text/scriptlet',
                           'wsc' => 'text/scriptlet',
                           'tm' => 'text/texmacs',
                           'ts' => 'text/texmacs',
                           'tsv' => 'text/tab-separated-values',
                           'jad' => 'text/vnd.sun.j2me.app-descriptor',
                           'wml' => 'text/vnd.wap.wml',
                           'wmls' => 'text/vnd.wap.wmlscript',
                           'bib' => 'text/x-bibtex',
                           'boo' => 'text/x-boo',
                           'h++' => 'text/x-c++hdr',
                           'hpp' => 'text/x-c++hdr',
                           'hxx' => 'text/x-c++hdr',
                           'hh' => 'text/x-c++hdr',
                           'c++' => 'text/x-c++src',
                           'cpp' => 'text/x-c++src',
                           'cxx' => 'text/x-c++src',
                           'cc' => 'text/x-c++src',
                           'h' => 'text/x-chdr',
                           'htc' => 'text/x-component',
                           'c' => 'text/x-csrc',
                           'd' => 'text/x-dsrc',
                           'diff' => 'text/x-diff',
                           'patch' => 'text/x-diff',
                           'hs' => 'text/x-haskell',
                           'java' => 'text/x-java',
                           'lhs' => 'text/x-literate-haskell',
                           'moc' => 'text/x-moc',
                           'p' => 'text/x-pascal',
                           'pas' => 'text/x-pascal',
                           'gcd' => 'text/x-pcs-gcd',
                           'pl' => 'text/x-perl',
                           'pm' => 'text/x-perl',
                           'py' => 'text/x-python',
                           'scala' => 'text/x-scala',
                           'etx' => 'text/x-setext',
                           'tk' => 'text/x-tcl',
                           'tex' => 'text/x-tex',
                           'ltx' => 'text/x-tex',
                           'sty' => 'text/x-tex',
                           'cls' => 'text/x-tex',
                           'vcs' => 'text/x-vcalendar',
                           'vcf' => 'text/x-vcard',
                           '3gp' => 'video/3gpp',
                           'axv' => 'video/annodex',
                           'dl' => 'video/dl',
                           'dif' => 'video/dv',
                           'dv' => 'video/dv',
                           'fli' => 'video/fli',
                           'gl' => 'video/gl',
                           'mpeg' => 'video/mpeg',
                           'mpg' => 'video/mpeg',
                           'mpe' => 'video/mpeg',
                           'mp4' => 'video/mp4',
                           'qt' => 'video/quicktime',
                           'mov' => 'video/quicktime',
                           'ogv' => 'video/ogg',
                           'mxu' => 'video/vnd.mpegurl',
                           'flv' => 'video/x-flv',
                           'lsf' => 'video/x-la-asf',
                           'lsx' => 'video/x-la-asf',
                           'mng' => 'video/x-mng',
                           'asf' => 'video/x-ms-asf',
                           'asx' => 'video/x-ms-asf',
                           'wm' => 'video/x-ms-wm',
                           'wmv' => 'video/x-ms-wmv',
                           'wmx' => 'video/x-ms-wmx',
                           'wvx' => 'video/x-ms-wvx',
                           'avi' => 'video/x-msvideo',
                           'movie' => 'video/x-sgi-movie',
                           'mpv' => 'video/x-matroska',
                           'mkv' => 'video/x-matroska',
                           'ice' => 'x-conference/x-cooltalk',
                           'sisx' => 'x-epoc/x-sisx-app',
                           'vrm' => 'x-world/x-vrml',
                           );

}

