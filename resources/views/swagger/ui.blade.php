<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swagger UI</title>
    <link href="https://unpkg.com/swagger-ui-dist/swagger-ui.css" rel="stylesheet">
    <link rel="icon" href="{{ url('images/favicon-32x32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ url('images/favicon-16x16.png') }}" type="image/png" sizes="16x16">
</head>
<body>
<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-standalone-preset.js"></script>
<script>
    SwaggerUIBundle({
        url: "{{ $jsonUrl }}",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
    });
</script>
</body>
</html>
