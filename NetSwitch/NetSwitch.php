<?php

declare(strict_types=1);

namespace Network\NetSwitch;

use Network\IPDevice;
use Network\ARPProvider;
use Network\NetSwitch\Exception\RuntimeException;

/**
 * Represent a network switch
 */
abstract class NetSwitch extends IPDevice implements ARPProvider
{
    const DATA_SOURCE_MAC = 1;
    const DATA_SOURCE_IP  = 2;
    
    protected $dataSource;
    
    /**
     * Get the list of interfaces which are physically in the switch
     * 
     * @return string[]
     */
    abstract public function getPhysicalInterfaces(): array;
    /**
     * Get the defined vlan on the switch
     * 
     * @return array
     */
    abstract public function getVlans(): array;
    /**
     * Get the defined interfaces on the switch
     *
     * Be careful, interfaces can be defined on the switch but not physically in the switch
     * 
     * @return Port[]
     */
    abstract public function getInterfaces(): array;
    /**
     * Get the list of know mac addresses on the switch
     * 
     * @return array
     */
    abstract public function getMACAddressTable(): array;
    /**
     * Get the interfaces informations about power over ethernet
     * 
     * @return PoEConfig[]
     */
    abstract public function getPOEData(): array;
    /**
     * Get the datas provided by CDP protocol
     * 
     * @return array
     */
    abstract public function getCDPData(): array;
    /**
     * Get the data provided by LLDP protocol
     * 
     * @return array
     */
    abstract public function getLLDPData(): array;
    /**
     * Get the model of the switch
     * 
     * @return string
     */
    abstract public function getModel(): string;
    
    /**
     * {@inheritdoc}
     */
    public function __construct(string $ip, string $hostname)
    {
        parent::__construct($ip, $hostname);
        $this->resetDataSource();
    }
    
    /**
     * Clear all data sources
     */
    public function resetDataSource()
    {
        $this->dataSource = [
            self::DATA_SOURCE_MAC => [],
            self::DATA_SOURCE_IP  => []
        ];
    }
    
    /**
     * Add a data source
     *
     * @param DataSource $dataSource The data source which will be added
     * @param int        $type       The type of data source, see self::DATA_SOURCE_MAC and self::DATA_SOURCE_IP
     */
    public function addDataSource(DataSource $dataSource, int $type)
    {
        $this->dataSource[$type][$dataSource->getName()] = $dataSource;
    }
    
    /**
     * Gathers informations for interfaces on switch and returns the result
     * 
     * @return Port[]
     */
    public function getInterfacesDetails(): array
    {
        $vlanList = $this->getVlans();
        $lldpList = $this->getLLDPData();
        $cdpList = $this->getCDPData();
        $poeList = $this->getPOEData();
        $macTable = $this->getMACAddressTable();
        $interfaceList = $this->getInterfaces();
        $physicalPortList = $this->getPhysicalInterfaces();
        
        $result = [];

        foreach ($interfaceList as $interface) {
            if (false === array_search($interface->getName(), $physicalPortList)) {
                continue;
            }

            $ipList = [];

            $this->addPOEData($interface, $poeList);
            $this->addVlans($interface, $vlanList);
            $this->addMACAddress($interface, $macTable);
            $this->addMACDataSource($interface, $ipList);
            $this->addNeighborsData($interface, $lldpList, new DataSource('LLDP', $lldpList));
            $this->addNeighborsData($interface, $cdpList, new DataSource('CDP', $cdpList));
            $this->addIPDataSource($interface, $ipList);

            $result[] = $interface;
        }

        return $result;
    }
    
    /**
     * Add the PoE config to a Port
     *
     * If no PoE config can be found for the port, nothing will be done
     *
     * @param Port        $port The port on which the PoE Config will be added
     * @param PoEConfig[] $poe  The result of getPoEData() method
     */
    protected function addPOEData(Port $port, array $poe)
    {
        if (isset($poe[$port->getName()])) {
            $port->setPoeConfig($poe[$port->getName()]);
        }
    }
    
    /**
     * Add the vlans info to a port
     *
     * If no vlan can be found for the port, nothing will be done
     *
     * @param Port $port The port on which the vlans will be added
     * @param array $vlanList The result of getVlan() method
     */
    protected function addVlans(Port $port, array $vlanList)
    {
        foreach ($vlanList as $vlan) {
            foreach ($vlan['port-list'] as $portVlan) {
                if ($port->getName() == $portVlan['name']) {
                    $port->getVlanList()[$vlan['vlan']->getId()] = clone $vlan['vlan'];
                }
            }
        }
    }
    
    /**
     * Add the mac addresses to the according vlan of a port
     *
     * If no mac address can be found for the port, nothing will be done
     *
     * @param Port $port The port on which the mac addresses will be added
     * @param array $mac The result of getMacAddressTable() method
     */
    protected function addMACAddress(Port $port, array $mac)
    {
        if (isset($mac[$port->getName()])) {
            foreach ($mac[$port->getName()] as $macData) {
                $port->getVlanList()[$macData['vlan-id']]
                     ->getMacAddressList()[$this->getCleanedMacAddress($macData['mac'])] = new \ArrayObject();
            }
        }
    }
    
    /**
     * Add LLDP or CDP informations to a port
     *
     * If no LLDP or CDP informations can be found, nothing will be done
     *
     * @param Port       $port          The port on which the LLDP or CDP informations will be added
     * @param array      $neighborsList The result of getLLDPData() result
     * @param DataSource $source        The DataSource where the informations come from
     */
    protected function addNeighborsData(Port $port, array $neighborsList, DataSource $source)
    {
        // for convenience we consider lldp and cdp as a special vlan
        if (isset($neighborsList[$port->getName()])) {
            if (1 == count($port->getVlanList())) {
                $vlan = current($port->getVlanList());
            } else {
                if (!isset($port->getVlanList()[Vlan::NEIGHBORS])) {
                    $vlan = new Vlan(Vlan::NEIGHBORS, '');
                    $port->getVlanList()[Vlan::NEIGHBORS] = $vlan;
                }
                $vlan = $port->getVlanList()[Vlan::NEIGHBORS];
            }

            // If a we have a mac address, we use it. Otherwhise we use an unknown mac address
            if (isset($neighborsList[$port->getName()]['mac'])) {
                $mac = $neighborsList[$port->getName()]['mac'];
            } else {
                $mac = PortConnectedDevice::UNKNOWN_MAC_ADDRESS;
            }
            if (!isset($vlan->getMacAddressList()[$mac])) {
                $vlan->getMacAddressList()[$mac] = new \ArrayObject;
            }

            $this->addPortConnectedDevice(
                $vlan->getMacAddressList()[$mac],
                $source,
                $mac,
                $neighborsList[$port->getName()]['ip'],
                $neighborsList[$port->getName()]['port'],
                $neighborsList[$port->getName()]['sysname'],
                $neighborsList[$port->getName()]['time']
            );
        }
    }

    /**
     * Add a PortConnectedDevice object to a Port
     *
     * Every properties of the PortConnectedDevice can be omitted (set to null)
     * The provided properties will be added to the PortConnectedDevice
     *
     * @param mixed              $macData A reference to the object which old the PortConnectedDevice of a mac address
     * @param DataSource         $source  The Data Source where the information come from
     * @param string             $mac     The mac address to which the PortConnectedDevice is associated
     * @param string             $ip      The IP Address of the PortConnectedDevice
     * @param string             $port    The port of the PortConnectedDevice
     * @param string             $sysname The sysname of the PortConnectedDevice
     * @param \DateTimeImmutable $time    The time of the PortConnectedDevice
     */
    protected function addPortConnectedDevice(
        $macData, DataSource $source, string $mac, string $ip = null, string $port = null,
        string $sysname = null, \DateTimeImmutable $time = null
    )
    {
        $device = new PortConnectedDevice($source, $mac);
        if (isset($ip)) {
            $device->setIpAddress($ip);
        }
        if (isset($port)) {
            $device->setPortName($port);
        }
        if (isset($sysname)) {
            $device->setSysName($sysname);
        }
        if (isset($time)) {
            $device->setTime($time);
        }
        $macData[] = $device;
    }
    
    /**
     * Add the informations from the DataSource which have type IP to a port
     *
     * If no informations can be found for the port, nothing will be done
     *
     * @param Port $port The port on which the informations will be added
     * @param array $ipList The list of IP which will be be looked up with the Data Source
     */
    protected function addIPDataSource(Port $port, array $ipList)
    {
        foreach(array_unique($ipList) as $macData => $ip)
        {
            list($macData, $vlanId) = explode('-', $macData);
            foreach($this->dataSource[self::DATA_SOURCE_IP] as $source)
            {
                /** @var DataSource $source */
                $sourceData = $source->getData();
                if(isset($sourceData[$ip]))
                {
                    $this->addPortConnectedDevice(
                        $port->getVlanList()[$vlanId]->getMacAddressList()[$macData],
                        $source,
                        $macData,
                        $sourceData[$ip]['ip'],
                        null,
                        $sourceData[$ip]['sysname'],
                        $sourceData[$ip]['time']
                    );
                }
            }
        }
    }

    /**
     * Return a mac address with format aa:aa:aa:aa:aa:aa
     *
     * You can provide string with following format :
     *   aabbccddeeff
     *   aa:bb:cc:dd:ee:ff
     *   aa bb cc dd ee ff
     *   aa-bb-cc-dd-ee-ff
     *   aabbcc-ddeeff
     *   aabbcc:ddeeff
     * Actually, any format as long you have exactly twelve [a-f0-9] characters in the string
     *
     * @param string $mac The mac to clean
     *
     * @return string
     */
    public function getCleanedMacAddress(string $mac): string {
        $mac = preg_replace('`[^a-f0-9]`', '', $mac);
        if (strlen($mac) != 12) {
            throw new RuntimeException(sprintf("Invalid mac address %s ", $mac));
        }
        $mac = chunk_split($mac, 2, ':');
        $mac = rtrim($mac, ':');
        return $mac;
    }

    /**
     * Add the informations from the DataSource which have type MAC to a port
     *
     * If no informations can be found for the port, nothing will be done
     *
     * @param Port $port The port on which the informations will be added
     * @param array $ipList A list of IP which will be looked up with the data source which have type IP
     */
    protected function addMACDataSource(Port $port, array & $ipList)
    {
        foreach ($port->getVlanList() as $vlan) {
            foreach ($vlan->getMacAddressList() as $mac => $data) {
                foreach ($this->dataSource[self::DATA_SOURCE_MAC] as $source) {
                    /** @var DataSource $source */
                    $sourceData = $source->getData();
                    if (isset($sourceData[$mac])) {
                        // if there's only one information, we wrap it into an array
                        if (array_key_exists('ip', $sourceData[$mac])) {
                            $toAddList = [$sourceData[$mac]];
                        } else {
                            $toAddList = $sourceData[$mac];
                        }

                        foreach ($toAddList as $toAdd) {
                            if ($source->needIpLookup()) {
                                $ipList[$mac . '-' . $vlan->getId() . '-' . $toAdd['ip']] = $toAdd['ip'];
                            }

                            $this->addPortConnectedDevice(
                                $data,
                                $source,
                                $mac,
                                $toAdd['ip'],
                                null,
                                $toAdd['sysname'],
                                $toAdd['time']
                            );
                        }
                    }
                }
            }
        }
    }
}
