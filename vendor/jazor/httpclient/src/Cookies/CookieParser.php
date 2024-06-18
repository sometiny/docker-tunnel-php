<?php


namespace Jazor\Http\Cookies;

/**
 * cookie parser
 */
class CookieParser
{

    /**
     * @param string $cookieHeader
     * @return Cookie
     * @throws \Exception
     */
    public static function parser(string $cookieHeader): Cookie
    {
        $components = [];
        $startIndex = 0;
        while (true) {
            $idx = strpos($cookieHeader, ';', $startIndex);
            if ($idx === false) break;

            self::parserSignCookie(substr($cookieHeader, $startIndex, $idx - $startIndex), $components);
            $startIndex = $idx + 1;
        }
        if ($startIndex < strlen($cookieHeader)) {
            self::parserSignCookie(substr($cookieHeader, $startIndex), $components);
        }
        return self::getCookie($components);
    }

    /**
     * @param array $components
     * @return Cookie
     * @throws \Exception
     */
    private static function getCookie(array $components): ?Cookie
    {
        if(!isset($components['name'])) return null;

        $expires = -1;

        if(isset($components['expires'])){
            $date = \DateTime::createFromFormat(DATE_COOKIE, $components['expires']);
            if($date === false){
                $date = \DateTime::createFromFormat(DATE_RFC7231, $components['expires']);
                if($date === false){
                    throw new \Exception('date parse error: \'' . $components['expires'] . '\'');
                }
            }
            $expires = $date->getTimestamp();
        }
        else if(isset($components['max-age'])) {
            $expires = strtotime('+' . $components['max-age'] . 'seconds');
        }

        $cookie = new Cookie($components['name'], $components['value'], $expires);

        if (isset($components['domain'])) {
            $cookie->setDomain($components['domain']);
        }

        if(isset($components['httponly'])) $cookie->setHttpOnly(true);
        if(isset($components['secure'])) $cookie->setSecure(true);

        if(isset($components['path'])) {
            $cookie->setPath($components['path']);
        }else{
            $cookie->setPath('/', false);
        }

        return $cookie;
    }

    private static function parserSignCookie(string $cookie, array &$components)
    {
        $cookie = trim($cookie);
        if (empty($cookie)) {
            return;
        }

        $index = strpos($cookie, '=');
        if ($index === false) {
            $components[strtolower($cookie)] = true;
            return;
        }

        $name = substr($cookie, 0, $index);
        $value = substr($cookie, $index + 1);

        if (!isset($components['name'])) {
            $components['name'] = $name;
            $components['value'] = $value;
            return;
        }
        $components[strtolower($name)] = $value;
    }
}
