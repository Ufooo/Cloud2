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
    server_name {{ $domain }} *.{{ $domain }};
@if($wwwRedirectType->value === 'from_www')

    if ($host != {{ $domain }}) {
        return 301 $scheme://{{ $domain }}$request_uri;
    }
@elseif($wwwRedirectType->value === 'to_www')

    if ($host != www.{{ $domain }}) {
        return 301 $scheme://www.{{ $domain }}$request_uri;
    }
@endif
@else
@if($wwwRedirectType->value === 'from_www')
    server_name {{ $domain }};
@elseif($wwwRedirectType->value === 'to_www')
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
