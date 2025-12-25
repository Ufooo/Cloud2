{{-- PHP Package Installation --}}
{{-- Variables: $version --}}

apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y \
    php{{ $version }}-fpm \
    php{{ $version }}-cli \
    php{{ $version }}-dev \
    php{{ $version }}-common \
    php{{ $version }}-pgsql \
    php{{ $version }}-sqlite3 \
    php{{ $version }}-gd \
    php{{ $version }}-curl \
    php{{ $version }}-imap \
    php{{ $version }}-mysql \
    php{{ $version }}-mbstring \
    php{{ $version }}-xml \
    php{{ $version }}-zip \
    php{{ $version }}-bcmath \
    php{{ $version }}-soap \
    php{{ $version }}-intl \
    php{{ $version }}-readline \
    php{{ $version }}-gmp \
    php{{ $version }}-redis \
    php{{ $version }}-memcached \
    php{{ $version }}-msgpack \
    php{{ $version }}-igbinary

@if(version_compare($version, '8.0', '>='))
apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y php{{ $version }}-swoole || true
@endif

@if(version_compare($version, '8.0', '<'))
apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y php{{ $version }}-json || true
@endif
