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
