<?php

class KnuckleLog
{
    protected static $defaultFormat = '%h %l %u %t "%r" %>s %b';
    protected $pcreFormat;
    protected $handler = null;
    protected $fbuffer = [];
    protected $offsetLines = 0;
    protected $limitLines = 0;
    protected $patterns = [
        '%%' => '(?P<percent>\%)',
        '%a' => '(?P<remoteIp>)',
        '%A' => '(?P<localIp>)',
        '%h' => '(?P<host>[a-zA-Z0-9\-\._:]+)',
        '%l' => '(?P<logname>(?:-|[\w-]+))',
        '%m' => '(?P<requestMethod>OPTIONS|GET|HEAD|POST|PUT|DELETE|TRACE|CONNECT|PATCH|PROPFIND)',
        '%p' => '(?P<port>\d+)',
        '%r' => '(?P<request>(?:(?:[A-Z]+) .+? HTTP/[1-3].(?:0|1))|-|)',
        '%t' => '\[(?P<time>\d{2}/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/\d{4}:\d{2}:\d{2}:\d{2} (?:-|\+)\d{4})\]',
        '%u' => '(?P<user>(?:-|[\w-]+))',
        '%U' => '(?P<URL>.+?)',
        '%v' => '(?P<serverName>([a-zA-Z0-9]+)([a-z0-9.-]*))',
        '%V' => '(?P<canonicalServerName>([a-zA-Z0-9]+)([a-z0-9.-]*))',
        '%>s' => '(?P<status>\d{3}|-)',
        '%b' => '(?P<responseBytes>(\d+|-))',
        '%T' => '(?P<requestTime>(\d+\.?\d*))',
        '%O' => '(?P<sentBytes>[0-9]+)',
        '%I' => '(?P<receivedBytes>[0-9]+)',
        '\%\{(?P<name>[a-zA-Z]+)(?P<name2>[-]?)(?P<name3>[a-zA-Z]+)\}i' => '(?P<Header\\1\\3>.*?)',
        '%D' => '(?P<timeServeRequest>[0-9]+)',
    ];

    public static function getDefaultFormat()
    {
        return self::$defaultFormat;
    }

    public function __construct($fileName = null, $format = null, $offset = 0, $limit = 10)
    {

        $this->offsetLines = $offset;
        $this->limitLines = $limit;

        if(!($this->handler = fopen($fileName, "rb"))) {
            throw new \Exception("Cannot open the file");
        }

        // Set IPv4 & IPv6 recognition patterns
        $ipPatterns = implode('|', array(
            'ipv4' => '(((25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]))',
            'ipv6full' => '([0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4}){7})', // 1:1:1:1:1:1:1:1
            'ipv6null' => '(::)',
            'ipv6leading' => '(:(:[0-9A-Fa-f]{1,4}){1,7})', // ::1:1:1:1:1:1:1
            'ipv6mid' => '(([0-9A-Fa-f]{1,4}:){1,6}(:[0-9A-Fa-f]{1,4}){1,6})', // 1:1:1::1:1:1
            'ipv6trailing' => '(([0-9A-Fa-f]{1,4}:){1,7}:)', // 1:1:1:1:1:1:1::
        ));
        $this->patterns['%a'] = '(?P<remoteIp>'.$ipPatterns.')';
        $this->patterns['%A'] = '(?P<localIp>'.$ipPatterns.')';
        $this->setFormat($format ?: self::getDefaultFormat());
    }

    public function readLog($countLine = 10)
    {
        if(!$this->handler) {
            throw new Exception("Invalid file pointer");
        }

        while(!feof($this->handler)) {
            $this->fbuffer[] = fgets($this->handler);
            $countLine--;
            if($countLine == 0) break;
        }

        return $this->fbuffer;
    }

    public function setOffset($line = 0)
    {
        if(!$this->handler) {
            throw new \Exception("Invalid file pointer");
        }

        while(!feof($this->handler) && $line--) {
            fgets($this->handler);
        }
    }

    public function addPattern($placeholder, $pattern)
    {
        $this->patterns[$placeholder] = $pattern;
    }

    public function setFormat($format)
    {
        // strtr won't work for "complex" header patterns
        // $this->pcreFormat = strtr("#^{$format}$#", $this->patterns);
        $expr = "#^{$format}$#";
        foreach ($this->patterns as $pattern => $replace) {
            $expr = preg_replace("/{$pattern}/", $replace, $expr);
        }
        $this->pcreFormat = $expr;
    }

    public function parse($line)
    {
        if (!preg_match($this->pcreFormat, $line, $matches)) {
            throw new \Exception("Error parsing line, check offset and limits");
        }
        $entry = new \stdClass();
        foreach (array_filter(array_keys($matches), 'is_string') as $key) {
            if ('time' === $key && true !== $stamp = strtotime($matches[$key])) {
                $entry->stamp = $stamp;
            }
            $entry->{$key} = $matches[$key];
        }
        return $entry;
    }

    public function getPCRE()
    {
        return (string) $this->pcreFormat;
    }

    public function getLines()
    {
        if(!$this->handler) {
            throw new \Exception("Invalid file pointer");
        }
        $lines = 0;
        while(!feof($this->handler)) {
            $lines += substr_count(fread($this->handler, 8192), "\n");
        }
        fclose($this->handler);
        return $lines;
    }

    public function worker()
    {
        $this->setOffset($this->offsetLines);
        $result = $this->readLog($this->limitLines);
        $entry = [];
        foreach ($result as $line) {
            $entry[] = $this->parse($line);
        }
        return ['data' => $entry ?? null, 'totalLines' => $this->getLines() ?? null];
    }
}
