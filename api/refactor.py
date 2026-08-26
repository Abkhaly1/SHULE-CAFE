import os
import re

api_dir = '/opt/lampp/htdocs/shule-cafe/api'

for root, dirs, files in os.walk(api_dir):
    for f in files:
        if not f.endswith('.php'): continue
        filepath = os.path.join(root, f)
        
        with open(filepath, 'r') as file:
            content = file.read()
            
        if '2026' not in content:
            continue
            
        # 1. Fallbacks: ?? '2026' -> ?? date('Y')
        content = re.sub(r"\?\?\s*'2026'", "?? date('Y')", content)
        content = re.sub(r"\?\?\s*\"2026\"", "?? date('Y')", content)
        
        # 2. Defaults: DEFAULT '2026' -> we can leave it or remove it. Let's change DEFAULT '2026' to just have no default or dynamically handle it? Migrations are just schemas. Let's just remove DEFAULT '2026'.
        content = re.sub(r"\s*DEFAULT\s*'2026'", "", content)
        
        # 3. Inside double quoted SQL queries: " ... '2026' ... " -> " ... '" . date('Y') . "' ... "
        # We can find instances of '2026' and if they are in SQL strings, replace them.
        # Simple string replacement for SQL queries commonly found:
        content = content.replace("'2026'", "'\" . date('Y') . \"'")
        
        # 4. Wait, if it was ?? '2026', we already replaced it with ?? date('Y') so it's fine.
        # But what if there was $year = '2026'; ?
        content = content.replace("$year = '\" . date('Y') . \"';", "$year = date('Y');")
        content = content.replace("$year = $input['academic_year']   ?? '\" . date('Y') . \"';", "$year = $input['academic_year']   ?? date('Y');")
        content = content.replace("$year = $input['year'] ?? '\" . date('Y') . \"';", "$year = $input['year'] ?? date('Y');")
        content = content.replace("($year !== '\" . date('Y') . \"')", "($year !== date('Y'))")
        content = content.replace("$_GET['year'] ?? '\" . date('Y') . \"'", "$_GET['year'] ?? date('Y')")
        content = content.replace("$_GET['year']       ?? '\" . date('Y') . \"'", "$_GET['year'] ?? date('Y')")
        content = content.replace("$_POST['academic_year'] ?? '\" . date('Y') . \"'", "$_POST['academic_year'] ?? date('Y')")
        
        # Fix the migrations that used to have DEFAULT '2026' but now have DEFAULT '" . date('Y') . "'
        # wait, we removed DEFAULT '2026' before this replace.

        # Also fix: $year = '2026';
        content = content.replace("$year = '2026';", "$year = date('Y');")
        
        # And: $toYear   = $input['to_year']   ?? '2026';
        content = content.replace("?? '\" . date('Y') . \"'", "?? date('Y')")
        
        # And SELECT '2026' AS academic_year -> SELECT '\" . date('Y') . \"' AS academic_year
        # It's fine for it to be SELECT '" . date('Y') . "' AS academic_year because it's inside a PHP double quote string.

        with open(filepath, 'w') as file:
            file.write(content)

print("Done refactoring API files.")
