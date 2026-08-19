server {
@if(isset($certificate) && $certificate)
    listen 443 ssl;
    http2 on;
    listen [::]:443 ssl;
@else
    listen 80;
    listen [::]:80;
@endif

@if($allowWildcard)
@if($wildcardRedirects ?? false)
{{-- The wildcard moved to its own redirect block, which nginx reaches only for
     names that have no server block of their own. Claiming it here as well
     would be a conflicting server name and one of the two would be ignored. --}}
    server_name {{ $domain }};
@else
{{-- A www redirect is served by its own exact-match server block, which nginx
     prefers over this wildcard. Redirecting every $host that is not the apex
     would take down legitimate subdomains. --}}
    server_name {{ $domain }} *.{{ $domain }};
@endif
@else
@php
    // The redirected name only leaves this block once the www redirect can
    // actually serve it on 443, which needs a certificate covering it. Dropping
    // it earlier leaves the name homeless: it falls through to the catch-all or
    // to a neighbouring wildcard site.
    $redirectedName = $wwwRedirectType->value === 'to_www' ? $domain : 'www.'.$domain;
    $redirectedNameHasHome = isset($certificate)
        && $certificate
        && $certificate->coversDomain($redirectedName);
@endphp
@if($wwwRedirectType->value === 'from_www' && $redirectedNameHasHome)
    server_name {{ $domain }};
@elseif($wwwRedirectType->value === 'to_www' && $redirectedNameHasHome)
    server_name www.{{ $domain }};
@else
    server_name {{ $domain }} www.{{ $domain }};
@endif
@endif

    server_tokens off;
    root {{ $documentRoot }};

@if(isset($certificate) && $certificate)
    # SSL Certificate
    ssl_certificate {{ $certificate->getCertPath() }}/fullchain.crt;
    ssl_certificate_key {{ $certificate->getCertPath() }}/private.key;
@else
    # NETIPAR SSL (DO NOT REMOVE!)
    # ssl_certificate;
    # ssl_certificate_key;
@endif

    # Site common configuration
    include /etc/nginx/netipar-conf/{{ $site->domain }}/site.conf;


    # Static assets - cache for 7 days
    location ~* \.(ico|png|jpg|jpeg|gif|webp|svg|woff2|woff|ttf|eot)$ {
        expires 7d;
        add_header Cache-Control "public";
        access_log off;
    }

@if($siteType->isPhpBased())
    # Vite-built assets have content hashes - cache forever
    location /build/assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log /var/log/nginx/access.log combined_host;
    error_log  /var/log/nginx/{{ $domain }}-error.log error;

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:{{ $phpSocket }};
        fastcgi_index index.php;
        include fastcgi_params;
        include netipar_fastcgi_defaults;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
@else
    location / {
        try_files $uri $uri/ =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log /var/log/nginx/access.log combined_host;
    error_log  /var/log/nginx/{{ $domain }}-error.log error;

    location ~ /\. {
        deny all;
    }
@endif
}
