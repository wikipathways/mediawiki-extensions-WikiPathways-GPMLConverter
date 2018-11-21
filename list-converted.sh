#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")

LOG_FILE="$HOME/bulk$TIMESTAMP.log"
touch "$LOG_FILE"

INVALID_GPML_LIST="$HOME/invalid-gpmls.txt"
rm -f "$INVALID_GPML_LIST"
touch "$INVALID_GPML_LIST"

CONVERTED_GPML_LIST="$HOME/converted-gpmls.txt"
rm -f "$CONVERTED_GPML_LIST"
touch "$CONVERTED_GPML_LIST"

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

for f in $(find /home/wikipathways.org/images/wikipathways/ -name 'WP*.gpml'); do
  if [ -s "$f" ]; then
    # TODO: which is better?
    #xmlstarlet val "$f";
    #if [ $? -eq 0 ]; then ... fi
    is_valid=$((xmlstarlet val "$f" | grep ' valid') || echo '');
    if [ ! "$is_valid" ]; then
      if ! grep "$f" "$INVALID_GPML_LIST"; then
        echo "$f" >> "$INVALID_GPML_LIST"
      fi
    else
      dir_f=$(dirname "$f")
      base_f=$(basename -- "$f")
      ext_f="${base_f##*.}"
      stub_f="${base_f%.*}"
      prefix="$dir_f/$stub_f"
      svg_f="$prefix.svg"

      if [ -s "$svg_f" ]; then
        echo "$f" >> "$CONVERTED_GPML_LIST"
      elif [ -f "$svg_f" ]; then
        echo "Removing empty file: $svg_f"
	sudo rm -f "$svg_f"
      fi
    fi
  else
    echo "Removing empty file: $f"
    sudo rm -f "$f"
  fi
done
