export NIP_RELEASE_NAME=$(date +%Y%m%d%H%M%S)
export NIP_RELEASE_DIRECTORY="$NIP_RELEASES_PATH/$NIP_RELEASE_NAME"

echo -e '\e[32m=> Creating new release\e[0m'
echo -e "Cloning from \e[1m$NIP_SITE_REPOSITORY\e[0m"
echo -e "Cloning into \e[1m$NIP_RELEASE_DIRECTORY\e[0m"

git clone --branch $NIP_SITE_BRANCH --depth 1 "$NIP_SITE_REPOSITORY" "$NIP_RELEASE_DIRECTORY"

echo -e '\e[32m=> Linking environment file\e[0m'
rm -f "$NIP_RELEASE_DIRECTORY/.env"
ln -sfn "$NIP_SITE_ROOT/.env" "$NIP_RELEASE_DIRECTORY/.env"

if [ -f "$NIP_SITE_ROOT/auth.json" ]; then
    echo -e '\e[32m=> Linking auth.json file\e[0m'
    rm -f "$NIP_RELEASE_DIRECTORY/auth.json"
    ln -sfn "$NIP_SITE_ROOT/auth.json" "$NIP_RELEASE_DIRECTORY/auth.json"
fi

echo -e '\e[32m=> Linking storage directories\e[0m'
rm -rf "$NIP_RELEASE_DIRECTORY/storage"
ln -sfn "$NIP_SITE_ROOT/storage" "$NIP_RELEASE_DIRECTORY/storage"
