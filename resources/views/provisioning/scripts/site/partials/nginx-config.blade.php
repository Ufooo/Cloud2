# Netipar Cloud - Nginx Configuration
# Site: {{ $domain }}
# Generated: {{ now()->toISOString() }}

include /etc/nginx/netipar-conf/{{ $site->id }}/before/*;

@include('provisioning.scripts.partials.nginx-www-redirect', [
    'domain' => $domain,
    'wwwRedirectType' => $wwwRedirectType,
])

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

include /etc/nginx/netipar-conf/{{ $site->id }}/after/*;
