#!/usr/bin/env bash

comm -23 <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.gpml | sed 's/.gpml$//' | sort -u) \
  <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.svg | sed 's/.svg$//' | sed 's/.dark$//' | sed 's/.react$//' | sort -u)

#sort <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.gpml | sed 's/.gpml$//') \
#  <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.svg | sed 's/.svg$//' | sed 's/.dark$//' | sed 's/.react$//') | \
#  uniq -u
