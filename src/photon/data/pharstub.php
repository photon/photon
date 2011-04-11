<?php
Phar::mapPhar('%s');
set_include_path('phar://%s' . PATH_SEPARATOR .
                 'phar://%s/apps' . PATH_SEPARATOR . 
                 get_include_path());
include 'photon.php';
__HALT_COMPILER();