let
  PathVisio = import ./pathvisio/default.nix;
  BiopaxPlugin = import ./pathvisio/biopax-plugin.nix;
  # https://github.com/svanderburg/composer2nix
  PHPStuff = import ./composer.nix;
  # https://github.com/svanderburg/node2nix
  NodeStuff = import ./node-stuff.nix;
in [
  PathVisio
  BiopaxPlugin
  PHPStuff
  NodeStuff
]
