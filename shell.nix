with import <nixpkgs> { config.allowUnfree = true; };
let
  deps = import ./deps.nix;
in
  pkgs.mkShell {
    buildInputs = deps;
    shellHook = ''
      #export PATH="$HOME/Documents/safer-npm:$PATH"
    '';
}
