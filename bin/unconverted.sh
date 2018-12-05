#!/usr/bin/env bash

comm -23 <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*.gpml | sed 's/.gpml$//' | sort -u) \
  <(find /home/wikipathways.org/images/wikipathways/ -name WP*_*svg | sed 's/.svg$//' | sed 's/.dark$//' | sed 's/.pvjssvg$//' | sort -u)
