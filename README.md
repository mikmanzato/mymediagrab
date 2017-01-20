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

Unpack the tar distribution archive, cd into the extracted yabt-x.y.z directory
and type:

    sudo ./install.sh [-c]

Yabt is installed in */usr/local/*.

Also installed are:
  * a global cron job which runs yabt once every 10 minutes.
  * logrotate configuration
  * default configuration files (if you use the "-c" option)

I will soon release a puppet moudule which automates installation and
configuration of Yabt.

Uninstallation
--------------

To uninstall, run:

    ./uninstall.sh

Notifications
-------------

TODO

Logging
-------

Detailed logs of operation are stored in /var/log/mymediagrab.

Recipes
-------

TODO
