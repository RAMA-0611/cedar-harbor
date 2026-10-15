<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Rotor API Docs</title>
    <link rel="stylesheet" href="{{ $cssUrl }}">
    <style>
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="{{ $bundleUrl }}"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: @json($specUrl),
        dom_id: '#swagger-ui',
        deepLinking: true,
        docExpansion: 'list',
        defaultModelsExpandDepth: 1,
        tryItOutEnabled: true,
        presets: [SwaggerUIBundle.presets.apis],
        layout: 'BaseLayout'
    });
</script>
</body>
</html>
