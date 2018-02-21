#!/bin/sh -x

WPID=$1
gpml2pvjson=./node_modules/.bin/gpml2pvjson
pvjs=./node_modules/.bin/pvjs

curl https://vm1.wikipathways.org/Pathway:$WPID?action=raw |
    tee $WPID.xml |
	$gpml2pvjson --id http://identifiers.org/wikipathways/$WPID |
	tee  $WPID.json |
	$pvjs > $WPID.svg
convert -scale x1200 $WPID.svg $WPID.png
