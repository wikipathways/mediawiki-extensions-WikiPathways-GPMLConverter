#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

LOG_FILE="/home/wikipathways.org/bulk-conversion.log"
INVALID_GPML_LIST="/home/wikipathways.org/invalid-gpml-list.txt"
rm "$INVALID_GPML_LIST"
touch "$INVALID_GPML_LIST"

# drawing on https://codeinthehole.com/tips/bash-error-reporting/
function handle_error {
  read line file <<<$(caller)
  echo "An error occurred in line $line of file $file:" | tee -a "$LOG_FILE" >&2
  sed "${line}q;d" "$file" | tee -a "$LOG_FILE" >&2
  exit 1
}

trap handle_error ERR

xmlstarlet='/nix/store/dwigzvk3yrbai9mxh3k2maqsghfjqgr6-xmlstarlet-1.6.1/bin/xmlstarlet'
for f in $(find /home/wikipathways.org/images/ -name 'WP*.gpml'); do
  # TODO: which is better?
  #$xmlstarlet val "$f";
  #if [ $? -eq 0 ]; then ... fi
  is_valid=$(($xmlstarlet val "$f" | grep ' valid') || echo '');
  echo "$f"
  if [[ -n "$is_valid" ]]; then
    dir_f=$(dirname "$f")
    base_f=$(basename -- "$f")
    ext_f="${base_f##*.}"
    stub_f="${base_f%.*}"
    prefix="$dir_f/$stub_f"

    # If you want to convert all:
    #sudo -i "$SCRIPT_DIR/bin/gpml2" "$f";
    # Convert the minimum required to get svgs:
    sudo -i "$SCRIPT_DIR/bin/gpml2" "$f" "$prefix.svg";
   
    sudo chown www-data:www-data "$prefix".*
    sudo chmod 644 "$prefix".*
  else
    echo "  Warning: invalid GPML"
    echo " "
    echo "$f" >> "$INVALID_GPML_LIST"
  fi
done
