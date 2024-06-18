<?php


namespace Jazor\Http\Cookies;

use Jazor\Uri;

class Cookie
{
    private string $name = '';

    private string $value = '';
    private string $path = '';
    private string $domain;
    private bool $secure = false;
    private bool $httpOnly = false;
    private int $expires = -1;

    private bool $pathSpecified = false;
    private bool $domainSpecified = false;
    private bool $secureSpecified = false;
    private bool $httpOnlySpecified = false;

    public function __construct(string $name, string $value, int $expires = -1)
    {
        $this->name = $name;
        $this->value = $value;
        $this->expires = $expires;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * @return int
     */
    public function getExpires(): int
    {
        return $this->expires;
    }

    /**
     * @param bool $secure
     * @param bool $specified
     */
    public function setSecure(bool $secure, bool $specified = true): void
    {
        $this->secure = $secure;
        $this->secureSpecified = $specified;
    }

    /**
     * @param string $domain
     * @param bool $specified
     */
    public function setDomain(string $domain, bool $specified = true): void
    {
        if($domain[0] != '.') $domain = '.' . $domain;
        $this->domain = strtolower($domain);
        $this->domainSpecified = $specified;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @param bool $specified
     */
    public function setPath(string $path, bool $specified = true): void
    {
        $this->path = $path;
        $this->pathSpecified = $specified;
    }

    /**
     * @return bool
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * @param bool $httpOnly
     * @param bool $specified
     */
    public function setHttpOnly(bool $httpOnly, bool $specified = true): void
    {
        $this->httpOnly = $httpOnly;
        $this->httpOnlySpecified = $specified;
    }

    public function forUri(Uri $uri)
    {
        if (empty($this->domain)) {
            $this->domain = '.' . strtolower($uri->getAuthority());
        }
    }

    public function checkDomain(Uri $uri)
    {
        if(empty($this->domain)) return true;
        $authority = '.' . strtolower($uri->getAuthority());
        $domain = $this->domain;

        return strrpos($authority, $domain) === strlen($authority) - strlen($domain);
    }

    public function isForUri(Uri $uri)
    {
        $authority = '.' . strtolower($uri->getAuthority());
        $domain = $this->domain;

        return strrpos($authority, $domain) === strlen($authority) - strlen($domain)
            && !$this->isExpired()
            && (!$this->secure || $uri->getSchema() === 'https')
            && (strpos($uri->getPath(), $this->path) === 0);
    }

    public function equals(Cookie $cookie): bool
    {
        return $cookie->domain === $this->domain
            && $cookie->name === $this->name;
    }

    public function isExpired()
    {
        return $this->expires > 0 && $this->expires < time();
    }

    public function toArray()
    {
        $result = ['name' => $this->name, 'value' => $this->value];

        if($this->expires > -1){
            $result['expires'] = (new \DateTime())->setTimestamp($this->expires)->setTimezone(new \DateTimeZone('+0000'))->format('D, d-M-Y H:i:s \G\M\T');
        }
        if($this->pathSpecified){
            $result['path'] = $this->path;
        }
        if($this->secureSpecified){
            $result['secure'] = true;
        }
        if($this->httpOnlySpecified){
            $result['httpOnly'] = true;
        }
        if($this->domainSpecified){
            $result['domain'] = $this->domain;
        }

        return $result;
    }

    public function __toString()
    {
        return sprintf('%s=%s', $this->name, $this->value);
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httpOnly' => $this->httpOnly,
            'expires' => $this->expires,
            'pathSpecified' => $this->pathSpecified,
            'domainSpecified' => $this->domainSpecified,
            'secureSpecified' => $this->secureSpecified,
            'httpOnlySpecified' => $this->httpOnlySpecified,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->value = $data['value'];
        $this->path = $data['path'];
        $this->domain = $data['domain'];
        $this->secure = $data['secure'];
        $this->httpOnly = $data['httpOnly'];
        $this->expires = $data['expires'];
        $this->pathSpecified = $data['pathSpecified'];
        $this->domainSpecified = $data['domainSpecified'];
        $this->secureSpecified = $data['secureSpecified'];
        $this->httpOnlySpecified = $data['httpOnlySpecified'];
    }

    public function __debugInfo()
    {
        return $this->__serialize();
    }
}
