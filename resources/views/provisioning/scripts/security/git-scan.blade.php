{{-- Security git scan script --}}
#!/bin/bash
# Git status scanner for Cloud security monitoring
# Scans multiple site paths for uncommitted git changes
# Output: JSON format for parsing by SecurityMonitor module

# Start JSON output
echo '{"sites":['

first_site=true

@foreach($sites as $site)
# Add comma separator for subsequent sites
if [ "$first_site" = true ]; then
    first_site=false
else
    echo ','
fi

site_path="{{ $site['path'] }}"
site_user="{{ $site['user'] }}"

# Check if directory exists
if [ ! -d "$site_path" ]; then
    cat << EOF
{"path":"$site_path","error":"Directory does not exist"}
EOF
else
    # Check if it's a git repository
    if [ ! -d "$site_path/.git" ]; then
        cat << EOF
{"path":"$site_path","error":"Not a git repository"}
EOF
    else
        # Change to site directory
        cd "$site_path" 2>/dev/null
        if [ $? -ne 0 ]; then
            cat << EOF
{"path":"$site_path","error":"Cannot access directory"}
EOF
        else
            # Run git status and process output
            echo -n "{\"path\":\"$site_path\",\"changes\":["

            first_change=true

            # Use git status --porcelain for machine-readable output
            while IFS= read -r line; do
                # Skip empty lines
                [ -z "$line" ] && continue

                # Extract status code (first 2 characters) and file path
                status="${line:0:2}"
                file="${line:3}"

                # Handle renamed files (format: old -> new)
                if [[ "$file" == *" -> "* ]]; then
                    file="${file##* -> }"
                fi

                # Determine change type
                case "$status" in
                    "??") type="untracked" ;;
                    " M"|"M "|"MM") type="modified" ;;
                    " D"|"D "|"DD") type="deleted" ;;
                    " A"|"A "|"AA") type="added" ;;
                    " R"|"R ") type="renamed" ;;
                    " C"|"C ") type="copied" ;;
                    *) type="unknown" ;;
                esac

                # Escape special characters in file path for JSON
                escaped_file=$(echo "$file" | sed 's/\\/\\\\/g; s/"/\\"/g')
                escaped_status=$(echo "$status" | sed 's/\\/\\\\/g; s/"/\\"/g')

                # Add comma separator for subsequent changes
                if [ "$first_change" = true ]; then
                    first_change=false
                else
                    echo -n ','
                fi

                echo -n "{\"status\":\"$escaped_status\",\"type\":\"$type\",\"file\":\"$escaped_file\"}"

            done < <(sudo -u "$site_user" git status --porcelain 2>/dev/null)

            echo ']}'
        fi
    fi
fi
@endforeach

# Close JSON array
echo ']}'
