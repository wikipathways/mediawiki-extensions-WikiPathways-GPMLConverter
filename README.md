# GPMLConverter

## How to Install

1. Clone Repo

```sh
git submodule add https://github.com/wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git GPMLConverter
cd GPMLConverter
```

Note: this is the SSH URL, which you can use as an alternative to the HTTPS URL:
> git@github.com:wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git

2. Install Nix (multi-user)
Install [Nix](https://nixos.org/nix/). If running a Debian system like Ubuntu, you can install with [this script](https://github.com/ariutta/nix-install-deb-multi-user).

3. Install GPMLConverter Dependencies

Run the `install-dependencies` script:
```sh
sudo -i $(pwd)/install-dependencies
```

## How to Update
If you ever need to update the GPMLConverter dependencies, update `node-packages.json`. Then run the `install-dependencies` script again, just like above.

## How to Use
Try converting some data:

```sh
curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=json" | \
jq -r .data | base64 --decode | \
gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=xml" | xpath "*/ns1:data/text()" | base64 --decode | gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "https://cdn.rawgit.com/wikipathways/pvjs/e47ff1f6/test/input-data/troublesome-pathways/WP1818_73650.gpml" | gpml2pvjson --id "http://identifiers.org/wikipathways/WP1818" --pathway-version "73650" > "WP1818_73650.json"

bridgedb xrefs "Human" "Ensembl" "ENSG00000111186"
pvjs json2svg "WP1818_73650.json"
```
