with import <nixpkgs> { config.allowUnfree = true; };
let
  composerDeps = callPackage ./nix-dependencies/composer-dependencies/default.nix { noDev = true; };
  nodeDeps = callPackage ./nix-dependencies/node-dependencies/default.nix { nodejs = pkgs."nodejs-6_x"; };
  pathvisio = callPackage ./nix-dependencies/pathvisio/default.nix {};
  nixos = import <nixos> { config.allowUnfree = true; };
in [
  composerDeps
  nodeDeps
  pathvisio
] ++ (if stdenv.isDarwin then [] else [])
