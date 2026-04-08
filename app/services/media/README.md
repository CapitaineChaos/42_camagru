### Pour codes les end points protected

```php
<?php
require_once __DIR__ . '/../src/JWT.php';

$payload = JWT::auth();
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié.']);
    exit;
}
$userId = $payload['sub'];
```