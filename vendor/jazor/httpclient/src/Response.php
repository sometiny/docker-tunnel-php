<?php


namespace Jazor\Http;

use Jazor\UnexpectedException;

class Response extends Headers
{
    private int $statusCode;
    private ?string $statusText;
    private string $httpVersion = 'HTTP/1.1';
    private ?string $body = null;
    private ?string $location = null;
    private ?string $allResponseHeaders = null;
    private ?string $vary = null;
    private Transporter $transporter;
    private Request $request;

    /**
     * Response constructor.
     * @param string|array $headers
     * @throws UnexpectedException
     */
    public function __construct($headers, Request $request, Transporter $transporter)
    {
        if(is_array($headers)){
            $this->allResponseHeaders = implode("\r\n", $headers) . "\r\n";
        }else {
            $this->allResponseHeaders = $headers;
            $headers = explode("\r\n", $headers);
        }

        $index = $this->parseStatusLine($headers);
        if($index !== 0){
            $this->allResponseHeaders = implode("\r\n", array_slice($headers, $index)) . "\r\n";
        }

        for ($i = $index + 1; $i < count($headers); $i++) {
            $this->addHeaderLine($headers[$i]);
        }
        $this->prepareHeaders();
        $this->transporter = $transporter;
        $this->request = $request;
    }

    protected function prepareHeaders()
    {
        parent::prepareHeaders();
        $header = $this->getSingletHeader('Location');
        if ($header !== null) $this->location = $header;

        $header = $this->getSingletHeader('Vary');
        if ($header !== null) $this->vary = $header;
    }

    /**
     * When this stream wrapper follows a redirect, the wrapper_data returned by stream_get_meta_data()
     * might not necessarily contain the HTTP status line that actually applies to the content data at index 0.
     * @param string $line
     * @return int
     * @throws UnexpectedException
     */
    private function parseStatusLine(array $headers){

        for($i = count($headers) - 1 ; $i >=0 ;$i--){
            $line = $headers[$i];
            if(preg_match('/^(HTTP\/[0-9.]+?) ([0-9]+)(?: (.+))?/', $line, $match)){
                $this->httpVersion = $match[1];
                $this->statusCode = intval($match[2]);
                $this->statusText = $match[3];
                return $i;
            }
        }
        throw new UnexpectedException('except http header');
    }


    /**
     * @return int
     * @throws \Exception
     */
    private function readNextChunk(): int
    {
        $client = $this->transporter->getClient();
        $header = fgets($client);
        if ($header === false) throw new \Exception('remote disconnect');
        $length = strstr($header, ' ', true);
        if ($length !== false) {
            $header = $length;
        }
        if ($header === '0') return 0;
        return intval($header, 16);
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function readChunked(): string
    {
        $client = $this->transporter->getClient();
        $body = '';
        while (($length = $this->readNextChunk()) > 0) {
            $body .= $this->readLengthBody($length);
            fgets($client);
        }
        fgets($client);
        return $body;
    }

    /**
     * @param $length
     * @return string
     * @throws \Exception
     */
    private function readLengthBody($length): string
    {
        $client = $this->transporter->getClient();
        $body = '';
        while ($length > 0) {
            $block = fread($client, $length);
            if ($block === false) throw new \Exception('remote disconnect');

            $length -= strlen($block);
            $body .= $block;
        }
        return $body;
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function getBody(): ?string
    {
        $transferEncoding = $this->getTransferEncoding();
        $contentLength = $this->getContentLength();
        $contentEncoding = $this->getContentEncoding();
        if (!empty($transferEncoding)) {
            $body = $this->readChunked();
        } else {
            $body = $this->readLengthBody($contentLength);
        }

        $this->transporter->clearUp();

        if (empty($contentEncoding)) return $body;

        $contentEncoding = strtolower($contentEncoding);

        if ($contentEncoding == 'gzip') $body = gzdecode($body);
        else if ($contentEncoding == 'deflate') $body = gzinflate($body);

        if ($body === false) throw new \Exception('can not decompress response datas');

        return $body;
    }
    public function sink($file)
    {
        $fp = fopen($file, 'wb');

        try {
            stream_copy_to_stream($this->transporter->getClient(), $fp );
        } finally {
            fclose($fp);
        }
        $this->transporter->clearUp();
    }

    /**
     * @param int $options
     * @return array|null
     * @throws \Exception
     */
    public function getJson(int $options = 0): ?array
    {
        $result = json_decode($this->body, true, 512, $options);

        if($result === false){
            throw new \Exception('json decode failed');
        }

        return $result;
    }

    /**
     * @param string|null $body
     */
    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusText(): ?string
    {
        return $this->statusText;
    }

    /**
     * @return string|null
     */
    public function getAllResponseHeaders(): ?string
    {
        return $this->allResponseHeaders;
    }

    /**
     * @return string
     */
    public function getHttpVersion(): string
    {
        return $this->httpVersion;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @return string|null
     */
    public function getVary(): ?string
    {
        return $this->vary;
    }
}
