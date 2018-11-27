with import <nixpkgs> { config.allowUnfree = true; };
let
  custom = import ./nixpkgs-custom/all-custom.nix;
  pathvisio = callPackage ./nixpkgs-custom/pathvisio/default.nix {
    organism="Homo sapiens";
    headless=true;
    genes=false;
    interactions=false;
    metabolites=false;
    # NOTE: this seems high, but I got an error
    #       regarding memory when it was lower.
    memory="2048m";
  };
in [
  pathvisio
  custom.bridgedb
  custom.gpml2pvjson
  custom.pvjs

  pkgs.php
  php72Packages.composer

  pkgs.coreutils
  pkgs.xmlstarlet
  pkgs.bc
] ++ (if stdenv.isDarwin then [] else [])
