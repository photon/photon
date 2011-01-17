<?php

/**
 * Small tester script to run the server and test things.
 */
namespace Foobar
{
    class Foo
    {
        public function hello($req, $match)
        {
            return new \photon\http\Response('Hello World!' . "\n");
        }
    }
}

namespace
{
    include '../src/photon/core.php';
    include '../src/photon/http.php';
    include '../src/photon/mongrel2.php';
    include '../src/photon/config.php';
    include '../src/photon/server.php';
    include '../src/photon/http/response.php';
    include '../src/photon/http/multipartparser.php';

    use photon\config\Container as Conf;
    $urls = array(array('regex' => '#^/handlertest/foo$#',
                        'base' => '',
                        'model' => '\Foobar\Foo',
                        'method' => 'hello')
                  );
    Conf::load(array('urls' => $urls));
    $server_conf = array('sub_addr' => 'tcp://127.0.0.1:9997',
                         'pub_addr' => 'tcp://127.0.0.1:9997'
                         );
    $server_conf = array('pub_addr' => 'ipc://handler-res',
                         'sub_addr' => 'ipc://mongrel-req'
                         );
    $server = new \photon\server\TestServer($server_conf);
    $server->start();
}
