<?php

use Jazor\Http\Request;
use Jazor\Uri;

include_once './vendor/autoload.php';
include_once './helper.php';

$hubHost = 'registry-1.docker.io';
$authHost = 'auth.docker.io';
$authBase = 'https://auth.docker.io';

$TUNNEL_PROXY_START = '/TUNNEL_PROXY_START/';
$TUNNEL_PROXY_END = '/TUNNEL_PROXY_END';

$headers = get_request_headers();
unset($headers['Host']);
unset($headers['Accept-Encoding']);

$method = $_SERVER['REQUEST_METHOD'];
$pathAndQuery = $_SERVER['REQUEST_URI'];
$host = $_SERVER['HTTP_HOST'];
$scheme = $_SERVER['REQUEST_SCHEME'] ?? ($_SERVER['HTTPS'] === 'on' ? 'https' : 'http');

$localBase = $scheme . '://' . $host;

$newHost = strpos($pathAndQuery, '/token?') === 0 ? $authHost : $hubHost;

$remoteScheme = 'https';

if (strpos($pathAndQuery, $TUNNEL_PROXY_START) === 0) {
    $idx = strpos($pathAndQuery, $TUNNEL_PROXY_END);
    $proxyInfo = substr($pathAndQuery, 20, $idx - 20);

    $pathAndQuery = substr($pathAndQuery, $idx + 17);

    $idx = strpos($proxyInfo, '/');

    $remoteScheme = substr($proxyInfo, 0, $idx);
    $newHost = substr($proxyInfo, $idx + 1);

    if (strpos($newHost, 'docker.com') !== strlen($newHost) - 10) exit();

}

$newUri = $remoteScheme . '://' . $newHost . $pathAndQuery;


$req = new Request($newUri, $method);

foreach ($headers as $name => $value) $req->setHeader($name, $value);

$req->setHeader('Connection', 'close');

$response = $req->getResponse(['sslVerifyPeer' => false, 'sslVerifyHost' => false]);


$headers = $response->getHeaders();


header('HTTP/1.1 ' . $response->getStatusCode() . ' ' . $response->getStatusText());

$contentType = $response->getContentType();
if ($contentType) send_header('Content-Type', $contentType);
foreach ($headers as $name => $value) {
    $values = (array)$value;
    if (strpos($name, 'Docker-') === 0) {
        foreach ($values as $v) send_header($name, $v);
    }
}

$auth = $response->getSingletHeader("Www-Authenticate");

if ($auth) {
    $new_auth = str_replace($authBase, $localBase, $auth);
    send_header("Www-Authenticate", str_replace($authBase, $localBase, $auth));
}


$location = $response->getLocation();

if ($location) {
    $uri = new Uri($location);
    if ($uri->isFullUrl()) {
        $authority = $uri->getAuthority();
        $newUri = sprintf('%s%s%s/%s%s%s%s', $localBase, $TUNNEL_PROXY_START, $uri->getSchema(), $authority, $TUNNEL_PROXY_END, $uri->getPathAndQuery(), $uri->getAnchor());
        send_header('Location', $newUri);
    }
}

$contentLength = $response->getContentLength();
if ($contentLength === 0) exit();

if ($method === 'HEAD') {
    if ($contentLength >= 0) send_header('Content-Length', $contentLength);
    exit();
}

if ($contentLength >= 0 && !$response->getContentEncoding()) {
    send_header('Content-Length', $contentLength);
    if ($contentLength > 0) $response->sink('php://output');
    return;
}

echo $response->getBody();
