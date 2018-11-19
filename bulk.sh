#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")

LOG_FILE="$HOME/bulk$TIMESTAMP.log"
INVALID_GPML_LIST="$HOME/invalid-gpml$TIMESTAMP.txt"
touch "$LOG_FILE"
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

TARGET_FORMAT="$1"
TARGET_FORMAT="${TARGET_FORMAT:-*}"

xmlstarlet='/nix/store/dwigzvk3yrbai9mxh3k2maqsghfjqgr6-xmlstarlet-1.6.1/bin/xmlstarlet'
#for f in $(find /home/wikipathways.org/images/ -name 'WP*.gpml'); do
for f in $(find /home/wikipathways.org/images/wikipathways/7/78 -name 'WP*.gpml'); do
  # TODO: which is better?
  #$xmlstarlet val "$f";
  #if [ $? -eq 0 ]; then ... fi
  is_valid=$(($xmlstarlet val "$f" | grep ' valid') || echo '');
  echo '' | tee -a "$LOG_FILE"
  echo '------------------------------------------------' | tee -a "$LOG_FILE"
  echo "$f" | tee -a "$LOG_FILE"
  if [[ -n "$is_valid" ]]; then
    dir_f=$(dirname "$f")
    base_f=$(basename -- "$f")
    ext_f="${base_f##*.}"
    stub_f="${base_f%.*}"
    prefix="$dir_f/$stub_f"

    if [[ $TARGET_FORMAT == '*' ]]; then
      # If you want to convert all:
      sudo -i "$SCRIPT_DIR/bin/gpml2" "$f" 2> >(tee -a "$LOG_FILE" >&2);
    else
      # Convert the minimum required to get svgs:
      sudo -i "$SCRIPT_DIR/bin/gpml2" "$f" "$prefix.$TARGET_FORMAT" 2> >(tee -a "$LOG_FILE" >&2);
    fi
   
    sudo chown www-data:www-data "$prefix".*
    sudo chmod 644 "$prefix".*
  else
    echo "  Warning: invalid GPML" | tee -a "$LOG_FILE"
    echo " "
    echo "$f" >> "$INVALID_GPML_LIST"
  fi
done
