@if($wwwRedirectType->value !== 'none' && !($allowWildcard ?? false))
@php
    $isFromWww = $wwwRedirectType->value === 'from_www';
    $redirectedName = $isFromWww ? 'www.'.$domain : $domain;
    $targetName = $isFromWww ? $domain : 'www.'.$domain;

    $wwwCertificate = $certificate ?? null;
    $hasCoveringCertificate = $wwwCertificate && $wwwCertificate->coversDomain($redirectedName);
@endphp
# WWW Redirect: {{ $redirectedName }} -> {{ $targetName }}
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
    server_name {{ $redirectedName }};

    # Keep this name reachable on plain HTTP, otherwise the redirect swallows
    # the ACME challenge and the name can never be revalidated.
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        alias /home/{{ $site->user }}/.letsencrypt/;
    }

    location / {
        return 301 https://{{ $targetName }}$request_uri;
    }
}
@if($hasCoveringCertificate)

# HSTS and the host-preserving HTTP redirect mean browsers arrive straight on
# https, so the redirect only takes effect if it is also served on 443.
server {
    listen 443 ssl;
    http2 on;
    listen [::]:443 ssl;
    server_tokens off;
    server_name {{ $redirectedName }};

    ssl_certificate {{ $wwwCertificate->getCertPath() }}/fullchain.crt;
    ssl_certificate_key {{ $wwwCertificate->getCertPath() }}/private.key;

    include {{ $wwwCertificate->getSiteConfDir() }}/site.conf;

    return 301 https://{{ $targetName }}$request_uri;
}
@endif
@endif
