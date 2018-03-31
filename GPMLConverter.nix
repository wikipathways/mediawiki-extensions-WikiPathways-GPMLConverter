let
  # copy located on dev.wikipathways.org server at /var/www/dev.wikipathways.org/wpi/bin/pathvisio_core.jar
  PathVisio = import ./pathvisio/default.nix;
  # copy located on dev.wikipathways.org server at /var/www/dev.wikipathways.org/wpi/bin/Biopax3GPML.jar
  PathVisioBiopaxPlugin = import ./pathvisio/biopax-plugin.nix;
  # copy located on dev.wikipathways.org server at /var/www/dev.wikipathways.org/wpi/bin/pathvisio_color_exporter.jar
  PathVisioColorExporterPlugin = import ./pathvisio/biopax-plugin.nix;
  # https://github.com/svanderburg/composer2nix
  PHPStuff = import ./composer.nix;
  # https://github.com/svanderburg/node2nix
  NodeStuff = import ./node-stuff.nix;
in [
  PathVisio
  PathVisioBiopaxPlugin
  PathVisioColorExporterPlugin
  PHPStuff
  NodeStuff
]
