@include('provisioning.partials.header')

@include('provisioning.partials.ssh')

@include('provisioning.partials.swap')

@include('provisioning.partials.base-dependencies')

@include('provisioning.partials.awscli')

@include('provisioning.partials.cleanup')

@include('provisioning.partials.sudoers')

@include('provisioning.partials.redis')

@include('provisioning.partials.memcached')

@include('provisioning.partials.supervisor')

@include('provisioning.partials.security')

@include('provisioning.partials.footer')
