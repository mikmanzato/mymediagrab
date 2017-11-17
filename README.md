MyMediaGrab
===========

Automatically grab pictures & videos from your smartphone/camera and save them
to disk.

Ideal tool to install on your NAS. Senses when you're home, connects to your
device and scans it for new content. A FTP Server application is required on
the smartphone in order to allow MyMediaGrab to scan & download media.

Can also grab media from a local directory.


Requirements
------------

Mymediagrab requires a working command-line php installation. On Linux systems,
installing the `php-cli` package or equivalent should do. Mymediagrab works on
PHP 5.6 as well as PHP 7.

Installation
------------

Unpack the tar distribution archive, cd into the extracted mymediagrab-x.y.z directory
and type:

    sudo ./install.sh [-c]

Mymediagrab is installed in */usr/local/*.

Also installed are:
  * a global cron job which runs Mymediagrab once every 10 minutes.
  * logrotate configuration
  * default configuration files, if you use the `-c` option. Note that these
    configuration files will overwrite the existing ones you may have edited.

I will soon release a puppet moudule which automates installation and
configuration of MyMediaGrab.


Uninstallation
--------------

To uninstall Mymediagrab, run:

    ./uninstall.sh

from the extracted package directory.


Connecting to the devices
-------------------------

Mymediagrab connects to your devices while they are in range.

Data is typically accessed via FTP.

### Mobile devices ###

For mobile devices you will need to configure static DHCP entries on your
router which forces the devices to always get the same IP address when they
connect to the WiFi. DHCP configuration is beyond the purpose of this readme.

You will also need to install a FTP server application on your devices. There
are several available both on iTunes (for iOS devices) and Google Play (for
Android devices). Once done, you'll have to find out where the device stores
the media files you want to backup. You will need to configure this path.

Logging
-------

Detailed logs of operation are stored in */var/log/mymediagrab*.

Configuration
-------------

MyMediaGrab is configured via configuration files placed in */usr/local/etc/mymediagrab/*.
There is:

  * one main configuration file
  * several job configuration files in the *jobs.d* subdirectory

Licensing
---------

Copyright (c) Michele Manzato.

Mymediagrab is open source software licensed under the MIT License. Basically, you are
free to use Mymediagrab in any commercial or non-commercial project, you can modify
the code as you wish as long as you don't change licensing and retain the
original copyright statement.

Mymediagrab includes a copy of the following PHP libraries:

  * [Smarty](http://www.smarty.net/) - Version 3.1.29

    Copyright (c) 2015 New Digital Group, Inc.
    Copyright (c) 2015 Uwe Tews.

    Smarty is licensed under the Lesser General Public License (LGPL).

  * [PHPMailer](http://...) - Version 5.1

    Copyright (c) 2004-2009 Andy Provost. All Rights Reserved.
    Copyright (c) 2001-2003 Brent R. Matzelle.

    PHPMailer is licensed under the Lesser General Public License (LGPL).


Disclaimer
----------

Mymediagrab is released as-is. I cannot guarantee fitness of Mymediagrab for
any particular use and I cannot assume any direct or indirect liability should
your system or your data be damaged or lost due to proper or improper use of
Mymediagrab, or due to bugs in Mymediagrab itself or third-party programs run
by Mymediagrab.
