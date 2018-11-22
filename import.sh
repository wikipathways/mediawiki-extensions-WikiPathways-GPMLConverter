#!/usr/bin/env bash

gpmlconverter_f="./gpmlconverter"

wget -O "$gpmlconverter_f.tar.bz2" "https://ucb5dc179a7f814699617578abba.dl.dropboxusercontent.com/cd/0/get/AWBk9fidXm5kDHt9griN3hacs7FbUzG5MWJtPdmZmjpHvk844X2QWr9lmy3pmOKzBmb6mu8GAN4liIDqozsUI5xJJyyhVC_7kF11cPKINNx7p6FsySq90SotJt6mUh-g3QTTETf8YWCwjuquFJz9r6Jzt5-Dbdp4Fb1CyytzEGCguQc3nLztzpkfBN48lS21s5pIid3kwk2ixfN6h-khOyDg/file?_download_id=7977680457957250190417508629967540075288169068461475582331230171161&_notify_domain=www.dropbox.com&dl=1"

tar -jxf "$gpmlconverter_f.tar.bz2"

nix-store --import < "$gpmlconverter_f"
