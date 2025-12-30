@php
// Default to 'netipar' if $users is not passed (server provisioning context)
$users = $users ?? ['netipar'];
@endphp
# Setup Git SSH Key for Repository Access

echo "Configuring Git SSH key..."

@foreach($users as $user)
echo "Setting up SSH for user: {{ $user }}..."

# Create SSH directory for user if not exists
mkdir -p /home/{{ $user }}/.ssh
chmod 700 /home/{{ $user }}/.ssh

# Write the git private key
cat > /home/{{ $user }}/.ssh/id_ed25519 << 'GITPRIVATEKEYEOF'
{!! $gitPrivateKey !!}
GITPRIVATEKEYEOF

chmod 600 /home/{{ $user }}/.ssh/id_ed25519

# Configure SSH to use this key for git hosts
cat > /home/{{ $user }}/.ssh/config << 'SSHCONFIGEOF'
Host github.com
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new

Host gitlab.com
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new

Host bitbucket.org
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new
SSHCONFIGEOF

chmod 600 /home/{{ $user }}/.ssh/config

# Add known hosts for GitHub, GitLab, and Bitbucket
cat >> /home/{{ $user }}/.ssh/known_hosts << 'KNOWNHOSTSEOF'
github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl
github.com ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBEmKSENjQEezOmxkZMy7opKgwFB9nkt5YRrYMjNuG5N87uRgg6CLrbo5wAdT/y6v0mKV0U2w0WZ2YB/++Tpockg=
github.com ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQCj7ndNxQowgcQnjshcLrqPEiiphnt+VTTvDP6mHBL9j1aNUkY4Ue1gvwnGLVlOhGeYrnZaMgRK6+PKCUXaDbC7qtbW8gIkhL7aGCsOr/C56SJMy/BCZfxd1nWzAOxSDPgVsmerOBYfNqltV9/hWCqBywINIR+5dIg6JTJ72pcEpEjcYgXkE2YEFXV1JHnsKgbLWNlhScqb2UmyRkQyytRLtL+38TGxkxCflmO+5Z8CSSNY7GidjMIZ7Q4zMjA2n1nGrlTDkzwDCsw+wqFPGQA179cnfGWOWRVruj16z6XyvxvjJwbz0wQZ75XK5tKSb7FNyeIEs4TT4jk+S4dhPeAUC5y+bDYirYgM4GC7uEnztnZyaVWQ7B381AK4Qdrwt51ZqExKbQpTUNn+EjqoTwvqNj4kqx5QUCI0ThS/YkOxJCXmPUWZbhjpCg56i+2aB6CmK2JGhn57K5mj0MNdBXA4/WnwH6XoPWJzK5Nyu2zB3nAZp+S5hpQs+p1vN1/wsjk=
gitlab.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAfuCHKVTjquxvt6CM6tdG4SLp1Btn/nOeHHE5UOzRdf
gitlab.com ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBFSMqzJeV9rUzU4kWitGjeR4PWSa29SPqJ1fVkhtj3Hw9xjLVXVYrU9QlYWrOLXBpQ6KWjbjTDTdDkoohFzgbEY=
gitlab.com ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCsj2bNKTBSpIYDEGk9KxsGh3mySTRgMtXL583qmBpzeQ+jqCMRgBqB98u3z++J1sKlXHWfM9dyhSevkMwSbhoR8XIq/U0tCNyokEi/ueaBMCvbcTHhO7FcwzY92WK4 Voices7fUIm/kFljNHop2DvIRBfBibuO7C0M1xqj/VoVpZfJM5J4yfAfnBKfjAfBo9O2HxC7lcYeC0X8ERfkNFjzLjIIzGKs0uHwVM4dGzBnE3zvpars2CFwKClFSAQB7MzRIkHsatOX3gKDD6ATz5sQ3LrKy5aGSr3gq+xlnHqz3DzBj8b7cHFdqhREFggGQRG5YymG5lfbJ6j2t/cOZ1rP1
bitbucket.org ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIazEu89wgQZ4bqs3d63QSMzYVa0MuJ2e2gKTKqu+UUO
bitbucket.org ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBPIQmuzMBuKdWeF4+a2sjSSpBK0iqitSQ+5BM9KhpexuGt20JpTVM7u5BDZngncgrqDMbWdxMWWOGtZ9UgbqgZE=
bitbucket.org ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAubiN81eDcafrgMeLzaFPsw2kNvEcqTKl/VqLat/MaB33pZy0y3rJZtnqwR2qOOvbwKZYKiEO1O6VqNEBxKvJJelCq0dTXWT5pbO2gDXC6h6QDXCaHo6pOHGPUy+YBaGQRGuSusMEASYiWunYN0vCAI8QaXnWMXNMdFP3jHAJH0eDsoiGnLPBlBp4TNm6rYI74nMzgz3B9IikW4WVK+dc8KZJZWYjAuORU3jc1c/NPskD2ASinf8v3xnfXeukU0sJ5N6m5E8VLjObPEO+mN2t/FZTMZLiFqPWc/ALSqnMnnhwrNi2rbfg/rd/IpL8Le3pSBne8+seeFVBoGqzHM9yXw==
KNOWNHOSTSEOF

# Remove duplicate lines from known_hosts
sort -u /home/{{ $user }}/.ssh/known_hosts -o /home/{{ $user }}/.ssh/known_hosts

chmod 600 /home/{{ $user }}/.ssh/known_hosts

# Set ownership
chown -R {{ $user }}:{{ $user }} /home/{{ $user }}/.ssh

echo "SSH configured for {{ $user }}"
@endforeach

echo "Git SSH key configured for all users."
