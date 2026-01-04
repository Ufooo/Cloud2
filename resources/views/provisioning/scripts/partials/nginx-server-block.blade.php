server {
    listen 80;
    listen [::]:80;

@if($allowWildcard)
    server_name .{{ $domain }};
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
    root {{ $rootPath }};

    # NETIPAR SSL (DO NOT REMOVE!)
    # ssl_certificate;
    # ssl_certificate_key;

    # Site common configuration
    include /etc/nginx/netipar-conf/{{ $site->id }}/site.conf;

    # Site-specific Nginx includes
    include {{ $fullPath }}/nginx.conf*;

@if($siteType->isPhpBased())
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log off;
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

    access_log off;
    error_log  /var/log/nginx/{{ $domain }}-error.log error;

    location ~ /\. {
        deny all;
    }
@endif
}
