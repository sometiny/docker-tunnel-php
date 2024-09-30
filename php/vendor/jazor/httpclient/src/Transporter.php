<?php


namespace Jazor\Http;


use Jazor\Http\Cookies\CookieContainer;

abstract class Transporter
{
    protected int $timeout = 15;
    protected bool $sslVerifyPeer = false;
    protected bool $sslVerifyHost = true;
    protected bool $followLocation = true;
    protected array $options = [];

    private static ?CookieContainer $defaultCookieContainer = null;

    protected CookieContainer $cookieContainer;

    /**
     * Transporter constructor.
     * @param array|null $options
     * @param CookieContainer|null $cookieContainer
     */
    public function __construct(?array $options = null, ?CookieContainer $cookieContainer = null)
    {
        if(self::$defaultCookieContainer === null){
            self::$defaultCookieContainer = new CookieContainer();
        }

        $this->cookieContainer = $cookieContainer ?? self::$defaultCookieContainer;

        $this->options = array_merge($this->options, $options ?? []);

        $options = $this->options;

        $this->timeout = $options['timeout'] ?? 15;
        $this->followLocation = $options['followLocation'] ?? true;
        $this->sslVerifyPeer = $options['sslVerifyPeer'] ?? true;
        $this->sslVerifyHost = $options['sslVerifyHost'] ?? true;

    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return bool
     */
    public function isSslVerifyPeer(): bool
    {
        return $this->sslVerifyPeer;
    }

    /**
     * @param bool $sslVerifyPeer
     */
    public function setSslVerifyPeer(bool $sslVerifyPeer): void
    {
        $this->sslVerifyPeer = $sslVerifyPeer;
    }

    /**
     * @return bool
     */
    public function isSslVerifyHost(): bool
    {
        return $this->sslVerifyHost;
    }

    /**
     * @param bool $sslVerifyHost
     */
    public function setSslVerifyHost(bool $sslVerifyHost): void
    {
        $this->sslVerifyHost = $sslVerifyHost;
    }

    /**
     * @return bool
     */
    public function isFollowLocation(): bool
    {
        return $this->followLocation;
    }

    /**
     * @param bool $followLocation
     */
    public function setFollowLocation(bool $followLocation): void
    {
        $this->followLocation = $followLocation;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public abstract function execute(Request $request) : Response;

    /**
     * @param string|null $content
     * @param Request $request
     * @return mixed
     */
    public abstract function send(?string $content, Request $request);

    /**
     * @param string|null $file
     * @param Request $request
     * @return mixed
     */
    public abstract function sendFile(?string $file, Request $request);

    /**
     * @return CookieContainer
     */
    public function getCookieContainer(): CookieContainer
    {
        return $this->cookieContainer;
    }
    public function getClient()
    {
        return null;
    }
}
