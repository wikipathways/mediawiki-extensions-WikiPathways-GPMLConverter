#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")

LOG_FILE="$HOME/bulk$TIMESTAMP.log"
INVALID_GPML_LIST="$HOME/invalid-gpmls.txt"
touch "$LOG_FILE"
rm -f "$INVALID_GPML_LIST"
touch "$INVALID_GPML_LIST"

# Based on http://linuxcommand.org/lc3_wss0140.php
PROGNAME=$(basename $0)
error_exit()
{

#	----------------------------------------------------------------
#	Function for exit due to fatal program error
#		Accepts 1 argument:
#			string containing descriptive error message
#	----------------------------------------------------------------


	echo "${PROGNAME}: ${1:-"Unknown Error"}" 1>&2
	exit 1
}

# drawing on https://codeinthehole.com/tips/bash-error-reporting/
function handle_error {
  read line file <<<$(caller)
  (echo "An error occurred in line $line of file $file:" | tee -a "$LOG_FILE" >&2)
  (sed "${line}q;d" "$file" | tee -a "$LOG_FILE" >&2)
  exit 1
}

trap handle_error ERR

for f in $(find /home/wikipathways.org/images/wikipathways/ -name 'WP*_*.gpml'); do
  if [ -s "$f" ]; then
    # TODO: which is better?
    #xmlstarlet val "$f";
    #if [ $? -eq 0 ]; then ... fi
    is_valid=$((xmlstarlet val "$f" | grep ' valid') || echo '');
    if [ ! "$is_valid" ]; then
      echo "$f" >> "$INVALID_GPML_LIST"
    elif [ $(xmlstarlet sel -N 'gpml=http://pathvisio.org/GPML/2013a' -t -v 'count(/gpml:Pathway/gpml:Group/@GroupRef)' "$f") != "0" ]; then
      echo "$f" >> "$INVALID_GPML_LIST"
    fi
  else
    echo "Removing empty file: $f"
    sudo rm -f "$f"
  fi
done
