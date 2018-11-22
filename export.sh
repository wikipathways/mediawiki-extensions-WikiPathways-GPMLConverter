#!/usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

gpmlconverter_f="gpmlconverter"
nix-store --export $(nix-store -qR $(nix-instantiate "$SCRIPT_DIR/default.nix")) > "$gpmlconverter_f"

#local_store_dir="$SCRIPT_DIR/local-store"
#mkdir "$local_store_dir"
#for f in $(nix-instantiate "$SCRIPT_DIR/default.nix"); do
#  nix-store --dump $f > "$local_store_dir/"$(basename $f)
#done

tar --remove-files -jcf "$gpmlconverter_f.tar.bz2" "$gpmlconverter_f"
