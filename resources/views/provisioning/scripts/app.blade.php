@include('provisioning.partials.header')

@include('provisioning.partials.ssh')

@include('provisioning.partials.swap')

@include('provisioning.partials.base-dependencies')

@include('provisioning.partials.awscli')

@include('provisioning.partials.cleanup')

@include('provisioning.partials.sudoers')

@include('provisioning.partials.php')

@include('provisioning.partials.nginx')

@include('provisioning.partials.nodejs')

@if($server->database_type?->type() === 'mysql')
@include('provisioning.partials.mysql')
@elseif($server->database_type?->type() === 'mariadb')
@include('provisioning.partials.mariadb')
@elseif($server->database_type?->type() === 'postgresql')
@include('provisioning.partials.postgresql')
@endif

@include('provisioning.partials.redis')

@include('provisioning.partials.memcached')

@include('provisioning.partials.supervisor')

@include('provisioning.partials.security')

@include('provisioning.partials.footer')
