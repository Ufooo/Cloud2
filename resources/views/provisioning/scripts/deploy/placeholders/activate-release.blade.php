echo -e '\e[32m=> Activating release\e[0m'
ln -s "$NIP_RELEASE_DIRECTORY" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"

echo -e '\e[32m=> Purging old releases\e[0m'
cd "$NIP_RELEASES_PATH"
CURRENT_RELEASE=$(readlink -f "$NIP_SITE_ROOT/current" | xargs basename)
ls -t | tail -n +6 | grep -v "^${CURRENT_RELEASE}$" | xargs -r rm -rf

cd "$NIP_APPLICATION_PATH"
