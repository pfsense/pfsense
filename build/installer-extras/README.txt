pfSense Software Installation Media
===================================

Overview
--------

The pfSense project is a free network firewall distribution, based on the
FreeBSD operating system with a custom kernel and including third party free
software packages for additional functionality. pfSense software, with the help
of the package system, is able to provide the same functionality or more of
common commercial firewalls, without any of the artificial limitations. It has
successfully replaced every big name commercial firewall you can imagine in
numerous installations around the world, including Check Point, Cisco PIX, Cisco
ASA, Juniper, Sonicwall, Netgear, Watchguard, Astaro, and more.

pfSense software includes a web interface for the configuration of all included
components. There is no need for any UNIX knowledge, no need to use the command
line for anything, and no need to ever manually edit any rule sets. Users
familiar with commercial firewalls catch on to the web interface quickly, though
there can be a learning curve for users not familiar with commercial-grade
firewalls.

pfSense started in 2004 as a fork of the m0n0wall Project which ended
2015/02/15, though has diverged significantly since.

pfSense is Copyright 2004-2019 Rubicon Communications, LLC (Netgate) and
published under an open source license. (https://pfsense.org/license)

Read more at https://pfsense.org/ and support the team by buying bundled
hardware appliances or commercial support.

Contribute
----------

For information on how to contribute to the pfSense project, see
https://github.com/pfsense/pfsense/blob/master/.github/CONTRIBUTING.md

Installing pfSense Software (amd64)
-----------------------------------

The installation media can be inserted into the target device. When booted from
this disk, the installer will launch automatically. For more information on how
to install pfSense software, see the installation section of the online
documentation: https://www.netgate.com/docs/pfsense/book/install/index.html

Restoring an Existing Firewall Configuration (amd64)
----------------------------------------------------

An existing configuration file (config.xml) can be restored during the
installation process. Place a copy of the config.xml file on this FAT partition,
in this directory or under X:\conf\config.xml where X: is the letter of this
drive.

At the end of the installation process, this file will be copied to the target
drive and used in place of the default configuration. Packages will be restored
after the firewall boots with the new configuration in place.

Alternately, the installer can attempt to recover an existing config.xml file
from the target disk before it formats the drive during the installation
process.

For more information on these features, see the online documentation at
https://www.netgate.com/docs/pfsense/backup/automatically-restore-during-install.html

Installing pfSense Software (ARM)
---------------------------------

ARM systems use recovery images and not an installation disk. The process is
similar, however, details of this process may vary by model. View the
appropriate recovery instructions for each model at
https://www.netgate.com/docs/pfsense/solutions/

Restoring an Existing Firewall Configuration (ARM)
--------------------------------------------------

ARM systems can restore an existing configuration using the External
Configuration Locator (ECL) feature. Place a copy of config.xml on the FAT
partition under X:\config\config.xml where X: is the letter of this drive.

After the recovery process completes, remove this drive and reboot the device.
After a few moments, insert the drive again. The firewall device will boot from
its internal disk but will find the configuration on this FAT partition, then
restore it to the firewall in place of the default settings.

For more information on these features, see the online documentation at
https://www.netgate.com/docs/pfsense/backup/automatically-restore-during-install.html
