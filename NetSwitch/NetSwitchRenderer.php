<?php

declare(strict_types = 1);

namespace Network\NetSwitch;

/**
 * Render a view of the Ports of a NetSwitch
 *
 * Yeah, this code is a mess
 */
class NetSwitchRenderer
{
    /**
     * Get the render of a NetSwitch object
     *
     * @param NetSwitch $switch The NetSwitch to render
     *
     * @return string
     */
    public function render(NetSwitch $switch): string
    {
        ob_start();

        $portList = $switch->getInterfacesDetails();
        $this->computeRowCount($portList);
        ?>
        <table>
            <thead>
                <tr>
                    <th colspan="6">Switch</th>
                    <th colspan="5">Appareil distant</th>
                </tr>
                <tr>
                    <th>Port</th>
                    <th>Trunk</th>
                    <th>Activé</th>
                    <th>PoE</th>
                    <th>Mode</th>
                    <th>Vlan</th>
                    <th>Hote</th>
                    <th>Port</th>
                    <th>IP</th>
                    <th>Source</th>
                    <th>Mac</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $this->renderRows($portList);
            ?>
            </tbody>
        </table>
        <?php

        return ob_get_clean();
    }

    protected function renderTrunk(Port $port)
    {
        if ($port->isInTrunk()) {
            echo $port->getTrunkName();
        }
    }

    protected function renderEnabledState(Port $port)
    {
        if ($port->isEnabled()) {
            echo 'On';
        } else {
            echo 'Off';
        };
    }

    protected function renderTrTagClass(Port $port)
    {
        $cycle = ["even", "odd"];
        echo ' class="'. $cycle[$port->getName()[strlen($port->getName())-1] % 2] . '"';
    }

    protected function renderPoEConfig(Port $port)
    {
        if ($port->hasPoeConfig()) {
            $poe = $port->getPoeConfig();
            if (false == $poe->isEnabled()) {
                echo 'No';
            } else {
                echo 'Yes (' . $poe->getUsage() . 'W/' . $poe->getMax() . 'W)';
            }
        }
    }

    protected function renderVlan(Vlan $vlan, Port $port)
    {
        $display = '';
        if ('' != $vlan->getName()) {
            $display = $vlan->getName() . ' (' . $vlan->getId() . ')';
        }
        ?>
        <td rowspan="<?= $port->nbVlanDistant[$vlan->getId()] ?>"><?= $display ?></td>
        <?php
    }

    protected function renderMode(Port $port)
    {
        if ($port->hasKnownMode()) {
            echo $port->getMode();
        }
    }

    protected function renderPortConfig(Port $port)
    {
        ?>
        <td rowspan="<?= $port->nbDistant ?>"><?= $port->getName() ?></td>
        <td rowspan="<?= $port->nbDistant ?>"><?php $this->renderTrunk($port); ?></td>
        <td rowspan="<?= $port->nbDistant ?>"><?php $this->renderEnabledState($port); ?></td>
        <td rowspan="<?= $port->nbDistant ?>"><?php $this->renderPoEConfig($port); ?></td>
        <td rowspan="<?= $port->nbDistant ?>"><?php $this->renderMode($port); ?></td>
        <?php
    }

    protected function renderDevice(PortConnectedDevice $device)
    {
        ?>
        <td><?= $device->hasSysName() ? $device->getSysName() : '' ?></td>
        <td><?= $device->hasPortName() ? $device->getPortName() : '' ?></td>
        <td><?= $device->hasIpAddress() ? $device->getIpAddress() : '' ?></td>
        <td><?= $device->getDataSource()->getName() ?></td>
        <?php
    }

    protected function renderRows(array $portList)
    {
        /** @var Port $port */
        foreach ($portList as $port) {
            // There isn't any vlan, we display a single tr
            if (0 == count($port->getVlanList())) {
                ?>
                <tr<?php $this->renderTrTagClass($port); ?>>
                    <?php $this->renderPortConfig($port); ?>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php
            } else {
                $displayPort = true;
                foreach ($port->getVlanList() as $vlan) {
                    ?>
                    <?php
                    // There isn't any mac address, we display a single tr for the vlan
                    if (0 == count($vlan->getMacAddressList())) {
                        ?>
                        <tr<?php $this->renderTrTagClass($port); ?>>
                            <?php
                            if ($displayPort) {
                                $displayPort = false;
                                $this->renderPortConfig($port);
                            }
                            $this->renderVlan($vlan, $port);
                            ?>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                    } else {
                        $displayVlan = true;
                        foreach ($vlan->getMacAddressList() as $mac => $deviceList) {
                            // There isn't any info about connected device, we display a single tr for the mac address
                            if (0 == count($deviceList)) {
                                ?>
                                <tr<?php $this->renderTrTagClass($port); ?>>
                                    <?php
                                    if ($displayPort) {
                                        $displayPort = false;
                                        $this->renderPortConfig($port);
                                    }
                                    if ($displayVlan) {
                                        $this->renderVlan($vlan, $port);
                                        $displayVlan = false;
                                    }
                                    ?>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td><?= $mac ?></td>
                                </tr>
                                <?php
                            } else {
                                $displayMac = true;
                                foreach ($deviceList as $device) {
                                    ?>
                                    <tr<?php $this->renderTrTagClass($port); ?>>
                                    <?php
                                    if ($displayPort) {
                                        $displayPort = false;
                                        $this->renderPortConfig($port);
                                    }
                                    if ($displayVlan) {
                                        $this->renderVlan($vlan, $port);
                                        $displayVlan = false;
                                    }
                                    $this->renderDevice($device);
                                    if ($displayMac) {
                                        $displayMac = false;
                                        ?>
                                        <td rowspan="<?= count($deviceList) ?>"><?= $mac ?></td>
                                        <?php
                                    }
                                    echo '</tr>';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function computeRowCount(array $portList)
    {
        /** @var Port $port */
        foreach ($portList as $port) {
            $port->nbDistant = 0;
            $port->nbVlanDistant = [];

            foreach ($port->getVlanList() as $vlan) {
                $port->nbVlanDistant[$vlan->getId()] = 0;
                if (0 == count($vlan->getMacAddressList())) {
                    $port->nbDistant++;
                    $port->nbVlanDistant[$vlan->getId()] = 1;
                } else {
                    foreach ($vlan->getMacAddressList() as $distant) {
                        $port->nbDistant += max(1, count($distant));
                        $port->nbVlanDistant[$vlan->getId()] += max(1, count($distant));
                    }
                }
            }
        }
    }
}
