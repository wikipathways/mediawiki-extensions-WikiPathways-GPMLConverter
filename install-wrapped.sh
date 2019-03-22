#! /usr/bin/env bash

# see https://stackoverflow.com/a/246128/5354298
get_script_dir() { echo "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"; }
SCRIPT_DIR=$(get_script_dir)

# see https://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
is_installed() { hash $1 2>/dev/null || { false; } }
exit_if_not_installed() { is_installed $1 || { echo >&2 "I require $1 but it's not installed. Aborting. See https://nixos.org/nix/manual/#sec-prerequisites-source."; exit 1; } }
function ensure_installed() {
	if ! is_installed $1 ; then
		echo "installing missing dependency '$1'...";
		eval $2;
	fi
}

echo "installing/updating GPMLConverter"

echo "ensuring nix dependencies are installed...";

exit_if_not_installed "sudo";
exit_if_not_installed "getent";
ensure_installed "curl" "sudo apt install curl";
ensure_installed "rsync" "sudo apt install rsync";

# Install multi-user Nix.
# see https://nixos.org/nix/manual/#sect-multi-user-installation
if [ ! -d "/nix" ]; then
	if ! ensure_installed "nix-env" "sh <(curl https://nixos.org/nix/install) --daemon" ; then
		printf "\e[1;31merror\e[0m: could not install nix\n";
		exit 1;
	fi
fi

# TODO: this was giving me errors
# Restrict access to Nix operations to root and a group called nix-users.
# see https://nixos.org/nix/manual/#idm140737318344544
if ! getent group "nix-users" > /dev/null; then
	sudo groupadd -r nix-users
	sudo chgrp nix-users /nix/var/nix/daemon-socket
	sudo chmod ug=rwx,o= /nix/var/nix/daemon-socket
fi

TARGET_USER="$1"

if [ -z "$TARGET_USER" ]; then
	printf "\e[1;31merror\e[0m: target user not specified\n";
	exit 1;
elif ! getent passwd "$TARGET_USER" > /dev/null; then
	echo "adding new user $TARGET_USER"
	sudo useradd -m "$TARGET_USER"
fi

#echo "ensuring Nix is up to date...";
#sudo -u "$TARGET_USER" -i nix-channel --update

# TODO: if the target user is not the current user, should
# we enable Nix for the current user as well?
TARGET_USER_HOME="/home/$TARGET_USER"

#PREFIX=
#if [ -d "/usr/local/nix" ]; then
#	PREFIX="/usr/local/nix"
#elif [ -d "/nix/var/nix/profiles/default" ]; then
#	PREFIX="/nix/var/nix/profiles/default"
#fi
#NIXSH="$PREFIX/etc/profile.d/nix.sh"

NIXSH=
if [ -e "$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh" ]; then
	NIXSH="$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh"
elif [ -e "/usr/local/etc/profile.d/nix.sh" ]; then
	NIXSH="/usr/local/etc/profile.d/nix.sh"
elif [ -e "/nix/var/nix/profiles/default/etc/profile.d/nix.sh" ]; then
	NIXSH="/nix/var/nix/profiles/default/etc/profile.d/nix.sh"
else
	printf "\e[1;31merror\e[0m: cannot find nix.sh\n";
	exit 1;
fi

#echo "if [ -e \"/usr/local/etc/profile.d/nix.sh\" ]; then . \"/usr/local/etc/profile.d/nix.sh\"; fi" >> "$TARGET_USER_HOME/.profile"
#echo "if [ -e \"$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh\" ]; then . \"$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh\"; fi" >> "$TARGET_USER_HOME/.profile"

# TODO
#sudo chown wikipathways:wikipathways /home/wikipathways/
sudo usermod -a -G nix-users "$TARGET_USER"
TARGET_USER_HOME_PROFILE="$TARGET_USER_HOME/.profile"
if [ ! -e "$TARGET_USER_HOME_PROFILE" ]; then
	sudo touch "$TARGET_USER_HOME_PROFILE"
	sudo chown "$TARGET_USER":"$TARGET_USER" "$TARGET_USER_HOME_PROFILE"
	sudo chmod u+rw "$TARGET_USER_HOME_PROFILE"
fi

if ! grep nix-setup-user "$TARGET_USER_HOME/.profile" > /dev/null; then
	echo "" | sudo -u wikipathways -i tee -a '$HOME/.profile' > /dev/null
	echo "# Added by nix-setup-user" | sudo -u wikipathways -i tee -a '$HOME/.profile' > /dev/null
	echo "export NIX_REMOTE=daemon" | sudo -u wikipathways -i tee -a '$HOME/.profile' > /dev/null
	echo "if [ -e \"$NIXSH\" ]; then . \"$NIXSH\"; fi" | sudo -u wikipathways -i tee -a '$HOME/.profile' > /dev/null
	sudo -u wikipathways -i . "$NIXSH"

#	sudo -i su $TARGET_USER -c "echo \"# Added by nix-setup-user\" >> \"$TARGET_USER_HOME/.profile\""
#	sudo -i su $TARGET_USER -c "echo \"export NIX_REMOTE=daemon\" >> \"$TARGET_USER_HOME/.profile\""
#	sudo -i su $TARGET_USER -c "echo \"if [ -e \\\"$NIXSH\\\" ]; then . \\\"$NIXSH\\\"; fi\" >> \"$TARGET_USER_HOME/.profile\""
#	sudo -i su $TARGET_USER -c ". \"$NIXSH\""
fi

#SYMLINK_PATH="$TARGET_USER_HOME/.nix-profile"
#PROFILE_DIR="/nix/var/nix/profiles/per-user/$TARGET_USER"
#echo "Creating profile $PROFILE_DIR..."
#echo "Profile symlink: $SYMLINK_PATH"
#if [ -e "$SYMLINK_PATH" ]; then rm "$SYMLINK_PATH"; fi
#mkdir -p "$PROFILE_DIR"
#chown "$TARGET_USER" "$PROFILE_DIR"
#ln -s "$PROFILE_DIR/profile" "$SYMLINK_PATH"
#chown -h "$TARGET_USER" "$SYMLINK_PATH"
#touch "$TARGET_USER_HOME/.profile"
#echo "" >> "$TARGET_USER_HOME/.profile"
#echo "# Added by nix-setup-user" >> "$TARGET_USER_HOME/.profile"
#echo "export NIX_REMOTE=daemon" >> "$TARGET_USER_HOME/.profile"
#echo "if [ -e \"/usr/local/etc/profile.d/nix.sh\" ]; then . \"/usr/local/etc/profile.d/nix.sh\"; fi" >> "$TARGET_USER_HOME/.profile"
#echo "if [ -e \"$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh\" ]; then . \"$TARGET_USER_HOME/.nix-profile/etc/profile.d/nix.sh\"; fi" >> "$TARGET_USER_HOME/.profile"

echo "installing/updating GPMLConverter...";
# Currently installing as root, hoping that means it'll always be available as default.
# TODO: what is the default profile vs. the root profile?
# TODO: should/can we create an apache user and install as that user instead?
if ! sudo -u $TARGET_USER -i nix-env -f $SCRIPT_DIR/default.nix -i ; then
	printf "\e[1;31merror\e[0m: could not install/update GPMLConverter\n";
	exit 1;
fi

#printf "\e[1;32mreducing Nix disk space usage...\e[0m\n"
echo "Reducing Nix disk space usage..."
if ! sudo -u $TARGET_USER -i nix-collect-garbage -d ; then
	printf "\e[1;31merror\e[0m: nix-collect-garbage -d failed\n";
	exit 1;
fi
if ! sudo -u $TARGET_USER -i nix-store --optimise ; then
	printf "\e[1;31merror\e[0m: nix-store --optimise\n";
	exit 1;
fi

#APACHE_ENV_VARS_PATH="/etc/apache2/envvars";
#NIX_BIN_PATH_GLOBAL="/root/.nix-profile/bin";
#if ! cat $APACHE_ENV_VARS_PATH | grep $NIX_BIN_PATH_GLOBAL; then
#	echo '' >> $APACHE_ENV_VARS_PATH;
#	echo '# Added to allow WikiPathways to access CLI tools installed via nix.' >> $APACHE_ENV_VARS_PATH;
#	echo "PATH=\$PATH:$NIX_BIN_PATH_GLOBAL" >> $APACHE_ENV_VARS_PATH;
#fi

# TODO: do we want to symlink or copy?
#	#echo "Creating symlink to browser version of pvjs.js";
#	executable_pvjs_symlink=`which pvjs`;
#	executable_pvjs=`readlink $executable_pvjs_symlink`;
#	executable_pvjs_dir="`dirname $executable_pvjs`/..";
#	browser_pvjs=`readlink -f "$executable_pvjs_dir/@wikipathways/pvjs/dist/pvjs.vanilla.js"`;
#	browser_pvjs_symlink="./modules/pvjs.vanilla.js";
#	rm -f "$browser_pvjs_symlink";
#	ln -s "$browser_pvjs" "$browser_pvjs_symlink";
#
#	echo "Symlink created:";
#	echo `ls -l $browser_pvjs_symlink`;

printf "\e[1;32mSuccess! GPMLConverter installed/updated.\e[0m\n"

executable_pvjs_symlink=$(sudo -u $TARGET_USER -i which pvjs);
executable_pvjs=$(sudo -u $TARGET_USER -i readlink $executable_pvjs_symlink);
executable_pvjs_dir="$(dirname $executable_pvjs)/..";
browser_pvjs_dir=$(sudo -u $TARGET_USER -i readlink -f "$executable_pvjs_dir/@wikipathways/pvjs/dist");
printf "\n\e[1;33mTo enable/update browser version of pvjs, copy JS files into ./modules (check that permissions are set correctly):\e[0m\n"
echo "rm -rf ./modules; mkdir -p ./modules; cp $browser_pvjs_dir/* ./modules/"

# TODO: should we set PATH like this?
# should apache be able to use every utility installed for the default profile?
# what about having the apache user source this instead in /home/apache/.bashrc?
# what if different applications/sites need different versions of a cli tool?
envvars_path="$SCRIPT_DIR/envvars"
echo "export PATH=\"$SCRIPT_DIR/bin:$TARGET_USER_HOME/.nix-profile/bin:\$PATH\"" > "$envvars_path"

printf "\n\e[1;33mTo enable for Apache, add the following line to /etc/apache2/envvars (if not already present):\e[0m\n"
echo ". $envvars_path"
printf "\e[1;33mThen restart Apache:\e[0m\n"
# TODO: restart seems to not make the env vars show up, but stop followed by start does. Why?
#echo "sudo apachectl restart"
echo "sudo apachectl stop; sudo apachectl start"
