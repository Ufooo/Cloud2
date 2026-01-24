# Netipar Cloud - Nginx Configuration
# Site: {{ $domain }}
# Generated: {{ now()->toISOString() }}

# Site-level includes
include /etc/nginx/netipar-conf/{{ $site->domain }}/before/*;

# Domain-level includes
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/before/*;

@include('provisioning.scripts.partials.nginx-server-block', [
    'site' => $site,
    'domain' => $domain,
    'applicationPath' => $applicationPath,
    'documentRoot' => $documentRoot,
    'phpSocket' => $phpSocket,
    'siteType' => $siteType,
    'allowWildcard' => $allowWildcard,
    'wwwRedirectType' => $wwwRedirectType,
])

# Site-level includes
include /etc/nginx/netipar-conf/{{ $site->domain }}/after/*;

# Domain-level includes
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/after/*;
