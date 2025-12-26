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

    # SSL placeholder - will be configured when certificate is installed
    # listen 443 ssl http2;
    # listen [::]:443 ssl http2;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;

    charset utf-8;

    include /etc/nginx/netipar-conf/{{ $site->id }}/server/*;

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
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;

        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
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
