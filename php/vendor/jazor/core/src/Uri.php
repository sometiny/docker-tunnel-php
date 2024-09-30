<?php


namespace Jazor;

use Sparkle\Http\HttpResponseException;

/**
 * Class Uri
 * @package Jazor\HttpClient
 */
class Uri
{
    private string $url;
    private string $schema;
    private string $authority;
    private string $host;
    private int $port;
    private string $path;
    private ?string $query = null;
    private string $pathAndQuery;
    private ?string $anchor = null;
    private bool $isFullUrl = true;

    /**
     * Uri constructor.
     * @param string $url
     * @param string|Uri $base
     */
    public function __construct(string $url, $base = null)
    {
        if (empty($url)) throw new \InvalidArgumentException('$url is empty');

        if (!$this->parseUrl($url)) {
            $this->tryParsePathAndQuery($url);
            $this->isFullUrl = false;

            if (empty($base)) return;

            if (is_string($base)) {
                $base = new Uri($base);
            }
            if (!($base instanceof Uri)) {
                throw new \UnexpectedValueException('$base expect url');
            }
            self::combine($this, $base);
            return;
        }
        $this->url = $url;
    }

    private static function combine(Uri $uri, Uri $base)
    {
        if ($uri->isFullUrl) {
            return;
        }
        if (!$base->isFullUrl) {
            throw new \UnexpectedValueException('$base must be a full url');
        }
        $uri->schema = $base->schema;
        $uri->authority = $base->authority;
        $uri->host = $base->host;
        $uri->port = $base->port;
        if (strpos($uri->path, '/') !== 0) {
            $uri->path = self::combinePath($uri->path, $base->path);
        }
        $uri->url = (string)$uri;
        $uri->isFullUrl = true;
    }

    public static function combinePath(string $path, string $base)
    {
        if (strpos($path, '/') === 0) {
            return $path;
        }

        $path = $base . '/../' . $path;

        $parts = array_filter(explode('/', $path), function ($a) {
            return !empty($a);
        });
        $results = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($results);
                continue;
            }
            $results[] = $part;
        }
        return '/' . implode('/', $results);
    }

    private function parseUrl($url)
    {
        $matched = preg_match('/^(https?):\/\/([\w\-.:]+?)(\/[^?#]*)?(\?[^#]*)?(#.*)?$/i', $url, $match);
        if (!$matched) return false;

        if (isset($match[5])) $this->anchor = $match[5];
        if (isset($match[4])) $this->query = $match[4];
        if (isset($match[3])) $this->path = $match[3];

        $this->path = self::formatPath($this->path ?? '');

        $this->authority = $match[2];
        $this->schema = strtolower($match[1]);
        $this->pathAndQuery = $this->path . $this->query;

        $this->parseHostAndPort($this->schema, $this->authority);
        return true;
    }

    private function tryParsePathAndQuery($url)
    {
        $matched = preg_match('/^([^?#]+)?(\?[^#]*)?(#.*)?$/i', $url, $match);
        if (!$matched) throw new \Exception('relative url not well formed');


        if (isset($match[3])) $this->anchor = $match[3];
        if (isset($match[2])) $this->query = $match[2];
        $this->path = $match[1];

        $this->path = self::formatPath($this->path);

        $this->pathAndQuery = $this->path . $this->query;
    }

    private static function formatPath($path)
    {
        if (empty($path)) return '/';
        return $path;
    }

    private function parseHostAndPort(string $schema, string $authority)
    {
        $idx = strpos($authority, ':');
        if ($idx === 0 || $idx === strlen($authority) - 1) throw new \Exception('url not well formed');

        if ($idx === false) {
            $this->host = $authority;
            $this->port = $schema === 'http' ? 80 : 443;
            return;
        }
        $this->host = substr($authority, 0, $idx);
        $this->port = intval(substr($authority, $idx + 1));
    }

    public function __toString()
    {
        return sprintf('%s://%s%s%s%s', $this->schema, $this->authority, $this->path, $this->query ?? '', $this->anchor ?? '');
    }

    /**
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * @return string
     */
    public function getAuthority(): string
    {
        return $this->authority;
    }

    public function getSchemaAndAuthority(): string
    {
        return $this->schema . '://' . $this->authority;
    }

    public function getHostAndPort(): string{
        return  $this->host . ':' . $this->port;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getPathAndQuery(): string
    {
        return $this->pathAndQuery;
    }

    /**
     * @return string
     */
    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    public function isFullUrl(): bool
    {
        return $this->isFullUrl;
    }
}
