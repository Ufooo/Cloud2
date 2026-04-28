{{-- Idempotent dependency check.
     Only runs apt-get if a required binary is missing — so a stale
     /etc/apt/sources.list.d/ entry can't break renewals on stable hosts.

     @param array<string, string> $dependencies  Map of "binary" => "apt package"
--}}
echo "Checking dependencies..."

NEEDED=()
@foreach($dependencies as $binary => $package)
command -v {{ $binary }} >/dev/null 2>&1 || NEEDED+=("{{ $package }}")
@endforeach

if [ ${#NEEDED[@]} -gt 0 ]; then
    echo "Installing missing packages: ${NEEDED[*]}"
    if ! apt-get update -qq; then
        echo "WARNING: apt-get update failed (likely a stale source in /etc/apt/sources.list.d/). Attempting install anyway..."
    fi
    if ! apt-get install -y -qq "${NEEDED[@]}" > /dev/null 2>&1; then
        echo "ERROR: failed to install required dependencies: ${NEEDED[*]}"
        echo "Hint: a stale repository in /etc/apt/sources.list.d/ can break this. Remove or fix it, then retry."
        exit 1
    fi
else
    echo "All dependencies already installed."
fi
