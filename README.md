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

### Install for Development

```sh
$ git clone --recurse-submodules git@github.com:wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git GPMLConverter
```

### Troubleshooting

#### Not enough space

On the VM for vm1.wikipathways.org, not enough space was allocated for `/nix`.
To get around this problem, I had to take these steps before installing:

```
sudo mount -o bind /home/wikipathways/nix /nix
```

Comment out lines of 327 to 335 of file `nix-2.1.3-x86_64-linux/install-multi-user`
and then install Nix.

#### Too many files open

After installing Nix, try running `bash ./import.sh` instead of `nix-env -f default -i`.

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
ssh -o ProxyCommand='ssh 10.1.101.113 nc vm1.wikipathways.org 22' vm1.wikipathways.org -t 'cd /home/wikipathways.org/extensions/GPMLConverter; bash -l'
```

```
xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -i "//svg:g" -t attr -n "fill" -v "g" -u '//svg:g[contains(@typeof,'Edge')]/@fill' -v 'when' WP2868_98142.svg; echo ''
xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -u '//svg:g[contains(@typeof,'Edge')]/svg:g/@fill' --expr "string(../svg:path/@class)" WP2868_98142.svg; echo ''

xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -i "/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')]" -t attr -n "fill" -v "g" -u '/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')]/@fill' -x "string(../svg:g/@fill)" WP2868_98142.svg; echo ''

xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -m '//svg:g[contains(@typeof,'Edge')]/svg:g//svg:path' "svg:svg" WP2868_98142.svg; echo ''

xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -m '//svg:g[contains(@typeof,'Edge')]/svg:g//svg:path' "node()" WP2868_98142.svg; echo ''

https://tw.saowen.com/a/8d7b95b1b078da209f45dc5e6211b79157d759417511baeaf2bf8623599e7d91

xmlstarlet sel -N svg='http://www.w3.org/2000/svg' -t -v '/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')][last()]/@class' WP2868_98142.svg; echo ''

xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -m '/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')][last()]/svg:g/svg:path' "/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')][last()]" WP2868_98142.svg; echo ''

xmlstarlet sel -N svg='http://www.w3.org/2000/svg' -t -v 'count(/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')])' WP2868_98142.svg; echo ''

for 
xmlstarlet ed -N svg='http://www.w3.org/2000/svg' -m '/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')][last()]/svg:g/svg:path' "/svg:svg/svg:g/svg:g[contains(@typeof,'Edge')][last()]" WP2868_98142.svg; echo ''

rm wow.*; ./bin/gpml2 /home/wikipathways.org/images/wikipathways/a/a4/WP2868_98142.gpml wow.svg
```
