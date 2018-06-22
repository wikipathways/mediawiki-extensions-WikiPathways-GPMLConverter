# some docs
# https://developer.apple.com/library/archive/documentation/Java/Conceptual/Java14Development/03-JavaDeployment/JavaDeployment.html

# some of the CLI args allowed
# https://github.com/PathVisio/pathvisio/blob/master/modules/org.pathvisio.launcher/src/org/pathvisio/launcher/PathVisioMain.java
{ stdenv, fetchurl, fetchFromGitHub, makeDesktopItem, unzip, ant, jdk, options ? {
  desktop = true;
  interactions = false;
  metabolites = false;
  # TODO look into downloading the dbs, if requested
} }:

let
  baseName = "PathVisio";
  version = "3.3.0";
in
stdenv.mkDerivation rec {
  name = "${baseName}-${version}";

  # should this be nativeBuildInputs or just buildInputs?
  nativeBuildInputs = [ unzip ant jdk ];

  javaPath = "${jdk.jre}/bin/java";

  src = fetchFromGitHub {
    owner = "pathvisio";
    repo = "PathVisio";
    rev = "61f15de96b676ee581858f0485f9c6d8f61a3476";
    sha256 = "1n2897290g6kph1l04d2lj6n7137w0gnavzp9rjz43hi1ggyw6f9";
  };

  srcBiopax3GPML = fetchurl {
    url = "https://cdn.rawgit.com/wikipathways/wikipathways.org/e8fae01e/wpi/bin/Biopax3GPML.jar";
    sha256 = "1jm5khh6n78fghd7wp0m5dcb6s2zp23pgsbw56rpajfxgx1sz7lg";
  };

  srcPngIcon = "${src}/www/bigcateye_135x135.png";

  iconSrc = "${src}/lib-build/bigcateye.icns";

#  # WARNING: this is very large
#  srcMetabolites = fetchurl {
#    url = "http://bridgedb.org/data/gene_database/metabolites_20180508.bridge.zip";
#    sha256 = "13l3p755k3k4vp2zg24fbsmprbgml75cg105qmzs85xipz92cqv4";
#  };

#  srcInteractions = fetchurl {
#    url = "http://developers.pathvisio.org/data/gene_database/interactions_20180425.bridge";
#    sha256 = "0bijs9jvl0362l96ar11zndziwlccyxysdqlbglwl34p733x1p64";
#  };

  postPatch = ''
    for f in $out/bin/*; do
      substituteInPlace $out/scripts/* \
            --replace "java -ea" "${javaPath} -ea"
    done
  '';

  buildPhase = (if ! options.desktop then "ant"
  else if stdenv.system == "x86_64-darwin" then "ant appbundler"
  else "ant exe") + ''

    mkdir -p ./bin
    cp ./scripts/gpmldiff.sh ./bin/gpmldiff
    cp ./scripts/gpmlpatch.sh ./bin/gpmlpatch

    cat > ./bin/gpmlconvert <<EOF
    #! $shell
    CLASSPATH=../modules/org.pathvisio.core.jar:../lib/*:${srcBiopax3GPML}
    exec ${javaPath} -ea -classpath \$CLASSPATH org.pathvisio.core.util.Converter "\$@"
    EOF

    chmod a+x ./bin/gpmlconvert
    chmod a+x ./bin/gpmldiff
    chmod a+x ./bin/gpmlpatch
  '';

  doCheck = true;

  checkPhase = ''
    # TODO are we running the existing tests?
    cd ./bin
    ./gpmlconvert ../example-data/Hs_Apoptosis.gpml Hs_Apoptosis.pdf
    rm Hs_Apoptosis.pdf
    ./gpmlconvert ../example-data/Hs_Apoptosis.gpml Hs_Apoptosis.png
    rm Hs_Apoptosis.png
    ./gpmlconvert ../example-data/Hs_Apoptosis.gpml Hs_Apoptosis.owl
    rm Hs_Apoptosis.owl
    ./gpmldiff ../example-data/Hs_Apoptosis.gpml ../example-data/Hs_Cell_cycle_KEGG.gpml
    rm Hs_Apoptosis.bpss
    cd ../
  '';

  # TODO is this just for Linux?
  desktopItem = makeDesktopItem {
    name = name;
    exec = ''
      ${javaPath} -jar -Dfile.encoding=UTF-8 $out/lib/pathvisio.jar "\$@"
    '';
    icon = "${srcPngIcon}";
    desktopName = baseName;
    # TODO what is genericName?
    genericName = "IDE";
    comment = meta.description;
    # TODO what is categories?
    categories = "Development;";
    mimeType = "application/gpml+xml";
  };

  installPhase = ''
    mkdir -p $out/bin $out/lib $out/modules $out/share

    cp -r ./bin/* $out/bin/
    for f in $out/bin/*; do
      substituteInPlace $f \
            --replace "../lib" "$out/lib" \
            --replace "../modules" "$out/modules"
    done

    cp -r ./lib/* $out/lib/
    cp -r ./modules/* $out/modules/

#    echo 'Sample commands'
#    echo 'wget https://cdn.rawgit.com/PathVisio/GPML/fa76a73d/test/2013a/WP1243_69897.gpml'
#    echo 'GPML -> BioPAX/OWL'
#    echo 'gpmlconvert WP1243_69897.gpml WP1243_69897.owl'
#    echo 'GPML -> PDF'
#    echo '/bin/gpmlconvert WP1243_69897.gpml WP1243_69897.pdf'
#    echo 'GPML -> PNG'
#    echo 'gpmlconvert WP1243_69897.gpml WP1243_69897.png'
  '' + (
  if ! options.desktop then ''
    echo 'desktop functionality not enabled'
  '' else if stdenv.system == "x86_64-darwin" then ''
    mkdir -p $out/share/Applications
    unzip -o release/${baseName}.app.zip -d $out/share/Applications/
  '' else ''
    mkdir -p $out/share/applications
    ln -s ${desktopItem}/share/applications/* $out/share/applications/
  '');

  meta = with stdenv.lib;
    { description = "A tool to edit and analyze biological pathways";
      homepage = https://www.pathvisio.org/;
      license = licenses.asl20;
      maintainers = with maintainers; [ ];
      platforms = platforms.all;
    };
}
