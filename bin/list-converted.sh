#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

LOGS_DIR="$dir_out/logs"
if [ ! -z "$WP_DIR" ] && [ -r "$WP_DIR" ] && [ -w "$WP_DIR" ]; then
  LOGS_DIR="$WP_DIR/logs"
elif [ ! -z "$HOME" ]; then
  LOGS_DIR="$HOME/bulk-gpml2-logs"
fi
LOG_FILE="$LOGS_DIR/bulk-gpml2.log"
INVALID_GPML_LIST="$LOGS_DIR/invalid-gpmls.txt"
CONVERTED_GPML_LIST="$LOGS_DIR/converted-gpmls.txt"

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

mkdir -p "$LOGS_DIR"
rm -f "$LOG_FILE" "$INVALID_GPML_LIST" "$CONVERTED_GPML_LIST"
touch "$LOG_FILE" "$INVALID_GPML_LIST" "$CONVERTED_GPML_LIST"

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
      reactsvg_f="$prefix.react.svg"

      if [ -s "$svg_f" ] && [ -s "$reactsvg_f" ]; then
        echo "$f" >> "$CONVERTED_GPML_LIST"
      elif [ -f "$svg_f" ] || [ -f "$reactsvg_f" ]; then
        if [ -f "$svg_f" ]; then
          echo "Removing empty file: $svg_f"
  	  sudo rm -f "$svg_f"
        fi
        if [ -f "$reactsvg_f" ]; then
          echo "Removing empty file: $reactsvg_f"
  	  sudo rm -f "$reactsvg_f"
        fi
      fi
    fi
  else
    echo "Removing empty file: $f"
    sudo rm -f "$f"
  fi
done
