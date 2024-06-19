<?php

use Jazor\Http\Request;
use Jazor\Uri;

include_once __DIR__ . '/./vendor/autoload.php';
include_once __DIR__ . '/./helper.php';

const HUB_HOST = 'registry-1.docker.io';
const AUTH_HOST = 'auth.docker.io';
const AUTH_BASE = 'https://auth.docker.io';
const TUNNEL_PROXY_START= '/TUNNEL_PROXY_START/';

$headers = get_request_headers();
unset($headers['Host']);
unset($headers['Accept-Encoding']);

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$host = $_SERVER['HTTP_HOST'];
$scheme = $_SERVER['REQUEST_SCHEME'] ?? ($_SERVER['HTTPS'] === 'on' ? 'https' : 'http');

#you can set up 'localBase' use other method, e.g. http header
$localBase = $scheme . '://' . $host;

#ensure host to connect
$newHost = strpos($requestUri, '/token?') === 0 ? AUTH_HOST : HUB_HOST;

$remoteScheme = 'https';

#inner proxy setup
if (strpos($requestUri, TUNNEL_PROXY_START) === 0) {

    $url = urldecode(substr($requestUri, 20));
    $uri = new Uri($url);

    #block proxy, just proxy for docker.com
    $hostName = $uri->getAuthority();
    if (strpos($hostName, '.docker.com') !== strlen($hostName) - 11) exit();
    $newUri = $url;
}else{
    #get uri for proxy
    $newUri = $remoteScheme . '://' . $newHost . $requestUri;
}

#start proxy
$req = new Request($newUri, $method);

foreach ($headers as $name => $value) $req->setHeader($name, $value);

$req->setHeader('Connection', 'close');

#ignore ssl error
$response = $req->getResponse(['sslVerifyPeer' => false, 'sslVerifyHost' => false]);

$headers = $response->getHeaders();

#passthrough status code
header('HTTP/1.1 ' . $response->getStatusCode() . ' ' . $response->getStatusText());

$contentType = $response->getContentType();
if ($contentType) send_header('Content-Type', $contentType);

#passthrough docker header
foreach ($headers as $name => $value) {
    $values = (array)$value;
    if (strpos($name, 'Docker-') === 0) {
        foreach ($values as $v) send_header($name, $v);
    }
}

#redirect authentication
$auth = $response->getSingletHeader("Www-Authenticate");
if ($auth) {
    send_header("Www-Authenticate", str_replace(AUTH_BASE, $localBase, $auth));
}

#redirect location with inner proxy
$location = $response->getLocation();
if ($location) {
    $uri = new Uri($location);
    if ($uri->isFullUrl()) {
        $authority = $uri->getAuthority();
        $newUri = sprintf('%s%s%s', $localBase, TUNNEL_PROXY_START, urlencode($location));
        send_header('Location', $newUri);
    }
}

$contentLength = $response->getContentLength();
if ($contentLength === 0 || $method === 'HEAD') {
    if ($contentLength >= 0) send_header('Content-Length', $contentLength);
    exit();
}

#sink to php://output
if ($contentLength >= 0 && !$response->getContentEncoding()) {
    send_header('Content-Length', $contentLength);
    if ($contentLength > 0) $response->sink('php://output');
    return;
}

#send response body to client
echo $response->getBody();
