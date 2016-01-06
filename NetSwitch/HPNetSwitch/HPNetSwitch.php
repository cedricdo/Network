<?php

declare(strict_types=1);

namespace Network\NetSwitch\HPNetSwitch;

use Network\NetSwitch\Exception\ConnectionException;
use Network\NetSwitch\Exception\InvalidArgumentException;
use Network\NetSwitch\Exception\RuntimeException;
use Network\NetSwitch\Vlan;
use Network\NetSwitch\NetSwitch;
use Network\NetSwitch\PoEConfig;
use Network\NetSwitch\Port;
use phpseclib\Net\SSH2;

/**
 * Represent a switch from Hewlett Packard Company
 */
abstract class HPNetSwitch extends NetSwitch
{
    /** @var SSH2  */
    protected $ssh;
    /** @var int  */
    protected $terminalColumn = 160;
    /** @var int  */
    protected $terminalLine   = 2048;
    /** @var string  */
    protected $snmpPortOid    = '1.3.6.1.2.1.47.1.1.1.1.7';
    /** @var string  */
    protected $snmpArpOid     = '1.3.6.1.2.1.4.22.1.2';
    /** @var string  */
    protected $enterKey       = "\n";
    /** @var string  */
    protected $spaceKey;
    /** @var string  */
    protected $user;
    /** @var string  */
    protected $password;
    /** @var string  */
    protected $snmpCommunity;
    /** @var string  */
    protected $promptPattern;
    /** @var string  */
    protected $morePattern;
    
    /**
     * Constructor
     *
     * @param string $ip            The IP address of the switch
     * @param string $hostname      The hostname of the switch
     * @param string $user          The username which will be used to connect to the switch
     * @param string $password      The password which will be used to connect to the switch
     * @param string $snmpCommunity The snmp community which will be used to fetch data from the switch
     * @param string $promptPattern The regex pattern which will be used to locate the prompt on the switch cli
     * @param string $morePattern   The regex pattern which will be used to locale the "see more" on the switch cli
     *                              when a command result needs more than one page
     *
     * @throws InvalidArgumentException If the user name is empty
     * @throws InvalidArgumentException If the snmp community is empty
     * @throws InvalidArgumentException If the prompt regex pattern is empty
     * @throws InvalidArgumentException If the more regex pattern is empty
     */
    public function __construct(
        string $ip, string $hostname, string $user, string $password = '', string $snmpCommunity = 'public',
        string $promptPattern = 'PROC[a-zA-Z0-9-]+[#>]', string $morePattern = '-- MORE --'
    )
    {
        $user = trim($user);
        $snmpCommunity = trim($snmpCommunity);

        if ('' == $user) {
            throw new InvalidArgumentException("User name can not be empty");
        }
        if ('' == $snmpCommunity) {
            throw new InvalidArgumentException("Snmp community can not be empty");
        }
        if ('' == trim($promptPattern)) {
            throw new InvalidArgumentException("Prompt regex pattern can not be empty");
        }
        if ('' == trim($morePattern)) {
            throw new InvalidArgumentException("More regex pattern can not be empty");
        }

        parent::__construct($ip, $hostname);
        $this->spaceKey      = chr(20);
        $this->user          = $user;
        $this->password      = $password;
        $this->snmpCommunity = $snmpCommunity;
        $this->promptPattern = $promptPattern;
        $this->morePattern   = $morePattern;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaces(): array
    {
        $this->connect();
        return $this->execPageableCommand(
            'show interface config',
            '`((?:[A-F]\d+|\d/\d+)(?:-Trk\d+)?)[^|]+\|(.+)`',
            function ($line, $match) {
                $port = explode('-', $match[1]);
                $mode = trim(substr($match[2], 9, 13));
                return [
                    null,
                    new Port(
                        $port[0],
                        'yes' == strtolower(trim(substr($match[2], 1, 8))),
                        '' != $mode ? $mode : null,
                        $port[1] ?? null
                    )
                ];
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCDPData(): array
    {
        $time = new \DateTimeImmutable;
        $this->connect();
        $output = $this->execPageableCommand(
            'show cdp neighbor detail',
            '`.*`',
            function ($line, $match) {
                return [
                    null,
                    [$match[0]]
                ];
            }
        );

        $result = [];
        $port = null;
        $discard = [];

        foreach ($output as $line) {
            preg_match('`([a-zA-Z ]+):(.+)`', $line, $match);
            if (count($match) > 0) {
                $label = strtolower(trim($match[1]));
                $data = trim($match[2]);

                if ($label == 'port') {
                    $port = $data;

                    $result[$port] = [
                        'port'    => $port,
                        'ip'      => null,
                        'sysname' => null,
                        'port'    => null,
                        'time'    => $time,
                        'mac'     => null
                    ];
                } elseif ($label == 'device id') {
                    try {
                        $result[$port]['mac'] = $this->getCleanedMacAddress($data);
                    } catch(RuntimeException $e) {
                        $result[$port]['sysname'] = ('' == $data ? null : $data);
                    }
                } elseif ($label == 'address type' && $data != 'IP') {
                    $discard[] = $port;
                } elseif ($label == 'address') {
                    if (false !== filter_var($data, FILTER_VALIDATE_IP)) {
                        $result[$port]['ip'] = $data;
                    }
                } elseif ($label == 'platform') {
                    if (empty($result[$port]['sysname'])) {
                        $result[$port]['sysname'] = $data;
                    } else {
                        $result[$port]['sysname'] .= ' ; ' . $data;
                    }
                } elseif ($label == 'device port') {
                    $result[$port]['port'] = ('' == $data ? null : $data);;
                }
            }
        }

        foreach ($discard as $index) {
            unset($result[$index]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLLDPData(): array
    {
        $time = new \DateTimeImmutable;
        $this->connect();
        $lldpData = $this->execPageableCommand(
            'show lldp info remote-device',
            '`([A-F]\d+|\d/\d+)\s+\|`',
            function ($line, $match) use ($time) {
                $line = substr($line, strpos($line, $match[1]) - 2);
                $ip = trim(substr($line, 14, 26));
                $mac = null;
                $sysname = trim(substr($line, 57));
                if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
                    try {
                        $mac = $this->getCleanedMacAddress($ip);
                    } catch(RuntimeException $e) {
                        if ('' == $sysname) {
                            $sysname = trim($ip);
                        }
                    }
                    $ip = null;
                }
                $port = trim(substr($line, 47, 10));
                return [
                    $match[1],
                    [
                        'port'    => trim(substr($line, 2, 10)),
                        'ip'      => $ip,
                        'sysname' => '' == $sysname ? null : $sysname,
                        'port'    => '' == $port ? null : $port,
                        'time'    => $time,
                        'mac'     => $mac
                    ]
                ];
            }
        );

        // If a port has no mac address info, we try to get one in the PortId property
        foreach ($lldpData as $port => & $lldp) {
            if (null == $lldp['mac']) {
                $result = $this->execPageableCommand(
                    'show lldp info remote-device ' . $port,
                    '`PortId[ :]+([a-f0-9: -]{12,18})`',
                    function ($line, $match) {
                        return [null, $match[1]];
                    }
                );
                // If the PortId doesn't hold mac address, we don't bother to go further
                if (0 == count($result)) {
                    continue;
                }
                try {
                    $lldp['mac'] = $this->getCleanedMacAddress($result[0]);
                } catch(RuntimeException $e) {

                }
            }
        }

        return $lldpData;
    }

    /**
     * {@inheritdoc}
     */
    public function getMACAddressTable(): array
    {
        $this->connect();
        $cmdResult = $this->execPageableCommand(
            'show mac-address',
            '`([a-zA-Z0-9-]{13})\s+([A-F]\d+|\d/\d+)\s+(\d+)`',
            function ($line, $match) {
                return [
                    null,
                    [
                        'port'    => $match[2],
                        'mac'     => $this->getCleanedMacAddress($match[1]),
                        'vlan-id' => $match[3]
                    ]
                ];
            }
        );

        $result = [];

        foreach ($cmdResult as $mac) {
            if (!isset($result[$mac['port']])) {
                $result[$mac['port']] = [];
            }
            $result[$mac['port']][] = [
                'mac'     => $mac['mac'],
                'vlan-id' => $mac['vlan-id']
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPOEData(): array
    {
        $this->connect();
        return $this->execPageableCommand(
            'show power-over-ethernet brief',
            '`([A-F]\d+|\d/\d+)\s+\|\s+(\S+)[^0-9]+([0-9.]+)\s+W\s+([0-9.]+)\s+W`',
            function ($line, $match) {
                return [
                    $match[1],
                    new PoEConfig('yes' == strtolower($match[2]), (float)$match[3],(float)$match[4])
                ];
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPhysicalInterfaces(): array
    {
        $return = [];
        $snmpData = snmprealwalk($this->ip, $this->snmpCommunity, $this->snmpPortOid);
        foreach ($snmpData as $port) {
            if (preg_match('`([A-F]\d{1,2}|\d/\\d{1,2})`', $port, $match)) {
                $return[] = $match[1];
            }
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getVlans(): array
    {
        $this->connect();
        $result = [];
        /** @var Vlan[] $vlans */
        $vlans = $this->execPageableCommand(
            'show vlans',
            '`(\d+)\s+(\S+)[^|]+\|`',
            function ($line, $match) {
                return [null, new Vlan((int)$match[1], $match[2])];
            }
        );

        foreach ($vlans as $vlan) {
            $result[$vlan->getId()] = [
                'vlan'      => $vlan,
                'port-list' => $this->execPageableCommand(
                    'show vlans ' . $vlan->getId(),
                    '`([A-F]\d+|\d/\d+)\s+(\S+)`',
                    function ($line, $match) {
                        return [
                            null,
                            ['name' => $match[1], 'mode' => $match[2]]
                        ];
                    }
                )
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getARPTable():  array
    {
        $time = new \DateTimeImmutable;
        $result = [];

        $snmpData = snmprealwalk($this->ip, $this->snmpCommunity, $this->snmpArpOid);
        foreach ($snmpData as $oid => $data) {
            preg_match('`((?:[a-f0-9]{1,2}:?){6})`', strtolower($data), $match);

            $macPart = [];
            foreach (explode(':', $match[1]) as $part) {
                if (!isset($part[1])) {
                    $part = '0' . $part;
                }
                $macPart[] = $part;
            }
            $ip = implode('.', array_slice(explode('.', $oid), -4));
            $mac = implode(':', $macPart);

            if (!isset($result[$mac])) {
                $result[$mac] = [];
            }
            $result[$mac][$ip] = [
                'ip'      => $ip,
                'port'    => null,
                'sysname' => null,
                'time'    => $time
            ];
        }

        return $result;
    }


    /**
     * Establish a SSH connection to the switch if necessary
     *
     * @throws ConnectionException If the connection fails
     */
    protected function connect()
    {
        if (false == $this->ssh instanceof SSH2) {
            $this->ssh = new SSH2($this->ip);
            $this->ssh->setWindowSize($this->terminalColumn, $this->terminalLine);
            if (false == @$this->ssh->login($this->user, $this->password)) {
                throw new ConnectionException(
                    sprintf("Connection to %s with user %s failed", $this->ip, $this->user)
                );
            }
            $this->ssh->read('continue');
            $this->ssh->write($this->enterKey);
            $this->ssh->read('`' . $this->promptPattern . '`', SSH2::READ_REGEX);
        }
    }

    /**
     * Allow to execute and get the result of a command which can return more than one page on the CLI
     *
     * @param string   $cmd      The command to run
     * @param string   $regexp   A regex which will filter the rows. If a rows doesn't satisfy the regex, it will be
     *                           skipped.
     * @param Callable $callback A callback wich will be called for each rows. The first parameter is the rows itself,
     *                           the second parameter is the result of preg_match call on the row wich the regexp
     *                           provided
     * 
     * @return array
     *
     * @throws InvalidArgumentException If $callback is not a valid callback
     */
    protected function execPageableCommand(string $cmd, string $regexp, $callback) : array
    {
        if (false == is_callable($callback)) {
            throw new InvalidArgumentException("You must provide a valid callback");
        }

        $result = [];

        $this->ssh->write($cmd . $this->enterKey);
        $readPattern = '`' . $this->morePattern . '|' . $this->promptPattern . '`';
        while ($stdout = $this->ssh->read($readPattern, SSH2::READ_REGEX)) {
            foreach (explode("\n", $stdout) as $line) {
                if ($regexp == '' || $regexp == '`.*`' || $regexp == '`.+`') {
                    $result[] = $line;
                    continue;
                }
                preg_match($regexp, $line, $match);
                if (count($match) > 0) {
                    list($index, $value) = $callback($line, $match);
                    if ($index !== null) {
                        $result[$index] = $value;
                    } else {
                        $result[] = $value;
                    }
                }
            }

            if (preg_match('`' . $this->promptPattern . '`', $stdout) === 1) {
                break;
            }
            $this->ssh->write($this->spaceKey);
        }

        return $result;
    }
}
