<?php

require 'vendor/autoload.php' ;

$app = new \Slim\Slim();

/*$app->get('/hello/:name', function ($name) {          //GET - localhost/slimtest/hello/vaibhav works for this
    echo "Hello " . $name;
});*/

$app->post('/parse', function() use ($app) {
    global $djml ; 
    $djml=$app->request()->post('djml');
    //echo $djml ;
});

$app->run();
include('DJMLParser.php');

?>