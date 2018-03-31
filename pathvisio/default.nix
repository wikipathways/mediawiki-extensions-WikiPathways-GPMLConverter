{ stdenv, pkgs, mavenbuild }:

with pkgs.javaPackages;

let
  poms = import ../poms.nix { inherit fetchMaven; };
in rec {
  PathVisioRec = { mavenDeps, sha512, version, skipTests ? true, quiet ? true }: mavenbuild rec {
    inherit mavenDeps sha512 version skipTests quiet;

    name = "PathVisio-${version}";
    src = pkgs.fetchFromGitHub {
      inherit sha512;
      owner = "PathVisio";
      repo = "pathvisio";
      rev = "v${version}";
    };
    m2Path = "/com/PathVisio/pathvisio/${version}";

    meta = {
      homepage = https://pathvisio.org;
      description = "pathway editor, visualization and analysis software";
      license = stdenv.lib.licenses.apache2;
      platforms = stdenv.lib.platforms.all;
      maintainers = with stdenv.lib.maintainers;
        [ pathvisio ];
    };
  };

  PathVisio_3_3_0 = PathVisioRec {
    mavenDeps = [];
    sha512 = "3kv5z1i02wfb0l5x3phbsk3qb3wky05sqn4v3y4cx56slqfp9z8j76vnh8v45ydgskwl2vs9xjx6ai8991mzb5ikvl3vdgmrj1j17p2";
    version = "3.3.0";
  };
}
