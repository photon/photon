<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * The project URLs file "includes" the urls of your apps at the right
 * points in the URL space of your project.
 */

// As an example, we make a very small response for the base of the
// demo directly available through a closure. This shows that the
// view parameter of a mapping is just a callable.

$content = '<html><head><title>Photon Demo</title></head><body>
<h1>PhotonDemo</h1>
<ul>
<li><a href="%s">Chat demo</a>, open multiple windows to create multiple connections.</a></li>
<li><a href="%s">Hello world</a>, yes needed.</a></li>
<li><a href="%s">Hello Photon</a>, change the end of the url to change the name.</a></li>
</ul>';
                        $url = 


$http = array(
              array('regex' => '#^/hello#',
                    'sub' => include __DIR__ . '/apps/helloworld/urls.php'
                    ),
              array('regex' => '#^/chat#',
                    'sub' => include __DIR__ . '/apps/photonchat/urls.php'
                    ),
              array('regex' => '#^/$#',
                    'view' => function ($req, $match) use ($content) {
                        // We redirect to the hello world application
                        // As you can see, you can have minimal views
                        // directly within your url definition, for
                        // example to output a robots.txt
                        $content = sprintf($content,
                           \photon\core\URL::forView('photonchat_home'),
                           \photon\core\URL::forView('helloworld_index'),
                           \photon\core\URL::forView('helloworld_you', 'Photon'));
                        return new \photon\http\Response($content);
                    },
                    'name' => 'yourproject_home',
                    ),
              );

$jssocket = include __DIR__ . '/apps/photonchat/urls_jssocket.php';

return array_merge($http, $jssocket);



