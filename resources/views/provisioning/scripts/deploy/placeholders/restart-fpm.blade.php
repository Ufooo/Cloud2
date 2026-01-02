echo -e '\e[32m=> Restarting FPM\e[0m'
( flock -w 10 9 || exit 1
    sudo -S service $NIP_PHP_FPM reload ) 9>/tmp/fpmlock-$(whoami)
