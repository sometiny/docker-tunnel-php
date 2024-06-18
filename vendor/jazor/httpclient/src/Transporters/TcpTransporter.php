<?php


namespace Jazor\Http\Transporters;


use Jazor\Http\Cookies\CookieContainer;
use Jazor\Http\Request;
use Jazor\Http\Response;
use Jazor\Http\Transporter;
use Jazor\Uri;

class TcpTransporter extends Transporter
{

    private $client;

    /**
     * TcpTransporter constructor.
     * @param array|null $options
     * @param CookieContainer|null $cookieContainer
     */
    public function __construct(?array $options = null, ?CookieContainer $cookieContainer = null)
    {
        parent::__construct($options, $cookieContainer);
    }

    private function getAddress(Uri $uri): string
    {
        return $this->options['remote']
            ?? sprintf('%s://%s', $uri->getSchema() === 'http' ? 'tcp' : 'tls', $uri->getHostAndPort());
    }

    /**
     * @param Uri $uri
     * @return false|resource
     * @throws \Exception
     */
    private function createSocket(Uri $uri)
    {
        $contextSSL = [
            'verify_peer' => $this->sslVerifyPeer,
            'verify_peer_name' => $this->sslVerifyHost,
            'SNI_enabled' => true
        ];


        $client = stream_socket_client(
            $this->getAddress($uri),
            $errorCode,
            $errorMessage,
            $this->timeout,
            STREAM_CLIENT_CONNECT, stream_context_create([
                'socket' => ['tcp_nodelay' => true,],
                'ssl' => $contextSSL,
            ])
        );

        if ($client === false) {
            throw new \Exception(sprintf('socket error: %s,%s', $errorCode, $errorMessage));
        }

        stream_set_timeout($client, $this->timeout, 0);

        return $client;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function execute(Request $request): Response
    {
        $uri = $request->getUri();
        $client = $this->createSocket($uri);

        $this->client = $client;

        $body = $request->getBody($this);
        $this->send($body, $request);

        return $this->getResponse($request);
    }

    private function sendHeader(Request $request){

        if($request->isHeaderSent() ) return;

        $cookie = $request->getHeader('Cookie');
        if(empty($cookie)){
            $cookie = $this->cookieContainer->getCookies($request->getUri());
            if(!empty($cookie)){
                $request->setHeader('Cookie', $cookie);
            }
        }
        fwrite($this->client, $request->getRequestHeaders());
        $request->setHeaderSent();
    }

    /**
     * must ensure necessary headers are set before send data
     * @param string|null $content
     * @param Request $request
     * @return false|int
     */
    public function send(?string $content, Request $request)
    {
        $this->sendHeader($request);

        return fwrite($this->client, $content ?? '');
    }

    /**
     * @param string|null $file
     * @param Request $request
     * @throws \Exception
     */
    public function sendFile(?string $file, Request $request)
    {
        $this->sendHeader($request);
        $fp = fopen($file, 'r');
        if (!$fp) {
            throw new \Exception('can not open file: ' . $file);
        }

        try {
            stream_copy_to_stream($fp, $this->client);
        } finally {
            fclose($fp);
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function readResponseHeaders()
    {
        $headers = [];
        while ($line = fgets($this->client)) {
            if ($line === false) {
                throw new \Exception('remote disconnect');
            }
            if ($line == "\r\n") {
                $headers[] = '';
                break;
            }
            $headers[] = trim($line);
        }
        return $headers;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \Jazor\UnexpectedException|\Exception
     */
    public function getResponse(Request $request): Response
    {
        $headers = $this->readResponseHeaders();
        $response = new Response($headers, $request, $this);
        $transferEncoding = $response->getTransferEncoding();
        $contentLength = $response->getContentLength();

        $cookies = $response->getHeader('Set-Cookie');
        if($cookies != null) {
            $this->cookieContainer->setCookies($request->getUri(), $cookies);
        }
        if (empty($transferEncoding) && $contentLength === -1) {
            throw new \Exception('server response error data? expect TransferEncoding or ContentLength' . implode("\r\n", $headers));
        }
        if (!empty($transferEncoding) && strtolower($transferEncoding) !== 'chunked') {
            throw new \Exception(sprintf('except TransferEncoding: chunked, specified: %s', $transferEncoding));
        }
        return $response;
    }

    public function clearUp()
    {
        if ($this->client !== null) {
            fclose($this->client);
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->clearUp();
    }

    public function getClient()
    {
        if($this->client == null) throw new \Exception('connection has been closed!');
        return $this->client;
    }
}
