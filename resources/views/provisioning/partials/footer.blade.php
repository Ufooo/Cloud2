# Final provisioning callback - status 10 marks completion
provisionPing {{ $server->id }} 10

touch /root/.netipar-provisioned
