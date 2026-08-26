import os
import re

frontend_dir = '/opt/lampp/htdocs/shule-cafe/frontend'

for root, dirs, files in os.walk(frontend_dir):
    for f in files:
        if not (f.endswith('.html') or f.endswith('.js')): continue
        filepath = os.path.join(root, f)
        
        with open(filepath, 'r') as file:
            content = file.read()
            
        if '2026' not in content:
            continue
            
        # JS variables
        content = content.replace("let currentYear = '2026';", "let currentYear = new Date().getFullYear().toString();")
        content = content.replace("let currentYear    = '2026';", "let currentYear = new Date().getFullYear().toString();")
        content = content.replace("let currentContextYear = '2026';", "let currentContextYear = new Date().getFullYear().toString();")
        
        # JS default parameters
        content = content.replace("(year = '2026')", "(year = new Date().getFullYear().toString())")
        
        # JS fallbacks
        content = content.replace("|| '2026'", "|| new Date().getFullYear().toString()")
        content = content.replace("?? '2026'", "?? new Date().getFullYear().toString()")
        
        # Placeholders
        content = content.replace("STD/2026/001", "STD/${new Date().getFullYear()}/001")
        # For HTML placeholders where template literals won't work in raw HTML attributes
        # e.g. placeholder="Auto-generated (e.g. STD/2026/001)" -> placeholder="Auto-generated (e.g. STD/.../001)"
        content = content.replace('placeholder="Auto-generated (e.g. STD/${new Date().getFullYear()}/001)"', 'placeholder="Auto-generated (e.g. STD/2026/001)"') # revert
        # wait, let's just make placeholders generic
        content = content.replace("STD/2026/001", "STD/202X/001")
        content = content.replace("TCH/2026/001", "TCH/202X/001")
        content = content.replace("STD/2026", "STD/202X")
        content = content.replace("TCH/2026", "TCH/202X")
        
        # In Javascript strings
        content = content.replace("|| 'STD/202X/001'", "|| `STD/${new Date().getFullYear()}/001`")
        content = content.replace("|| 'TCH/202X/001'", "|| `TCH/${new Date().getFullYear()}/001`")
        content = content.replace("|| 'STD/202X'", "|| `STD/${new Date().getFullYear()}`")
        content = content.replace("|| 'TCH/202X'", "|| `TCH/${new Date().getFullYear()}`")

        # HTML text
        content = content.replace("Year 2026", "Current Year")
        content = content.replace("2026 Active", "Active Year")
        content = content.replace("Academic Year 2026", "Current Academic Year")
        content = content.replace("Active Year: 2026", "Active Year")
        
        # Any remaining JS constants
        content = content.replace("const ACADEMIC_YEAR = '2026';", "const ACADEMIC_YEAR = new Date().getFullYear().toString();")

        with open(filepath, 'w') as file:
            file.write(content)

print("Done refactoring frontend files.")
