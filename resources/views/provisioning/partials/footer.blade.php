curl --insecure --data "event_id={{ $eventId }}&server_id={{ $server->id }}&recipe_id=" {{ $callbackUrl }}

touch /root/.netipar-provisioned
