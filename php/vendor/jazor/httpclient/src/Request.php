<?php


namespace Jazor\Http;


use Jazor\Http\Cookies\CookieContainer;
use Jazor\Http\Transporters\TcpTransporter;
use Jazor\Uri;

class Request extends Headers
{

    private Uri $uri;
    private string $method;
    private bool $headerSent = false;
    private Transporter $transporter ;

    private bool $jsonUnicode = true;

    private $sink ;

    private $body = null;

    /**
     * Request constructor.
     * @param string|Uri $url
     * @param string $method
     * @throws \Exception
     */
    public function __construct($url, string $method = 'GET')
    {
        $this->method = strtoupper($method);
        if ($url instanceof Uri) {
            $this->uri = $url;
        }else if(is_string($url)){
            $this->uri = new Uri($url);
        }else{
            throw new \Exception('expect Uri or url string');
        }

        $this->setHeader('Host', $this->uri->getAuthority());
        $this->setHeader('Accept', '*/*');
        $this->setHeader('Accept-Encoding', 'gzip, deflate');
        $this->setHeader('Cookie', null);
        $this->setHeader('Users-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36');
        $this->setHeader('Content-Type', null);
        $this->setHeader('Content-Length', null);
        $this->setHeader('Referer', null);
        $this->setHeader('Connection', 'close');
    }

    /**
     * @return Uri|string
     */
    public final function getUri(): Uri
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public final function getMethod(): string
    {
        return $this->method;
    }

    private function getContents(){
        if ($this->body === null) return null;
        if (is_string($this->body)) return $this->body;

        if (!is_array($this->body)) throw new \Exception('expect array body');

        $contentType = $this->getSingletHeader('Content-Type');

        switch ($contentType) {
            case "application/json":
                return json_encode($this->body, $this->jsonUnicode ? 256 : 0);
        }
        return http_build_query($this->body, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param Transporter $transporter
     * @return string|null
     * @throws \Exception
     */
    public function getBody(Transporter $transporter): ?string
    {
        $contents = $this->getContents();
        if ($contents === null) return null;

        $this->setHeader('Content-Length', strlen($contents));
        return $contents;
    }

    /**
     * @param null $body
     * @param null $contentType
     * @return Request
     */
    public function setBody($body, $contentType = null): Request
    {
        $this->body = $body;
        if(!empty($contentType)){
            $this->setContentType($contentType);
        }
        return $this;
    }

    /**
     * @return mixed|null
     */
    public final function getRawBody()
    {
        return $this->body;
    }

    /**
     * @param $contentType
     * @return $this
     */
    public final function setContentType($contentType): Request
    {
        $this->setHeader('Content-Type', $contentType);
        return $this;
    }

    /**
     * @return string
     */
    public final function getRequestHeaders(): string
    {
        return sprintf(
            "%s %s HTTP/1.1\r\n%s\r\n",
            $this->method,
            $this->uri->getPathAndQuery(),
            $this->getAllHeaders());
    }

    /**
     * @param array|null $options
     * @param CookieContainer|null $cookieContainer
     * @return Response
     * @throws \Exception
     */
    public final function getResponse(?array $options = null, ?CookieContainer $cookieContainer = null): Response
    {
        $transporter = (new TcpTransporter($options, $cookieContainer));
        $this->transporter = $transporter;
        return $transporter->execute($this);
    }

    /**
     * @return bool
     */
    public final function isHeaderSent(): bool
    {
        return $this->headerSent;
    }

    public final function setHeaderSent(): void
    {
        $this->headerSent = true;
    }

    /**
     * @return Transporter
     */
    public function getTransporter(): Transporter
    {
        return $this->transporter;
    }

    /**
     * @param bool $jsonUnicode
     */
    public function setJsonUnicode(bool $jsonUnicode): void
    {
        $this->jsonUnicode = $jsonUnicode;
    }

    /**
     * @return mixed
     */
    public function getSink()
    {
        return $this->sink;
    }

    /**
     * @param mixed $sink
     */
    public function setSink($sink): void
    {
        $this->sink = $sink;
    }
}
