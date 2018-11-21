# GPMLConverter

## How to Install

1. Clone Repo

```sh
$ git submodule add https://github.com/wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git GPMLConverter
$ cd GPMLConverter
```

Note: this is the SSH URL, which you can use as an alternative to the HTTPS URL:
> git@github.com:wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git

2. Install GPMLConverter Dependencies

```sh
$ sudo -i bash "$(pwd)/install"
```

## How to Use
Try converting some data.

The bare metal version
```sh
$ curl "http://vm1.wikipathways.org/Pathway:WP554?action=raw&oldid=77712" | \
	gpml2pvjson --id http://identifiers.org/wikipathways/WP554 \
	--pathway-version 77712 > WP554.json
```

Using the included PHP scripts when this is installed as a MediaWiki extension:
``` sh
$ php maintenance/convertPathway.php -o json -r 77712 WP554
The JSON for Revision #77712 of Pathway WP554 (ACE Inhibitor Pathway) is stored at WP554.json
```

or another format:
``` sh
$ php maintenance/convertPathway.php -o SVG -r 77712 WP554
An SVG file for Revision #77712 of Pathway WP554 (ACE Inhibitor Pathway) stored at WP554.svg
```

```
for f in $(find /home/wikipathways.org/images/wikipathways/ -name 'WP*.svg'); do sudo rm -f "$f"; done
find /home/wikipathways.org/images/ -name 'WP*.gpml'

rm data/WP*{txt,tsv,json,svg,bpss,owl}; 
gpml2 --id "http://identifiers.org/wikipathways/WP2722" /home/wikipathways.org/images/wikipathways/a/a1/WP2722_93913.gpml data/WP2722_93913.svg

rm data/WP*{txt,tsv,json,svg,bpss,owl}; gpml2 --id "http://identifiers.org/wikipathways/WP2722" --pathway-version="93913" /home/wikipathways.org/images/wikipathways/a/a1/WP2722_93913.gpml data/WP2722_93913.svg

cd /home/wikipathways.org/extensions/GPMLConverter; nohup ./bulk.sh >bulk.log 2>&1 &
```

We are currently adding the id mappings to the JSON, but if we wanted to, we'd also have this option, using the gene list text output:
```
   txt_f="$dir_out/$stub_out.txt"
   gpml2 "$path_in" "$txt_f"
   mappings_f="$dir_out/$stub_out.idmappings.tsv"
   tail -n +2 "$txt_f" | sort -u | bridgedb xrefs -f "tsv" "Homo sapiens" 1 0 ensembl ncbigene uniprot wikidata > "$mappings_f"
```

```
pathvisio convert /home/wikipathways.org/images/wikipathways/1/1f/WP269_91030.gpml WP269_91030.txt 
bridgedb xrefs -f "tsv" --headers=true -i 3 \
	"Drosophila melanogaster" "Database" "Identifier" \
	ensembl hgnc.symbol ncbigene uniprot hmdb chebi wikidata \
	< ./WP269_91030.txt
```
