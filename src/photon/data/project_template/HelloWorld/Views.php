<?php

namespace HelloWorld\Views;
use \photon\core\URL;
use \photon\http\response\Redirect;
use \photon\shortcuts;

class Base {

    public function redirect($request, $match)
    {
        $url = URL::forView('hello_view');
        return new Redirect($url);
    }

    public function hello($request, $match)
    {
        $parms = array(
            'name' => 'John DOE',
        );
        return shortcuts\Template::RenderToResponse('hello.html',
                                                     $parms,
                                                     $request);
    }

    public function hello_doe($request, $match)
    {
        $parms = array(
            'name' => urldecode($match[1]),
        );
        return shortcuts\Template::RenderToResponse('hello.html',
                                                     $parms,
                                                     $request);
    }
}
