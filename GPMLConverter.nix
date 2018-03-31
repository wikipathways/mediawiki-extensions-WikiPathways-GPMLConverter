let
  PathVisio = import ./pathvisio/default.nix;
  BiopaxPlugin = import ./pathvisio/biopax-plugin.nix;
  PHPStuff = import ./vim.nix;
  NodeStuff = import ./default.nix;
in [
  PathVisio
  BiopaxPlugin
  PHPStuff
  NodeStuff
]
