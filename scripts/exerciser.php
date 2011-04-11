<?php
/**
 * Act like a Mongrel2 server and push a request to the handler.
 * It prints the raw answer.
 */
$pub_addr = 'ipc://handler-res';
$sub_addr = 'ipc://mongrel-req';
$sub_addr = 'tcp://127.0.0.1:9997';
$pub_addr = 'tcp://127.0.0.1:9996';

$n = 10000;

$payload = file_get_contents($argv[1]);
$batch = (isset($argv[2]));
$ctx = new ZMQContext(1);
$serv = $ctx->getSocket(ZMQ::SOCKET_DOWNSTREAM);
$serv->bind($sub_addr);
$get = $ctx->getSocket(ZMQ::SOCKET_SUB);
$get->bind($pub_addr);
$get->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, ''); 
$start = microtime(true);
if ($batch) {
    // Batching all the requests and then getting the answers from the
    // queue is way, way, way faster. Even for larger messages, the
    // generation of the answers is most likely using the CPU a bit
    // better and the answers may not come in the same order.
    $i = 0;
    while ($i < $n) {
        $serv->send($payload);
        $i++;
    }
    $i = 0;
    while ($i < $n) {
        $message = $get->recv();
        $i++;
    }
} else {
    $i = 0;
    while ($i < $n) {
        $serv->send($payload);
        $message = $get->recv();
        $i++;
        usleep(1000);
    }
}
$end = microtime(true);
print "Done.\n";
print "Last message:\n";
print $message;
print "\n\n";
printf("Elapsed time: %ss\n", ($end-$start));
printf("Req/s: %s\n", (float)$n/($end-$start));


