#
# Activate Release (atomic swap)
#

echo "Activating new release..."
ln -s "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"

# Clean up old releases (keep last 5)
echo "Cleaning up old releases..."
cd "$NIP_RELEASES_PATH"
ls -1dt */ | tail -n +6 | xargs -r rm -rf
