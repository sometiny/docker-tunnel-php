<?php


namespace Jazor\Http\Cookies;

use Jazor\Uri;

class CookieContainer
{
    private array $cookies = [];


    public function __construct()
    {

    }

    /**
     * @param Uri $uri
     * @param array $cookies
     * @throws \Exception
     */
    public function setCookies(Uri $uri, array $cookies)
    {
        foreach ($cookies as $cookie) {
            $item = CookieParser::parser($cookie);
            if ($item == null) return;
            $this->setCookie($uri, $item);
        }
    }

    /**
     * @param Uri $uri
     * @param Cookie|string $cookie
     * @throws \Exception
     */
    public function setCookie(Uri $uri, $cookie): void
    {
        if (is_string($cookie)) {
            $cookie = CookieParser::parser($cookie);
            if ($cookie == null) return;
        }
        if (!($cookie instanceof Cookie)) throw new \Exception('except Cookie or cookie header string');
        if (!$cookie->checkDomain($uri)) return;
        $cookie->forUri($uri);
        $this->checkAndSet($cookie);
    }


    /**
     * @param Cookie $newCookie
     */
    private function checkAndSet(Cookie $newCookie)
    {

        /**
         * @var Cookie $cookie
         */

        $found = -1;
        foreach ($this->cookies as $index => $cookie) {
            if ($cookie->equals($newCookie)) {
                $found = $index;
                break;
            }
        }
        if ($found === -1) {
            if ($newCookie->isExpired()) return;

            $this->cookies[] = $newCookie;
            return;
        }
        if ($newCookie->isExpired()) {
            array_splice($this->cookies, $found, 1);
            return;
        }
        $this->cookies[$found] = $newCookie;
    }

    /**
     * @param Uri $uri
     * @return string
     */
    public function getCookies(Uri $uri): string
    {
        return trim(array_reduce($this->getCookiesArray($uri), function ($item, $value) {
            return $value . ';' . (string)$item;
        }, ''), '; ');
    }

    /**
     * @param Uri $uri
     * @return array
     */
    public function getCookiesArray(Uri $uri): array
    {
        /**
         * @var Cookie $cookie
         */

        $result = [];
        foreach ($this->cookies as $cookie) {
            if ($cookie->isForUri($uri)) {
                $result[] = $cookie;
            }
        }
        return $result;
    }

    public function toArray()
    {
        return array_map(function ($item) {
            return $item->toArray();
        }, $this->cookies);
    }

    public function __serialize(): array
    {
        return $this->cookies;
    }

    public function __unserialize(array $data): void
    {
        $this->cookies = $data;
    }
}
