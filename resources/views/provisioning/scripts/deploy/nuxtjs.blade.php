cd $NIP_SITE_PATH

git pull origin $NIP_SITE_BRANCH

npm ci || npm install
npm run build
