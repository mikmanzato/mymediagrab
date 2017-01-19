#!/bin/sh

D=$(dirname $0)

# Check that we are root
UID=$(id -u)
if [ $UID -ne 0 ] ; then
	echo "Must run as root" 1>&2
	exit 1
fi

# Installation locations
BINDIR="/usr/local/bin"
ETCDIR="/usr/local/etc"
LIBDIR="/usr/local/share"
LOGDIR="/var/log"
RUNDIR="/var/run"

# Remove all
sudo rm -rf $BINDIR/mymediagrab $ETCDIR/mymediagrab/ $LIBDIR/mymediagrab/ $RUNDIR/mymediagrab/ $LOGDIR/mymediagrab/
sudo rm -f /etc/cron.d/mymediagrab /etc/logrotate.d/mymediagrab

echo "Succesfully uninstalled."
