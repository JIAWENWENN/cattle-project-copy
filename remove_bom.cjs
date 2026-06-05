const fs = require('fs');
let content = fs.readFileSync('app/Http/Controllers/DailyOperationController.php');
if (content[0] === 0xEF && content[1] === 0xBB && content[2] === 0xBF) {
    content = content.slice(3);
}
fs.writeFileSync('app/Http/Controllers/DailyOperationController.php', content);
