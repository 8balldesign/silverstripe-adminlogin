<?php

/**
 * Check Access based on remote IP address
 *
 * @Example entries :
 * 192.168.178.8
 * 192.168.178.0/24
 * 192.168.178.0-50
 * 192.168.178.*
 * 192.168.*
 * @return false || string : entry the ip address was matched against
 */
class IpAccess extends Object
{
    /**
     * @var array
     */
    public $allowedIps = array();

    /**
     * @config
     * @var array
     */
    private static $allowed_ips = array();

    /**
     * @var string
     */
    private $ip = '';

    /**
     * IpAccess constructor.
     *
     * @param string $ip
     * @param array $allowedIps
     */
    public function __construct($ip = '', $allowedIps = array())
    {
        parent::__construct();
        $this->ip = $ip;

        self::config()->allowed_ips = $allowedIps;
    }

    /**
     * @param $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return array
     */
    public function getAllowedIps()
    {
        if ($this->allowedIps) {
            Deprecation::notice('1.1', 'Use the "IpAccess.allowed_ips" config setting instead');
            self::config()->allowed_ips = $this->allowedIps;
        }
        return (array)self::config()->allowed_ips;
    }

    /**
     * @return bool
     */
    public function hasAccess()
    {
        if (!(bool)Config::inst()->get('IpAccess', 'enabled')
            || empty($this->getAllowedIps())
            || $this->matchExact()
            || $this->matchRange()
            || $this->matchCIDR()
            || $this->matchWildCard())
        {
            return true;
        }

        return false;
    }

    /**
     * @param Controller $controller
     * @throws SS_HTTPResponse_Exception
     */
    public function respondNoAccess(Controller $controller)
    {
        $response = null;
        if (class_exists('ErrorPage', true)) {
            $response = ErrorPage::response_for(403);
        }
        $controller->httpError(403, $response ? $response : 'The requested page could not be found.');
    }

    /**
     * @return string
     */
    public function matchExact()
    {
        return in_array($this->ip, $this->getAllowedIps()) ? $this->ip : '';
    }

    /**
     * Try to match against a ip range
     *
     * Example : 192.168.1.50-100
     *
     * @return string
     */
    public function matchRange()
    {
        if ($ranges = array_filter($this->getAllowedIps(), function ($ip) {
            return strstr($ip, '-');
        })
        ) {
            foreach ($ranges as $range) {
                $first = substr($range, 0, strrpos($range, '.') + 1);
                $last  = substr(strrchr($range, '.'), 1);
                list ($start, $end) = explode('-', $last);
                for ($i = $start; $i <= $end; $i++) {
                    if ($this->ip === $first . $i) {
                        return $range;
                    }
                }
            }
        }
        return '';
    }

    /**
     * Try to match cidr range
     *
     * Example : 192.168.1.0/24
     *
     * @return string
     */
    public function matchCIDR()
    {
        if ($ranges = array_filter($this->getAllowedIps(), function ($ip) {
            return strstr($ip, '/');
        })
        ) {
            foreach ($ranges as $cidr) {
                list ($net, $mask) = explode('/', $cidr);
                if ((ip2long($this->ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($net)) {
                    return $cidr;
                }
            }
        }
        return '';
    }

    /**
     * Try to match against a range that ends with a wildcard *
     *
     * Example : 192.168.1.*
     * Example : 192.168.*
     *
     * @return string
     */
    public function matchWildCard()
    {
        if ($ranges = array_filter($this->getAllowedIps(), function ($ip) {
            return substr($ip, -1) === '*';
        })
        ) {
            foreach ($ranges as $range) {
                if (substr($this->ip, 0, strlen(substr($range, 0, -1))) === substr($range, 0, -1)) {
                    return $range;
                }
            }
        }
        return '';
    }

}
