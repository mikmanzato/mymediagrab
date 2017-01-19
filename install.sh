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
LOGDIR="/var/log/mymediagrab"
STATUSDIR="/var/lib/mymediagrab"

# Copy files
cp $D/bin/* $BINDIR
mkdir -p $LIBDIR/mymediagrab
cp -R $D/lib $LIBDIR/mymediagrab
cp -R $D/templates $LIBDIR/mymediagrab

if [ ! -d "$ETCDIR/mymediagrab" -o "$1" = "-c" ] ; then
	echo "Installing config files"
	mkdir -p $ETCDIR/mymediagrab
	cp -R $D/etc/mymediagrab/* $ETCDIR/mymediagrab/
fi

cp $D/etc/cron.d/mymediagrab /etc/cron.d/mymediagrab
cp $D/etc/logrotate.d/mymediagrab /etc/logrotate.d/mymediagrab
mkdir -p "$LOGDIR"
mkdir -p "$STATUSDIR"

echo "Succesfully installed."
