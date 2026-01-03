# Netipar Cloud - Nginx Configuration
# Site: {{ $domain }}
# Generated: {{ now()->toISOString() }}

# Site-level includes
include /etc/nginx/netipar-conf/{{ $site->id }}/before/*;

# Domain-level includes
include /etc/nginx/netipar-conf/{{ $site->id }}/{{ $domain }}/before/*;

@include('provisioning.scripts.partials.nginx-server-block', [
    'site' => $site,
    'domain' => $domain,
    'fullPath' => $fullPath,
    'rootPath' => $rootPath,
    'phpSocket' => $phpSocket,
    'siteType' => $siteType,
    'allowWildcard' => $allowWildcard,
    'wwwRedirectType' => $wwwRedirectType,
])

# Site-level includes
include /etc/nginx/netipar-conf/{{ $site->id }}/after/*;

# Domain-level includes
include /etc/nginx/netipar-conf/{{ $site->id }}/{{ $domain }}/after/*;
