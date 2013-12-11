<?php
/**
@author cookpan001
*/
class memcached
{

    const TERMINATOR = "\r\n";

    private $conn = null;
    private $host = '127.0.0.1';
    private $port = '11211';
    private $timeout = 10;

    function __construct()
    {
        
    }

    function connect($host = '127.0.0.1', $port = '11211')
    {
        $this->host = $host;
        $this->port = $port;
        $errno = 0;
        $errstr = '';
        $i = 0;
        while ($i < 5)
        {
            $i++;
            $this->conn = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, $this->timeout);
            if($this->conn)
            {
                return true;
            }
        }
        if (!$this->conn)
        {
            $msg = 'Fail to open ' . $this->host . ':' . $this->port . ', error message: ' . $errstr . '.';
            error_log($msg);
            return false;
        }
        return true;
    }

    private function sendCommand($cmd, $key, $output = '')
    {
        if(!$this->conn)
        {
            error_log('empty connection.');
            return false;
        }
        fwrite($this->conn, strtolower($cmd) . " $key " . $output . self::TERMINATOR);
    }

    private function sendData($value)
    {
        if(!$this->conn)
        {
            error_log('empty connection.');
            return false;
        }
        fwrite($this->conn, $value . self::TERMINATOR);
    }

    private function recvData()
    {
        if(!$this->conn)
        {
            error_log('empty connection.');
            return false;
        }
        $line = fgets($this->conn, 1024);
        return substr($line, 0, -2);
    }

    private function insert($cmd, $key, $value, $flag = 0, $expire = 0)
    {
        $arr = array(
            'flag' => $flag,
            'expire' => $expire,
            'len' => strlen('' . $value),
        );
        $this->sendCommand($cmd, $key, implode(' ', $arr));
        $this->sendData($value);
        $line = $this->recvData();
        if ($line === 'STORED')
        {
            return true;
        }
        return false;
    }

    function add($key, $value, $flag = 0, $expire = 0)
    {
        return $this->insert('add', $key, $value, $flag, $expire);
    }

    function set($key, $value, $flag = 0, $expire = 0)
    {
        return $this->insert('set', $key, $value, $flag, $expire);
    }

    function replace($key, $value, $flag = 0, $expire = 0)
    {
        return $this->insert('replace', $key, $value, $flag, $expire);
    }

    function increment($key, $addon = 1)
    {
        $this->sendCommand('incr', $key, abs($addon));
        $ret = $this->recvData();
        if ($ret === "ERROR" || $ret === "NOT_FOUND")
        {
            return false;
        }
        return $ret;
    }

    function decrement($key, $addon = 1)
    {
        $this->sendCommand('decr', $key, abs($addon));
        $ret = $this->recvData();
        if ($ret === "ERROR" || $ret === "NOT_FOUND")
        {
            return false;
        }
        return $ret;
    }

    function get($key)
    {
        if (is_array($key))
        {
            $key = implode(' ', $key);
        }
        $this->sendCommand('get', $key);
        $line = $this->recvData();
        $ret = array();
        $i = 0;
        while ($line !== "ERROR" || $line !== 'END')
        {
            $arr = explode(' ', $line);
            if (count($arr) > 2 && $arr[0] === 'VALUE')
            {
                $ret[$arr[1]] = $this->recvData();
                $line = $this->recvData();
                $i++;
            }
            else
            {
                break;
            }
        }
        if ($i == 0)
        {
            return false;
        }
        if ($i == 1)
        {
            return implode('', $ret);
        }
        return $ret;
    }

    function delete($key)
    {
        $this->sendCommand('delete', $key);
        $line = $this->recvData();
        if ($line === 'DELETED')
        {
            return true;
        }
        return false;
    }

    function flush()
    {
        $this->sendCommand('flush_all', '');
        $line = $this->recvData();
        if ($line === 'OK')
        {
            return true;
        }
        return false;
    }

    function getStats()
    {
        $this->sendCommand('stats', '');
        $line = $this->recvData();
        $ret = array();
        while ($line !== 'END' || $line !== 'ERROR')
        {
            if (substr($line, 0, 4) === 'STAT')
            {
                $arr = explode(' ', substr($line, 5));
                $ret[$arr[0]] = $arr[1];
                $line = $this->recvData();
            }
            else
            {
                break;
            }
        }
        if (empty($ret))
        {
            return false;
        }
        return $ret;
    }

    function getVersion()
    {
        $this->sendCommand('version', '');
        return $this->recvData();
    }

    function close()
    {
        fclose($this->conn);
    }

}
