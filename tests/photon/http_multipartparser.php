<?php

// TODO: Convert these tests to use a real unit testing framework.
//       This can be PHPUnit as it improved a lot (but I hope it is not
//       to verbose and "heavy".

include_once __DIR__ . '/../../src/photon/http/multipartparser.php';

$datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'r');
$boundary = '---------------------------10102754414578508781458777923';

$iterator = new \photon\http\multipartparser\BoundaryIter($datafile, $boundary);

while (false !== ($part=$iterator->getPart())) {
    //    print_r($part);
}
fclose($datafile);
$d = fopen(__DIR__ . '/../data/multi_video.upload', 'r');
$headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923');

$parser = new \photon\http\multipartparser\MultiPartParser($headers, $d);
$fields = $parser->parse();
$i = 1;
foreach ($fields as $field) {
    //file_put_contents($field[0]['name'].$i, $field[1]->read());
    $i++;
}
fclose($d);
print $i."\n";
