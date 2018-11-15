#!/usr/bin/env bash

xmlstarlet='/nix/store/dwigzvk3yrbai9mxh3k2maqsghfjqgr6-xmlstarlet-1.6.1/bin/xmlstarlet'
for f in $(find /home/wikipathways.org/images/ -name 'WP*.gpml' | head -n 100); do
  $xmlstarlet val "$f";

  if [ $? -eq 0 ]; then
    sudo -i "$(pwd)/gpmlconverter" "$f";
   
    dir_f=$(dirname "$f")
    base_f=$(basename -- "$f")
    ext_f="${base_f##*.}"
    stub_f="${base_f%.*}"
    prefix="$dir_f/$stub_f"
   
    sudo chown www-data:www-data "$prefix".*
    sudo chmod 644 "$prefix".*
  fi
done
