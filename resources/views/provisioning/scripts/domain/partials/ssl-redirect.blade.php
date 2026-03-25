# HTTP to HTTPS redirect for {{ $domain }}
server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain }};
    server_tokens off;

    location / {
        return 301 https://$host$request_uri;
    }

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        alias /home/{{ $site->user }}/.letsencrypt/;
    }
}
