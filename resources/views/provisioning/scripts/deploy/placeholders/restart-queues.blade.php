echo -e '\e[32m=> Restarting queues\e[0m'
if [ -f artisan ]; then
    $NIP_PHP artisan queue:restart 2>/dev/null || true
fi
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl reread 2>/dev/null || true
    sudo supervisorctl update 2>/dev/null || true
    sudo supervisorctl restart all 2>/dev/null || true
fi
