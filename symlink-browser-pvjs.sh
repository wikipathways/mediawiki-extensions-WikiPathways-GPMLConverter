#! /usr/bin/env bash

executable_pvjs=`readlink /nix/var/nix/profiles/default/bin/pvjs`;
executable_pvjs_dir="`dirname $executable_pvjs`/..";
browser_pvjs="$executable_pvjs_dir/@wikipathways/pvjs/dist/pvjs.js"

browser_pvjs_symlink="./pvjs.js";
rm "$browser_pvjs_symlink";
ln -s "$browser_pvjs" "$browser_pvjs_symlink";

echo "Created symlink to in-browser version of pvjs:";
echo `ls -l $browser_pvjs_symlink`;
