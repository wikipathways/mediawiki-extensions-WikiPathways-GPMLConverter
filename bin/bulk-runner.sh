#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

CACHE_DIR="$dir_out"
LOGS_DIR="$dir_out/logs"
TARGET_USER="$USER"
if [ ! -z "$WP_DIR" ] && [ -r "$WP_DIR" ] && [ -w "$WP_DIR" ]; then
  CACHE_DIR="$WP_DIR/images/metabolite-pattern-cache"
  LOGS_DIR="$WP_DIR/logs"
  TARGET_USER='www-data'
elif [ ! -z "$HOME" ]; then
  CACHE_DIR="$HOME/metabolite-pattern-cache"
  LOGS_DIR="$HOME/bulk-gpml2-logs"
fi
LOG_FILE="$LOGS_DIR/bulk-gpml2.log"
INVALID_GPML_LIST="$LOGS_DIR/invalid-gpmls.txt"
UNCONVERTIBLE_GPML_LIST="$LOGS_DIR/unconvertible-gpmls.txt"
CONVERTED_GPML_LIST="$LOGS_DIR/converted-gpmls.txt"

LATEST_GPML_VERSION="2013a"

cleanup() {
  sudo -S chown "$TARGET_USER":wpdevs "$CACHE_DIR"
  sudo -S chmod 664 "$CACHE_DIR"
}

# Based on http://linuxcommand.org/lc3_wss0140.php
# and https://codeinthehole.com/tips/bash-error-reporting/
PROGNAME=$(basename $0)
error_exit() {

#	----------------------------------------------------------------
#	Function for exit due to fatal program error
#		Accepts 1 argument:
#			string containing descriptive error message
#	----------------------------------------------------------------


  echo "${PROGNAME}: ${1:-"Unknown Error"}" 1>&2

  read line file <<<$(caller)
  echo "An error occurred in line $line of file $file:" 1>&2
  sed "${line}q;d" "$file" 1>&2
  cleanup
  exit 1
}

trap error_exit ERR
# TODO what about the following?
# SIGHUP SIGINT SIGTERM
trap cleanup EXIT INT QUIT TERM

TARGET_FORMAT="$1"
TARGET_FORMAT="${TARGET_FORMAT:-*}"

mkdir -p "$CACHE_DIR"
mkdir -p "$LOGS_DIR"
rm -f "$INVALID_GPML_LIST" "$CONVERTED_GPML_LIST"
touch "$LOG_FILE" "$UNCONVERTIBLE_GPML_LIST" "$INVALID_GPML_LIST" "$CONVERTED_GPML_LIST"

# TODO: find has an option to call "-exec". Better than a for loop?
# TODO: a globbing pattern like this might be better:
# ls -la ./WP[0-9]*[0-9]_[0-9]*[0-9].[a-z][a-z][a-z]
for f in $(find "/home/wikipathways.org/images/wikipathways/" -name 'WP*_*.gpml'); do
  if [ -s "$f" ]; then
    # TODO: which is better?
    #xmlstarlet val "$f";
    #if [ $? -eq 0 ]; then ... fi
    is_valid=$((xmlstarlet val "$f" | grep ' valid') || echo '');
    if [ ! "$is_valid" ] || [ $(xmlstarlet sel -N 'gpml=http://pathvisio.org/GPML/2013a' -t -v 'count(/gpml:Pathway/gpml:Group/@GroupRef)' "$f") != "0" ]; then
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

# Convert all GPML files that we can
for f in $(comm -23 <(find "/home/wikipathways.org/images/wikipathways/" -name 'WP*_*.gpml' | sort -u) <(cat "$INVALID_GPML_LIST" "$UNCONVERTIBLE_GPML_LIST" "$CONVERTED_GPML_LIST" | sort -u)); do
  #echo '' | tee -a "$LOG_FILE"
  #echo '------------------------------------------------' | tee -a "$LOG_FILE"
  echo "$f" | tee -a "$LOG_FILE"

  # removing invalid identifiers
  sudo -S $(readlink $(which xmlstarlet)) ed -L -N gpml="http://pathvisio.org/GPML/$LATEST_GPML_VERSION" \
      -u "/gpml:Pathway/gpml:DataNode/gpml:Xref[@Database='undefined']/@Database" \
      -v '' \
      -u "/gpml:Pathway/gpml:DataNode/gpml:Xref[@ID='undefined']/@ID" \
      -v '' \
      "$f"

  dir_f=$(dirname "$f")
  base_f=$(basename -- "$f")
  ext_f="${base_f##*.}"
  stub_f="${base_f%.*}"
  prefix="$dir_f/$stub_f"

  # TODO: how do we want to pipe to stdout and to log file(s)?
  if [[ $TARGET_FORMAT == '*' ]]; then
    # Convert to all supported formats:
    #sudo -S -i "$SCRIPT_DIR/gpml2" "$f" 2> >(tee -a "$LOG_FILE" >&2);
    sudo -S -i "$SCRIPT_DIR/gpml2" "$f" 2>> "$LOG_FILE";
  else
    # Convert only as needed to get dynamic SVGs:
    #sudo -S -i "$SCRIPT_DIR/gpml2" "$f" "$prefix.react" 2> >(tee -a "$LOG_FILE" >&2);
    sudo -S -i "$SCRIPT_DIR/gpml2" "$f" "$prefix.react" 2>> "$LOG_FILE";
  fi
 
  # Make file permissions match what normal conversion would generate
  sudo -S chown "$TARGET_USER":"$TARGET_USER" "$prefix"*
  sudo -S chmod 644 "$prefix"*

  echo "$f" >> "$CONVERTED_GPML_LIST"
done
