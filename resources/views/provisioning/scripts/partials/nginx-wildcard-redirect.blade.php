@if($wildcardRedirects ?? false)

# Wildcard catch-all: {{ '*.'.$domain }} -> {{ $domain }}
# Only names without a server block of their own land here. nginx always
# prefers an exact server_name over a wildcard, so every configured subdomain
# keeps its own site without this block having to test $host.
#
# Port 80 is deliberately absent: the site's ssl_redirect config already claims
# {{ '*.'.$domain }} there and carries the ACME challenge location.
server {
    listen 443 ssl;
    http2 on;
    listen [::]:443 ssl;

    server_name {{ '*.'.$domain }};
    server_tokens off;

    ssl_certificate {{ $certificate->getCertPath() }}/fullchain.crt;
    ssl_certificate_key {{ $certificate->getCertPath() }}/private.key;

    include {{ $certificate->getSiteConfDir() }}/site.conf;

    return 301 https://{{ $domain }}$request_uri;
}
@endif
