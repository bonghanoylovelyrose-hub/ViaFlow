<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
```

---

### Step 3 — Save the file
1. Click **File** → **Save As**
2. Navigate to:
```
C:\Users\John Ver\Desktop\waterbillingsystems
```
3. Set **"Save as type"** to **All Files (\*.\*)**
4. Name the file exactly:
```
server.php