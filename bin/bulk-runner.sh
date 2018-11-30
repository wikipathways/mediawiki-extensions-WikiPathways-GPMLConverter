#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

LATEST_GPML_VERSION="2013a"

CACHE_DIR="$dir_out"
WP_IMAGES_DIR="$WP_DIR/images"
if [ ! -z "$WP_DIR" ] && [ -r "$WP_IMAGES_DIR" ] && [ -w "$WP_IMAGES_DIR" ]; then
  CACHE_DIR="$WP_IMAGES_DIR/metabolite-pattern-cache"
elif [ ! -z "$HOME" ]; then
  CACHE_DIR="$HOME/metabolite-pattern-cache"
fi

INVALID_GPML_LIST="$HOME/invalid-gpmls.txt"
UNCONVERTIBLE_GPML_LIST="$HOME/unconvertible-gpmls.txt"
CONVERTED_GPML_LIST="$HOME/converted-gpmls.txt"

TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")
LOG_FILE="$HOME/bulk$TIMESTAMP.log"
touch "$LOG_FILE"

cleanup() {
  sudo -S chown www-data:wpdevs "$CACHE_DIR"
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

# Convert all GPML files that we can
for f in $(comm -23 <(find /home/wikipathways.org/images/wikipathways/ -name 'WP*.gpml' | sort -u) <(cat "$INVALID_GPML_LIST" "$UNCONVERTIBLE_GPML_LIST" "$CONVERTED_GPML_LIST" | sort -u)); do
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
    #sudo -S -i "$SCRIPT_DIR/bin/gpml2" "$f" 2> >(tee -a "$LOG_FILE" >&2);
    sudo -S -i "$SCRIPT_DIR/bin/gpml2" "$f" 2>> "$LOG_FILE";
  else
    # Convert only as needed to get dynamic SVGs:
    #sudo -S -i "$SCRIPT_DIR/bin/gpml2" "$f" "$prefix.react" 2> >(tee -a "$LOG_FILE" >&2);
    sudo -S -i "$SCRIPT_DIR/bin/gpml2" "$f" "$prefix.react" 2>> "$LOG_FILE";
  fi
 
  # Make file permissions match what normal conversion would generate
  sudo -S chown www-data:www-data "$prefix"*
  sudo -S chmod 644 "$prefix"*
done
