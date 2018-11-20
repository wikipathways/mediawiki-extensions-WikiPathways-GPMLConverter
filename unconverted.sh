#!/usr/bin/env bash

sort <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.gpml | sed 's/.gpml//') \
  <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.svg | sed 's/.svg//' | sed 's/.dark//' | sed 's/.react//') | \
  uniq -u
