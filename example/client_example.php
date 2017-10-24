<?php

require_once("../vendor/autoload.php");

$client = new pavelk\JsonRPC\Client("http://www.example.com");

// you can use the magic method shortcut to make requests...
$response = $client->foo(); // --> "bar"

// ...and it supports positional arguments
$response = $client->sum(1, 2, 3); // --> 6

// for named parameters you need to make a request object and send it with the client
$request  = new pavelk\JsonRPC\Client\Request('makeFullName', array('last_name' => 'Fry', 'first_name' => 'Philip J.'));
$response = $client->sendRequest($request); // --> "Philip J. Fry"

// notifications should be specified when you create a request object
$request  = new pavelk\JsonRPC\Client\Request('notify', 10, true);
$response = $client->sendNotify($request); // --> true (on success)

// batches are sent as an array of requests, and are processed and returned in order (with no notifications)
$requests   = array();
$requests[] = new pavelk\JsonRPC\Client\Request('makeFullName', array('last_name' => 'Fry', 'first_name' => 'Philip'));
$requests[] = new pavelk\JsonRPC\Client\Request('notify', 10, true);
$requests[] = new pavelk\JsonRPC\Client\Request('isEven', 11);
$response   = $client->sendBatch($requests);
print_r($response->results); // array( "Philip J. Fry", false)