echo -e '\e[32m=> Restarting queues\e[0m'
if [ -f artisan ]; then
    $NIP_PHP artisan queue:restart 2>/dev/null || true
fi
{{-- Errors stay in the deploy output: `2>/dev/null` hid a "sudo: a password
     is required" for months, so the deploy reported success while the daemon
     kept running the old code. A failure still must not abort the deploy. --}}
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl reread || true
    sudo supervisorctl update || true
@if(! empty($supervisorGroups ?? []))
    sudo supervisorctl restart {{ implode(' ', $supervisorGroups) }} || true
@endif
fi
