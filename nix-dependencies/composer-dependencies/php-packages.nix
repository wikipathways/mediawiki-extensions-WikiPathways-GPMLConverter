{composerEnv, fetchurl, fetchgit ? null, fetchhg ? null, fetchsvn ? null, noDev ? false}:

let
  packages = {};
  devPackages = {};
in
composerEnv.buildPackage {
  inherit packages devPackages noDev;
  name = "wikipathways-gpml-converter";
  src = ./.;
  executable = false;
  symlinkDependencies = false;
  meta = {
    homepage = https://github.com/wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter;
    license = "Apache-2.0";
  };
}