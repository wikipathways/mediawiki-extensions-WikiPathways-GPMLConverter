# GPMLConverter

## Install

Installation assumes basic utilities like `sudo`, `getent` and `git` are installed. If they are not, install them first.

1. Clone repo and submodule repos. You can either install as a Mediawiki extension:

```sh
$ cd /home/wikipathways.org/extensions # or wherever your extensions directory is located
$ git submodule add https://github.com/wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git GPMLConverter
$ cd GPMLConverter
$ git submodule update -q --init --recursive
```

Or install as a standalone utility (for example, during development):

```sh
$ git clone --recurse-submodules git@github.com:wikipathways/mediawiki-extensions-WikiPathways-GPMLConverter.git GPMLConverter
$ cd GPMLConverter
```

2. Install GPMLConverter and its dependencies

Provide a target user account, e.g., `wikipathways`. If this user doesn't already exist, it'll be created.

You'll also need to respond to several prompts.

```sh
$ "$(pwd)/install" <username>
```

Responses to give to prompts:
* Do you want to continue?: `y`
* Would you like to see a more detailed list of what we will do?: `n`
* Can we use sudo?: `y`
* Ready to continue?: `y`

## How to Use
Try converting some data using one of the following options.

### The bare metal version
```sh
$ curl "https://vm1.wikipathways.org/Pathway:WP554?action=raw&oldid=77712" | \
	gpml2pvjson --id http://identifiers.org/wikipathways/WP554 \
	--pathway-version 77712 > WP554.json
```

### Using the included PHP scripts when this is installed as a MediaWiki extension:
``` sh
$ php maintenance/convertPathway.php -o json -r 77712 WP554
The JSON for Revision #77712 of Pathway WP554 (ACE Inhibitor Pathway) is stored at WP554.json
```

or another format:
``` sh
$ php maintenance/convertPathway.php -o SVG -r 77712 WP554
An SVG file for Revision #77712 of Pathway WP554 (ACE Inhibitor Pathway) stored at WP554.svg
```

## Troubleshooting

#### Not enough space

On the VM for vm1.wikipathways.org, not enough space was allocated for `/nix`.
To get around this problem, I had to take these steps before installing:

```
sudo mkdir -p /nix
sudo mkdir -p /home/wikipathways/nix
sudo mount -o bind /home/wikipathways/nix /nix
bash install-nix-mounted --daemon
./install wikipathways
```

The customized install script `install-nix-mounted` disables the check for the existence of
`/nix` on lines of 327 to 335 of file `nix-2.1.3-x86_64-linux/install-multi-user`.

#### Too many files open

After installing Nix, try running `bash ./import.sh` instead of `nix-env -f default -i`.
