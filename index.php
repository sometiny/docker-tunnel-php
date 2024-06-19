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

#you can set up 'localBase' use other method, e.g. http header
$localBase = $scheme . '://' . $host;

#ensure host to connect
$newHost = strpos($pathAndQuery, '/token?') === 0 ? $authHost : $hubHost;

$remoteScheme = 'https';

#inner proxy setup
if (strpos($pathAndQuery, $TUNNEL_PROXY_START) === 0) {
    $idx = strpos($pathAndQuery, $TUNNEL_PROXY_END);
    if($idx === false) exit();

    $proxyInfo = substr($pathAndQuery, 20, $idx - 20);

    $pathAndQuery = substr($pathAndQuery, $idx + 17);

    $idx = strpos($proxyInfo, '/');
    if($idx === false) exit();

    $remoteScheme = substr($proxyInfo, 0, $idx);
    $newHost = substr($proxyInfo, $idx + 1);

    #block proxy, just proxy for docker.com
    if (strpos($newHost, '.docker.com') !== strlen($newHost) - 11) exit();

}

#get uri for proxy
$newUri = $remoteScheme . '://' . $newHost . $pathAndQuery;

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
    $new_auth = str_replace($authBase, $localBase, $auth);
    send_header("Www-Authenticate", str_replace($authBase, $localBase, $auth));
}

#redirect location with inner proxy
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
