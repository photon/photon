<?php
Phar::mapPhar('%s');
set_include_path('phar://%s' . PATH_SEPARATOR . get_include_path());
include 'phar://%s/vendor/photon/photon/src/photon.php';
__HALT_COMPILER();
