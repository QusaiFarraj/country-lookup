<?php

ini_set('display_errors', '1');

// load dependancies
require __DIR__ . '/../vendor/autoload.php';

use Slim\App;
use \GuzzleHttp\Client;

// load configs
$config = require __DIR__ . '/config.php';

// Init app
$app = new App($config);

// DI
$container = $app->getContainer();

// Adding CORS Middleware
$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);

    $sites = 'http://localhost:3000';
    // if($this->configs['env'] == 'production')
    //     $sites = 'https://widgets.cfxtrading.com, https://apis.cfxtrading.com';    

    return $response
            ->withHeader('Access-Control-Allow-Origin', $sites)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Expose-Headers', 'X-CSRF-NAME, X-CSRF-VALUE');
});

// Routes
$app->get('/', function ($request, $response) use ($container){
    $index = $container->get('config')['files']['index'];
    $file = file_get_contents($index);
    // var_dump($file); die();
    $response = $response->write($file);
    return $response->withHeader('Content-Type','text/html');
    // return $response->write('Welcome to Country Lookup Api');
});
$app->get('/static/css/main.e53e058f.css', function ($request, $response) use ($container){
    $file = file_get_contents($container->get('config')['files']['css']);
    // var_dump($file); die();
    $response = $response->write($file);
    return $response->withHeader('Content-Type','text/css');
});
$app->get('/static/js/main.d2e0aac2.js', function ($request, $response) use ($container){
    $file = file_get_contents($container->get('config')['files']['js']);
    // var_dump($file); die();
    $response = $response->write($file);
    return $response->withHeader('Content-Type','application/javascript');
});

$app->get('/lookup', function ($request, $response) use ($container){

    $api = $container->get('config')['api'];
    $params = $request->getQueryParams();

    // check if the request have query params / lookup data
    if (count($params) < 1) {
        return sendErrorMsg(400, 'No data has been sent with the request', $response);
    }

    if (isset($params['fullname']) && $params['fullname']) {
        $url = $api."/name/$params[fullname]?fullText=true";
    } elseif (isset($params['code'])) {
        $url = $api."/alpha/$params[code]";
    } elseif (isset($params['name'])) {
        $url = $api."/name/$params[name]";
    } else {
        return sendErrorMsg(400, 'Request params must be one of these [`name`,`code`,`fullname`]', $response);
    }

    return getCountriesInfo($url, $response);
});


$app->get('/all', function ($request, $response) use ($container){
    $api = $container->get('config')['api'];
    return getCountriesInfo($api, $response);
});

/*
* Get counrty /countires info
*/
function getCountriesInfo($url=null, $response=null)
{   
    if (!isset($url) && !isset($response)) {
        throw new Exception("param `url` can't be null. You must send a `url` to make calls");
    }

    $client = new Client();
    try {
        $res = $client->request('GET', $url);
    } catch (Exception $e) {
        $msg = explode('response:', $e->getMessage());
        $msg = json_decode($msg[1], true);
        return sendErrorMsg($msg['status'], $msg['message'], $response);
    }

    $status = $res->getStatusCode();
    $data = json_decode($res->getBody(), true);

    return $response->withJson($data, $status);
}

/**
* Set up the error msg and convert to JSON then return a JOSN response
*/
function sendErrorMsg($errorCode=null ,$msg=null, $response=null)
{
    if (!isset($errorCode) && !isset($msg) && !isset($response)) {
        throw new Exception("parameters: `errorCode=$errorCode` , `msg=$msg` and `response=$response` must not be null.");
    }

    switch ($errorCode) {
        case 400:
            $msg = 'Bad Input! ' . $msg;
            break;
        case 500:
            $msg = 'Server Error! ' . $msg;
            break;
        default:
            $errorCode = $errorCode;
            $msg = $msg;
            break;
    }

    $data = [
        'errorCode' => $errorCode,
        'errorMsg' => $msg,
    ];

    return $response->withJson($data, $errorCode);
}
