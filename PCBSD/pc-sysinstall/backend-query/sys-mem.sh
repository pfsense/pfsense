#!/bin/sh

MEM=`sysctl hw.realmem | sed "s|hw.realmem: ||g"`
MEM=`expr $MEM / 1024`
MEM=`expr $MEM / 1024`
echo $MEM
