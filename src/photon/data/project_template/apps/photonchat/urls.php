<?php

return array(
             array('regex' => '#^/$#',
                   'view' => array('\photonchat\views\Chat', 'home'),
                   'name' => 'photonchat_home',
                   ),
             array('regex' => '#^/media/photonchat/(.*)$#',
                   'view' => array('\photon\views\AssetDir', 'serve'),
                   'name' => 'photonchat_assets',
                   'params' => __DIR__ . '/www/media/photonchat',
                   ),
             );
