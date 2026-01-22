    cat > "$PROJECT_DIR{{ $webDirectory }}/index.html" << 'HTMLEOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $domain }}</title>
</head>
<body>
    <h1>Site is ready</h1>
    <p>Deploy your application to get started.</p>
</body>
</html>
HTMLEOF
